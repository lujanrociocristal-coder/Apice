<?php
/* ============================================================================
* TAREA DIARIA (Cron) — envía notificaciones al celular con la app cerrada.
*
* Lo ejecuta Hostinger una vez por día. Revisa qué estudios tienen audiencias
* o citas para HOY o MAÑANA, o causas con plazo de caducidad de instancia
* vencido o por vencer, y les manda un push a sus dispositivos. El celular,
* al recibirlo, muestra el aviso con el logo de ÁPICE.
*
* Comando en Cron (PHP): <ruta>/public_html/api/cron-push.php
* ========================================================================== */

/* SEGURIDAD (v46): esta tarea la ejecuta el Cron por linea de comandos.
   Desde internet solo funciona con la clave secreta de config.php; si no,
   responde 404 y no ejecuta nada. Evita que un tercero dispare los avisos. */
if (php_sapi_name() !== 'cli') {
  require_once __DIR__ . '/lib/db.php';
  $rutaSeg = function_exists('config_path') ? config_path() : (__DIR__ . '/config.php');
  $cfgSeg = @include $rutaSeg;
  $tokSeg = (is_array($cfgSeg) && !empty($cfgSeg['backup_token'])) ? $cfgSeg['backup_token'] : '';
  $dadoSeg = isset($_GET['token']) ? (string)$_GET['token'] : '';
  if ($tokSeg === '' || $dadoSeg === '' || !hash_equals($tokSeg, $dadoSeg)) {
    http_response_code(404);
    exit('Not found');
  }
}

require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/push.php';
require __DIR__ . '/lib/caducidad.php';

$total = 0;
try {
  $pdo = db();
  // Asegurar la tabla (por si el cron corre antes de la primera suscripción).
  $pdo->exec("CREATE TABLE IF NOT EXISTS push_subs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, usuario_id INT UNSIGNED NOT NULL,
  estudio_id INT UNSIGNED NOT NULL, endpoint VARCHAR(500) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id),
  UNIQUE KEY uq_ep (endpoint(255)), KEY idx_est (estudio_id), KEY idx_usr (usuario_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  
  $hoy = date('Y-m-d');
  $man = date('Y-m-d', strtotime('+1 day'));
  
  // Estudios con audiencias/citas para hoy o mañana.
  $st = $pdo->prepare("SELECT DISTINCT estudio_id FROM audiencias WHERE fecha IN (?, ?)");
  $st->execute([$hoy, $man]);
  $conAudiencia = $st->fetchAll(PDO::FETCH_COLUMN);
  
  // Estudios con causas cuyo plazo de caducidad está vencido o vence pronto.
  // Se revisan solo los estudios que tienen algún dispositivo suscripto.
  $conCaducidad = [];
  $stE = $pdo->query('SELECT DISTINCT estudio_id FROM push_subs');
  foreach ($stE->fetchAll(PDO::FETCH_COLUMN) as $eid) {
    if (causas_por_vencer($eid, 15)) $conCaducidad[] = $eid;
  }
  
  $estudios = array_unique(array_merge($conAudiencia, $conCaducidad));
  
  foreach ($estudios as $eid) {
    $s2 = $pdo->prepare('SELECT endpoint FROM push_subs WHERE estudio_id = ?');
    $s2->execute([(int)$eid]);
    foreach ($s2->fetchAll(PDO::FETCH_COLUMN) as $ep) {
      $code = push_send($ep);
      if ($code === 404 || $code === 410) {
        // Suscripción vencida: la borramos.
        $pdo->prepare('DELETE FROM push_subs WHERE endpoint = ?')->execute([$ep]);
      } else {
        $total++;
      }
    }
  }
} catch (Throwable $e) {
  echo 'Error: ' . $e->getMessage() . "\n";
}
  echo "Push enviados: $total\n";
