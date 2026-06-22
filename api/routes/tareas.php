<?php
/* ============================================================================
 *  TAREAS / PENDIENTES  (/api/tareas/...)
 *    GET    /api/tareas              -> todas las tareas del estudio
 *    GET    /api/tareas?causa=ID     -> tareas de una causa
 *    POST   /api/tareas              -> crear tarea
 *    PUT    /api/tareas/{id}         -> editar / marcar hecha
 *    DELETE /api/tareas/{id}         -> borrar
 * ========================================================================== */

function handle_tareas($method, $resto) {
  $id = isset($resto[0]) ? (int)$resto[0] : 0;
  if ($id) {
    if ($method === 'PUT')    return tarea_editar($id);
    if ($method === 'DELETE') return tarea_borrar($id);
  }
  if ($method === 'GET')  return tareas_listar();
  if ($method === 'POST') return tarea_crear();
  json_error('Método no permitido.', 405);
}

function tareas_listar() {
  $u = require_profesional();
  $causa = (int)($_GET['causa'] ?? 0);
  if ($causa) {
    puede_acceder_causa($causa, false);
    $st = db()->prepare('SELECT * FROM tareas WHERE causa_id = ? ORDER BY hecha ASC, COALESCE(vence, "9999-12-31") ASC, id ASC');
    $st->execute([$causa]);
  } else {
    $st = db()->prepare('SELECT t.*, c.caratula FROM tareas t LEFT JOIN causas c ON c.id = t.causa_id
                         WHERE t.estudio_id = ? ORDER BY t.hecha ASC, COALESCE(t.vence, "9999-12-31") ASC, t.id ASC');
    $st->execute([$u['estudio_id']]);
  }
  json_ok($st->fetchAll());
}

function tarea_crear() {
  $u = require_profesional();
  $texto = trim((string)field('texto'));
  if (!$texto) json_error('La tarea no puede estar vacía.');
  $causa = field('causa_id') ? (int)field('causa_id') : null;
  if ($causa) puede_acceder_causa($causa, false);
  db()->prepare('INSERT INTO tareas (estudio_id, causa_id, texto, vence, asignada_a) VALUES (?,?,?,?,?)')
      ->execute([$u['estudio_id'], $causa, $texto, field('vence'), field('asignada_a') ? (int)field('asignada_a') : null]);
  json_ok(['id' => (int)db()->lastInsertId()], 201);
}

function tarea_editar($id) {
  $u = require_profesional();
  $sets = []; $vals = [];
  foreach (['texto','vence','asignada_a'] as $f) {
    $v = field($f, '__NO__');
    if ($v !== '__NO__') { $sets[] = "$f = ?"; $vals[] = $v; }
  }
  if (field('hecha', '__NO__') !== '__NO__') { $sets[] = 'hecha = ?'; $vals[] = field('hecha') ? 1 : 0; }
  if (!$sets) json_error('No hay nada para actualizar.');
  $vals[] = $id; $vals[] = $u['estudio_id'];
  db()->prepare('UPDATE tareas SET ' . implode(', ', $sets) . ' WHERE id = ? AND estudio_id = ?')->execute($vals);
  json_ok(['actualizada' => true]);
}

function tarea_borrar($id) {
  $u = require_profesional();
  db()->prepare('DELETE FROM tareas WHERE id = ? AND estudio_id = ?')->execute([$id, $u['estudio_id']]);
  json_ok(['borrada' => true]);
}
