<?php
/* ============================================================================
 *  CONEXIÓN A LA BASE DE DATOS
 *  Abre la conexión MySQL usando los datos de config.php.
 *  Devuelve un objeto PDO (la herramienta de PHP para hablar con MySQL).
 * ========================================================================== */

function db() {
  static $pdo = null;            // se conecta una sola vez por pedido
  if ($pdo !== null) return $pdo;

  $cfgPath = __DIR__ . '/../config.php';
  if (!file_exists($cfgPath)) {
    json_error('Falta el archivo config.php en el servidor. Copiá config.example.php a config.php y completá los datos.', 500);
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
  if ($c === null) $c = require __DIR__ . '/../config.php';
  return $c;
}
