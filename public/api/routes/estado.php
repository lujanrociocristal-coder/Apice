<?php
/* ============================================================================
 *  ESTADO DE LA APP  (/api/estado/...)
 *
 *  Guarda y entrega los "bloques" de datos que usa el frontend (causas,
 *  agenda, clientes, guía, configuración) en formato JSON, COMPARTIDOS por
 *  todo el estudio. Es el corazón de la conexión "rápida y segura".
 *
 *    GET    /api/estado/{clave}   -> devuelve { value: "...json..." } o null
 *    PUT    /api/estado/{clave}   -> guarda el bloque (cuerpo: { value: ... })
 *    DELETE /api/estado/{clave}   -> borra el bloque
 *
 *  Reglas:
 *    - Hay que estar logueada (si no, 401 -> la app muestra el ingreso).
 *    - Los profesionales pueden leer y escribir.
 *    - Los clientes pueden LEER (su portal es de solo lectura).
 * ========================================================================== */

function handle_estado($method, $resto) {
  $u = require_login();
  $clave = $resto[0] ?? '';
  if ($clave === '') json_error('Falta la clave del bloque.');
  // Validación simple de la clave (letras, números, guion bajo, punto).
  if (!preg_match('/^[A-Za-z0-9_.\-]{1,80}$/', $clave)) json_error('Clave no válida.');

  $eid = (int)$u['estudio_id'];

  if ($method === 'GET') {
    $st = db()->prepare('SELECT valor FROM estado_app WHERE estudio_id = ? AND clave = ?');
    $st->execute([$eid, $clave]);
    $v = $st->fetchColumn();
    json_ok($v === false ? null : ['value' => $v]);
  }

  if ($method === 'PUT') {
    if ($u['rol'] !== 'profesional') json_error('Los clientes no pueden modificar datos.', 403);
    $valor = field('value');
    if (!is_string($valor)) $valor = json_encode($valor, JSON_UNESCAPED_UNICODE);
    db()->prepare('INSERT INTO estado_app (estudio_id, clave, valor) VALUES (?,?,?)
                   ON DUPLICATE KEY UPDATE valor = VALUES(valor)')
        ->execute([$eid, $clave, $valor]);
    json_ok(['guardado' => true]);
  }

  if ($method === 'DELETE') {
    if ($u['rol'] !== 'profesional') json_error('Los clientes no pueden borrar datos.', 403);
    db()->prepare('DELETE FROM estado_app WHERE estudio_id = ? AND clave = ?')->execute([$eid, $clave]);
    json_ok(['borrado' => true]);
  }

  json_error('Método no permitido.', 405);
}
