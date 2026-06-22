<?php
/* ============================================================================
 *  CONFIGURACIÓN DEL ESTUDIO  (/api/config/...)
 *    GET  /api/config              -> valor IUS, datos del estudio, feriados
 *    PUT  /api/config              -> editar valor IUS / datos del estudio
 *    GET  /api/config/feriados     -> lista de feriados (estudio + globales)
 *    POST /api/config/feriados     -> agregar feriado
 *    DELETE /api/config/feriados/{id} -> borrar feriado
 * ========================================================================== */

function handle_config($method, $resto) {
  $sub = $resto[0] ?? '';
  $id  = isset($resto[1]) ? (int)$resto[1] : 0;

  if ($sub === 'feriados') {
    if ($method === 'GET')    return feriados_listar();
    if ($method === 'POST')   return feriado_crear();
    if ($method === 'DELETE') return feriado_borrar($id);
  }
  if ($method === 'GET') return config_ver();
  if ($method === 'PUT') return config_editar();
  json_error('Método no permitido.', 405);
}

function config_ver() {
  $u = require_login();
  $st = db()->prepare('SELECT id, nombre, domicilio, telefono, email, cuit, valor_ius, recibo_seq, logo_url FROM estudios WHERE id = ?');
  $st->execute([$u['estudio_id']]);
  json_ok($st->fetch());
}

function config_editar() {
  $u = require_profesional();
  $sets = []; $vals = [];
  foreach (['nombre','domicilio','telefono','email','cuit','valor_ius','logo_url'] as $f) {
    $v = field($f, '__NO__'); if ($v !== '__NO__') { $sets[] = "$f = ?"; $vals[] = $v; }
  }
  if (!$sets) json_error('No hay nada para actualizar.');
  $vals[] = $u['estudio_id'];
  db()->prepare('UPDATE estudios SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
  json_ok(['actualizado' => true]);
}

function feriados_listar() {
  $u = require_login();
  $st = db()->prepare('SELECT * FROM feriados WHERE estudio_id = ? OR estudio_id IS NULL ORDER BY fecha');
  $st->execute([$u['estudio_id']]);
  json_ok($st->fetchAll());
}

function feriado_crear() {
  $u = require_profesional();
  if (!field('fecha') || !field('nombre')) json_error('Indicá fecha y nombre del feriado.');
  db()->prepare('INSERT INTO feriados (estudio_id, fecha, anual, nombre, tipo) VALUES (?,?,?,?,?)')
      ->execute([$u['estudio_id'], field('fecha'), field('anual') ? 1 : 0, field('nombre'),
                 field('tipo') === 'provincial' ? 'provincial' : 'nacional']);
  json_ok(['id' => (int)db()->lastInsertId()], 201);
}

function feriado_borrar($id) {
  $u = require_profesional();
  // No se pueden borrar los globales (estudio_id NULL) desde un estudio.
  db()->prepare('DELETE FROM feriados WHERE id = ? AND estudio_id = ?')->execute([$id, $u['estudio_id']]);
  json_ok(['borrado' => true]);
}
