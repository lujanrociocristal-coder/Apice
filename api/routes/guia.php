<?php
/* ============================================================================
 *  GUÍA JUDICIAL / DIRECTORIO  (/api/guia/...)
 *    GET    /api/guia            -> organismos del estudio (juzgados, etc.)
 *    POST   /api/guia            -> agregar organismo
 *    PUT    /api/guia/{id}       -> editar
 *    DELETE /api/guia/{id}       -> borrar
 * ========================================================================== */

function handle_guia($method, $resto) {
  $id = isset($resto[0]) ? (int)$resto[0] : 0;
  if ($id) {
    if ($method === 'PUT')    return guia_editar($id);
    if ($method === 'DELETE') return guia_borrar($id);
  }
  if ($method === 'GET')  return guia_listar();
  if ($method === 'POST') return guia_crear();
  json_error('Método no permitido.', 405);
}

function guia_listar() {
  $u = require_login();
  $st = db()->prepare('SELECT * FROM guia_judicial WHERE estudio_id = ? ORDER BY categoria, nombre');
  $st->execute([$u['estudio_id']]);
  $rows = $st->fetchAll();
  foreach ($rows as &$r) decode_json_fields($r, ['integrantes']);
  json_ok($rows);
}

function _guia_campos() { return ['ref','categoria','nombre','rol','direccion','tel','email','notas','oficial','actualizado']; }

function guia_crear() {
  $u = require_profesional();
  if (!field('nombre') || !field('categoria')) json_error('Indicá al menos categoría y nombre.');
  $cols = ['estudio_id']; $vals = [$u['estudio_id']]; $ph = ['?'];
  foreach (_guia_campos() as $f) {
    $v = field($f); if ($f === 'oficial') $v = $v ? 1 : 0;
    $cols[] = $f; $vals[] = $v; $ph[] = '?';
  }
  $cols[] = 'integrantes'; $vals[] = json_encode(field('integrantes', []), JSON_UNESCAPED_UNICODE); $ph[] = '?';
  db()->prepare('INSERT INTO guia_judicial (' . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')')->execute($vals);
  json_ok(['id' => (int)db()->lastInsertId()], 201);
}

function guia_editar($id) {
  $u = require_profesional();
  $sets = []; $vals = [];
  foreach (_guia_campos() as $f) {
    $v = field($f, '__NO__'); if ($v === '__NO__') continue;
    if ($f === 'oficial') $v = $v ? 1 : 0;
    $sets[] = "$f = ?"; $vals[] = $v;
  }
  if (field('integrantes') !== null) { $sets[] = 'integrantes = ?'; $vals[] = json_encode(field('integrantes'), JSON_UNESCAPED_UNICODE); }
  if (!$sets) json_error('No hay nada para actualizar.');
  $vals[] = $id; $vals[] = $u['estudio_id'];
  db()->prepare('UPDATE guia_judicial SET ' . implode(', ', $sets) . ' WHERE id = ? AND estudio_id = ?')->execute($vals);
  json_ok(['actualizado' => true]);
}

function guia_borrar($id) {
  $u = require_profesional();
  db()->prepare('DELETE FROM guia_judicial WHERE id = ? AND estudio_id = ?')->execute([$id, $u['estudio_id']]);
  json_ok(['borrado' => true]);
}
