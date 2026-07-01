<?php
/* ============================================================================
 *  CONEXIÓN A LA BASE DE DATOS
 *  Abre la conexión MySQL usando los datos de config.php.
 *  Devuelve un objeto PDO (la herramienta de PHP para hablar con MySQL).
 * ========================================================================== */

/* Devuelve la ruta del config.php. Orden de preferencia:
 *   1) FUERA de public_html (una carpeta arriba). Es lo más seguro (no es
 *      accesible desde la web) y el despliegue NUNCA lo toca ni lo borra.
 *   2) Dentro de public_html/api/config.php (alternativa/compatibilidad).
 *   db.php vive en .../public_html/api/lib, así que subir 3 niveles llega
 *   a la carpeta del dominio (arriba de public_html). */
function config_path() {
  $raiz = dirname(__DIR__, 3);   // carpeta del dominio, arriba de public_html
  // 1) Ubicación segura: carpeta propia con nombre no obvio, FUERA de public_html.
  $secreto = $raiz . '/apice_privado/apice_config.php';
  if (is_file($secreto)) return $secreto;
  // 2) Alternativa: config.php directamente fuera de public_html.
  $fuera = $raiz . '/config.php';
  if (is_file($fuera)) return $fuera;
  // 3) Respaldo/compatibilidad: dentro de public_html/api/config.php.
  return dirname(__DIR__) . '/config.php';
}

function db() {
  static $pdo = null;            // se conecta una sola vez por pedido
  if ($pdo !== null) return $pdo;

  $cfgPath = config_path();
  if (!file_exists($cfgPath)) {
    json_error('Falta el archivo config.php (se buscó fuera y dentro de public_html).', 500);
  }
  $cfg = require $cfgPath;

  $dsn = "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset=utf8mb4";
  try {
    $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
  } catch (PDOException $e) {
    // No mostramos detalles técnicos al usuario (seguridad).
    json_error('No se pudo conectar a la base de datos. Revisá los datos en config.php.', 500);
  }
  return $pdo;
}

/* Devuelve toda la configuración (por si algún endpoint la necesita). */
function cfg() {
  static $c = null;
  if ($c === null) {
    $cfgPath = config_path();
    if (!file_exists($cfgPath)) {
      json_error('No se encuentra config.php (ni fuera ni dentro de public_html).', 500);
    }
    $c = require $cfgPath;
  }
  return $c;
}
