<?php
/* ============================================================================
 *  APICE - INSTALADOR DE UN SOLO CLIC
 *
 *  Que hace: crea TODAS las tablas de la base y carga los feriados, leyendo
 *  los archivos schema.sql y seed.sql. Asi no tenes que usar phpMyAdmin.
 *
 *  Como se usa:
 *    1) Asegurate de tener subido public_html/api/config.php (con los datos
 *       de tu base).
 *    2) En el navegador entra a:  https://abogadoscatamarca.com/install.php
 *    3) Segui las instrucciones en pantalla.
 *    4) Cuando termine, BORRA este archivo (install.php) del servidor, por
 *       seguridad. (Si lo dejas, igual se bloquea solo una vez instalado.)
 * ========================================================================== */

header('Content-Type: text/html; charset=utf-8');

$cfgPath    = __DIR__ . '/api/config.php';
$schemaPath = __DIR__ . '/api/schema.sql';
$seedPath   = __DIR__ . '/api/seed.sql';

function salir($html) {
  echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
     . '<meta name="viewport" content="width=device-width, initial-scale=1">'
     . '<title>ÁPICE · Instalador</title><style>'
     . 'body{font-family:system-ui,sans-serif;background:#EEF1F5;color:#1C2433;margin:0;padding:40px 16px}'
     . '.card{max-width:560px;margin:0 auto;background:#fff;border:1px solid #E5E7EB;border-radius:14px;padding:28px}'
     . 'h1{font-size:20px;margin:0 0 14px}.ok{background:#E7F1EA;color:#14532D;padding:12px 14px;border-radius:9px}'
     . '.err{background:#FBEBEB;color:#8a2828;padding:12px 14px;border-radius:9px}'
     . 'code{background:#F1F3F6;padding:2px 6px;border-radius:5px}'
     . 'li{margin:6px 0}a{color:#2563EB}</style></head><body><div class="card">'
     . $html . '</div></body></html>';
  exit;
}

// 1) Verificar que exista config.php
if (!file_exists($cfgPath)) {
  salir('<h1>Falta config.php</h1><div class="err">No encontré <code>public_html/api/config.php</code>. '
      . 'Subilo primero (con los datos de tu base) y volvé a entrar a esta página.</div>');
}
$cfg = require $cfgPath;

// 2) Conectar a la base
try {
  $dsn = "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset=utf8mb4";
  $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);
} catch (Throwable $e) {
  salir('<h1>No me pude conectar a la base</h1><div class="err">Revisá los 4 datos de la base en '
      . '<code>config.php</code> (nombre, usuario y contraseña). Detalle técnico: '
      . htmlspecialchars($e->getMessage()) . '</div>');
}

// 3) ¿Ya estaba instalado? (si existe la tabla "estudios", no repetir el schema)
$yaInstalado = false;
try {
  $r = $pdo->query("SHOW TABLES LIKE 'estudios'");
  $yaInstalado = ($r && $r->rowCount() > 0);
} catch (Throwable $e) { $yaInstalado = false; }

// Función que ejecuta un archivo .sql sentencia por sentencia.
function correrSql($pdo, $ruta) {
  if (!file_exists($ruta)) throw new Exception('No encontré ' . basename($ruta));
  $sql = file_get_contents($ruta);
  // Quitar comentarios de bloque y de línea para poder separar por ";".
  $sql = preg_replace('#/\*.*?\*/#s', '', $sql);
  $limpio = '';
  foreach (explode("\n", $sql) as $linea) {
    $limpio .= preg_replace('/--.*$/', '', $linea) . "\n";
  }
  $sentencias = array_filter(array_map('trim', explode(';', $limpio)), 'strlen');
  $n = 0;
  foreach ($sentencias as $s) { $pdo->exec($s); $n++; }
  return $n;
}

// 4) Acción
$accion = $_GET['accion'] ?? '';
if ($accion !== 'instalar') {
  $aviso = $yaInstalado
    ? '<div class="ok">Detecté que las tablas ya existen. Podés volver a ejecutar sin problema: '
      . 'las tablas no se duplican y los feriados solo se cargan si faltan.</div>'
    : '<div class="ok">Todo listo para crear la base. Conexión a MySQL: correcta ✓</div>';
  salir('<h1>Instalador de ÁPICE</h1>' . $aviso
      . '<p>Voy a crear todas las tablas y cargar los feriados de Argentina y Catamarca.</p>'
      . '<p><a href="?accion=instalar" style="display:inline-block;background:#1C2433;color:#fff;'
      . 'padding:11px 18px;border-radius:9px;text-decoration:none;font-weight:600">Instalar ahora →</a></p>');
}

// 5) Ejecutar
try {
  $t = correrSql($pdo, $schemaPath);   // crea tablas (usa CREATE TABLE IF NOT EXISTS)
  // Cargar feriados solo si la tabla está vacía (para no duplicar).
  $hayFeriados = (int)$pdo->query('SELECT COUNT(*) FROM feriados')->fetchColumn();
  $f = 0;
  if ($hayFeriados === 0) { $f = correrSql($pdo, $seedPath); }
  salir('<h1>¡Instalación completa! ✓</h1>'
      . '<div class="ok">Se prepararon las tablas (' . (int)$t . ' sentencias) y '
      . ($hayFeriados === 0 ? 'se cargaron los feriados' : 'los feriados ya estaban cargados') . '.</div>'
      . '<h3 style="margin-top:18px">Ahora, los últimos pasos:</h3><ol>'
      . '<li><b>Borrá este archivo</b> <code>install.php</code> del servidor (por seguridad).</li>'
      . '<li>Entrá a <a href="/api/crear-usuario.php">crear tu usuaria</a> '
      . '(<code>/api/crear-usuario.php</code>) y después borrá también ese archivo.</li>'
      . '<li>Entrá a <a href="/">ÁPICE</a> con tu email y contraseña. ¡Listo!</li>'
      . '</ol>');
} catch (Throwable $e) {
  salir('<h1>Hubo un error durante la instalación</h1>'
      . '<div class="err">' . htmlspecialchars($e->getMessage()) . '</div>'
      . '<p>Si el mensaje habla de una tabla que ya existe, probablemente ya estaba instalada y '
      . 'podés continuar igual con la creación de tu usuaria.</p>');
}
