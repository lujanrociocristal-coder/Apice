<?php
/* ============================================================================
 *  AUDIENCIAS y CITAS  (/api/audiencias/...)
 *    GET    /api/audiencias            -> agenda del estudio (o del cliente)
 *    POST   /api/audiencias            -> crear audiencia o cita
 *    PUT    /api/audiencias/{id}       -> editar
 *    DELETE /api/audiencias/{id}       -> borrar
 *
 *  tipo = 'juzgado' | 'mediacion'  -> audiencias
 *  tipo = 'cita'                   -> cita con cliente (presencial/virtual)
 * ========================================================================== */

function handle_audiencias($method, $resto) {
  $id = isset($resto[0]) ? (int)$resto[0] : 0;
  if ($id) {
    if ($method === 'PUT')    return aud_editar($id);
    if ($method === 'DELETE') return aud_borrar($id);
  }
  if ($method === 'GET')  return aud_listar();
  if ($method === 'POST') return aud_crear();
  json_error('Método no permitido.', 405);
}

function aud_listar() {
  $u = require_login();
  if ($u['rol'] === 'cliente') {
    // El cliente ve solo su propia agenda: citas con él y audiencias de sus causas.
    $sql = 'SELECT a.* FROM audiencias a
            LEFT JOIN causas c   ON c.id = a.causa_id
            LEFT JOIN clientes cl ON cl.id = c.cliente_id
            WHERE a.estudio_id = ? AND ( (a.cliente_id IS NOT NULL AND a.cliente_id IN
                  (SELECT id FROM clientes WHERE usuario_id = ?)) OR cl.usuario_id = ? )
            ORDER BY a.fecha ASC, a.hora ASC';
    $st = db()->prepare($sql);
    $st->execute([$u['estudio_id'], $u['id'], $u['id']]);
  } else {
    $st = db()->prepare('SELECT a.*, c.caratula FROM audiencias a LEFT JOIN causas c ON c.id = a.causa_id
                         WHERE a.estudio_id = ? ORDER BY a.fecha ASC, a.hora ASC');
    $st->execute([$u['estudio_id']]);
  }
  json_ok($st->fetchAll());
}

function _aud_campos() {
  return ['tipo','causa_id','fecha','hora','detalle','cliente_nombre','cliente_id',
          'materia','cli_asiste','modalidad','lugar','link'];
}

function aud_crear() {
  $u = require_profesional();
  $tipo = field('tipo');
  if (!in_array($tipo, ['juzgado','mediacion','cita'], true)) json_error('Tipo de agenda no válido.');
  if (!field('fecha')) json_error('La fecha es obligatoria.');
  if ($tipo === 'juzgado' && field('causa_id')) puede_acceder_causa((int)field('causa_id'), false);

  $cols = ['estudio_id']; $vals = [$u['estudio_id']]; $ph = ['?'];
  foreach (_aud_campos() as $f) {
    $v = field($f);
    if ($f === 'cli_asiste') $v = $v ? 1 : 0;
    if ($f === 'causa_id')   $v = $v ? (int)$v : null;
    if ($f === 'cliente_id') $v = $v ? (int)$v : null;
    $cols[] = $f; $vals[] = $v; $ph[] = '?';
  }
  db()->prepare('INSERT INTO audiencias (' . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')')->execute($vals);
  json_ok(['id' => (int)db()->lastInsertId()], 201);
}

function aud_editar($id) {
  $u = require_profesional();
  $sets = []; $vals = [];
  foreach (_aud_campos() as $f) {
    $v = field($f, '__NO__');
    if ($v === '__NO__') continue;
    if ($f === 'cli_asiste') $v = $v ? 1 : 0;
    if ($f === 'causa_id')   $v = $v ? (int)$v : null;
    if ($f === 'cliente_id') $v = $v ? (int)$v : null;
    $sets[] = "$f = ?"; $vals[] = $v;
  }
  if (!$sets) json_error('No hay nada para actualizar.');
  $vals[] = $id; $vals[] = $u['estudio_id'];
  db()->prepare('UPDATE audiencias SET ' . implode(', ', $sets) . ' WHERE id = ? AND estudio_id = ?')->execute($vals);
  json_ok(['actualizada' => true]);
}

function aud_borrar($id) {
  $u = require_profesional();
  db()->prepare('DELETE FROM audiencias WHERE id = ? AND estudio_id = ?')->execute([$id, $u['estudio_id']]);
  json_ok(['borrada' => true]);
}
