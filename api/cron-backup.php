<?php
/* ============================================================================
 *  RESPALDO AUTOMATICO DE LA BASE  (cron-backup.php)
 *
 *  Que hace: guarda una copia completa de la base de datos en un archivo
 *  comprimido, en una carpeta PRIVADA fuera de public_html. Conserva las
 *  ultimas copias y borra las mas viejas.
 *
 *  Como se ejecuta (Cron de Hostinger, tipo PHP, una vez por dia):
 *      <ruta>/public_html/api/cron-backup.php
 *
 *  SEGURIDAD:
 *   - Por linea de comandos (cron): corre sin restriccion.
 *   - Por internet (HTTP): SOLO funciona si en config.php existe
 *     'backup_token' y se pasa ?token=ESE_VALOR. Si no, responde 404.
 *     Esto evita que cualquiera dispare o descubra el respaldo.
 *   - Los archivos NUNCA se guardan en public_html, asi no se pueden
 *     descargar desde el navegador.
 * ========================================================================== */

$esCLI = (php_sapi_name() === 'cli');

if (!$esCLI) {
  $cfg = @include __DIR__ . '/config.php';
  $token = (is_array($cfg) && !empty($cfg['backup_token'])) ? $cfg['backup_token'] : '';
  $dado  = isset($_GET['token']) ? (string)$_GET['token'] : '';
  if ($token === '' || $dado === '' || !hash_equals($token, $dado)) {
    http_response_code(404);
    exit('Not found');
  }
  header('Content-Type: text/plain; charset=utf-8');
}

require __DIR__ . '/lib/db.php';

/* Carpeta privada: al lado de public_html, nunca dentro. */
$destino = dirname(__DIR__, 2) . '/apice_backups';
if (!is_dir($destino)) @mkdir($destino, 0750, true);
if (!is_dir($destino)) { echo "ERROR: no se pudo crear la carpeta de respaldos\n"; exit(1); }

$CONSERVAR = 14; // cuantas copias se guardan

function esc_val($pdo, $v) {
  if ($v === null) return 'NULL';
  return $pdo->quote((string)$v);
}

try {
  $pdo = db();
  $fecha  = date('Y-m-d_His');
  $ruta   = $destino . '/apice-' . $fecha . '.sql.gz';
  $gz = gzopen($ruta, 'wb9');
  if (!$gz) { echo "ERROR: no se pudo crear el archivo\n"; exit(1); }

  gzwrite($gz, "-- Respaldo APICE  " . date('c') . "\n");
  gzwrite($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

  $tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
  $totalFilas = 0;

  foreach ($tablas as $tabla) {
    $crear = $pdo->query('SHOW CREATE TABLE `' . $tabla . '`')->fetch(PDO::FETCH_NUM);
    gzwrite($gz, "\n-- Tabla: $tabla\nDROP TABLE IF EXISTS `$tabla`;\n" . $crear[1] . ";\n");

    $st = $pdo->query('SELECT * FROM `' . $tabla . '`');
    $filas = 0;
    while ($fila = $st->fetch(PDO::FETCH_ASSOC)) {
      $cols = '`' . implode('`,`', array_keys($fila)) . '`';
      $vals = [];
      foreach ($fila as $v) $vals[] = esc_val($pdo, $v);
      gzwrite($gz, "INSERT INTO `$tabla` ($cols) VALUES (" . implode(',', $vals) . ");\n");
      $filas++; $totalFilas++;
    }
    gzwrite($gz, "-- ($filas filas)\n");
  }

  gzwrite($gz, "\nSET FOREIGN_KEY_CHECKS=1;\n");
  gzclose($gz);

  $kb = round(filesize($ruta) / 1024, 1);
  echo "OK: respaldo creado -> " . basename($ruta) . " ({$kb} KB, " . count($tablas) . " tablas, {$totalFilas} filas)\n";

  /* Borrar los respaldos mas viejos, conservando los ultimos $CONSERVAR. */
  $previos = glob($destino . '/apice-*.sql.gz');
  if ($previos && count($previos) > $CONSERVAR) {
    usort($previos, function ($a, $b) { return filemtime($b) - filemtime($a); });
    foreach (array_slice($previos, $CONSERVAR) as $viejo) {
      @unlink($viejo);
      echo "  - borrado por antiguedad: " . basename($viejo) . "\n";
    }
  }
  echo "Copias guardadas: " . count(glob($destino . '/apice-*.sql.gz')) . " (se conservan las ultimas {$CONSERVAR})\n";

} catch (Throwable $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
  exit(1);
}
