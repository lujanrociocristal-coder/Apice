<?php
/* ============================================================================
 *  RESPUESTAS JSON
 *  Funciones cortas para contestarle al frontend siempre en el mismo formato.
 * ========================================================================== */

/* Lee el cuerpo del pedido (lo que envía el frontend) como arreglo PHP. */
function body() {
  static $data = null;
  if ($data !== null) return $data;
  $raw = file_get_contents('php://input');
  $data = $raw ? json_decode($raw, true) : [];
  if (!is_array($data)) $data = [];
  return $data;
}

/* Toma un campo del cuerpo con un valor por defecto. */
function field($name, $default = null) {
  $b = body();
  return array_key_exists($name, $b) ? $b[$name] : $default;
}

/* Respuesta exitosa. */
function json_ok($data = [], $code = 200) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
  exit;
}

/* Respuesta de error. */
function json_error($mensaje, $code = 400) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => false, 'error' => $mensaje], JSON_UNESCAPED_UNICODE);
  exit;
}

/* Decodifica columnas JSON de la base a arreglos PHP (para no devolver texto). */
function decode_json_fields(&$row, $fields) {
  foreach ($fields as $f) {
    if (isset($row[$f]) && is_string($row[$f])) {
      $row[$f] = json_decode($row[$f], true);
    }
  }
}
