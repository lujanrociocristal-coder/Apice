<?php
/* ============================================================================
 *  HONORARIOS  (/api/honorarios/...)
 *    Maneja los GASTOS y los PAGOS de una causa.
 *    GET    /api/honorarios?causa=ID        -> gastos + pagos de la causa
 *    POST   /api/honorarios/gasto           -> agregar gasto
 *    DELETE /api/honorarios/gasto/{id}      -> borrar gasto
 *    PUT    /api/honorarios/gasto/{id}      -> marcar pagado / editar
 *    POST   /api/honorarios/pago            -> registrar un pago
 *    DELETE /api/honorarios/pago/{id}       -> borrar pago
 * ========================================================================== */

function handle_honorarios($method, $resto) {
  $tipo = $resto[0] ?? '';
  $id   = isset($resto[1]) ? (int)$resto[1] : 0;

  if ($tipo === 'gasto') {
    if ($method === 'POST')   return gasto_crear();
    if ($method === 'PUT')    return gasto_editar($id);
    if ($method === 'DELETE') return gasto_borrar($id);
  }
  if ($tipo === 'pago') {
    if ($method === 'POST')   return pago_crear();
    if ($method === 'DELETE') return pago_borrar($id);
  }
  if ($method === 'GET') return honorarios_listar();
  json_error('Método no permitido.', 405);
}

function _causa_de_gasto($id) {
  $st = db()->prepare('SELECT causa_id FROM honorarios_gastos WHERE id = ?');
  $st->execute([$id]); return (int)$st->fetchColumn();
}

function honorarios_listar() {
  $u = require_login();
  $causa = (int)($_GET['causa'] ?? 0);
  puede_acceder_causa($causa, $u['rol'] === 'cliente');
  $g = db()->prepare('SELECT * FROM honorarios_gastos WHERE causa_id = ? ORDER BY id ASC'); $g->execute([$causa]);
  $p = db()->prepare('SELECT * FROM pagos WHERE causa_id = ? ORDER BY fecha ASC');           $p->execute([$causa]);
  json_ok(['gastos' => $g->fetchAll(), 'pagos' => $p->fetchAll()]);
}

function gasto_crear() {
  require_profesional();
  $causa = (int)field('causa_id');
  puede_acceder_causa($causa, false);
  $concepto = trim((string)field('concepto'));
  if (!$concepto) json_error('El gasto necesita un concepto.');
  db()->prepare('INSERT INTO honorarios_gastos (causa_id, concepto, moneda, monto, pagado) VALUES (?,?,?,?,?)')
      ->execute([$causa, $concepto, field('moneda') === 'ius' ? 'ius' : 'ars',
                 (float)field('monto', 0), field('pagado') ? 1 : 0]);
  json_ok(['id' => (int)db()->lastInsertId()], 201);
}

function gasto_editar($id) {
  require_profesional();
  puede_acceder_causa(_causa_de_gasto($id), false);
  $sets = []; $vals = [];
  foreach (['concepto','moneda','monto'] as $f) {
    $v = field($f, '__NO__'); if ($v !== '__NO__') { $sets[] = "$f = ?"; $vals[] = $v; }
  }
  if (field('pagado', '__NO__') !== '__NO__') { $sets[] = 'pagado = ?'; $vals[] = field('pagado') ? 1 : 0; }
  if (!$sets) json_error('No hay nada para actualizar.');
  $vals[] = $id;
  db()->prepare('UPDATE honorarios_gastos SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
  json_ok(['actualizado' => true]);
}

function gasto_borrar($id) {
  require_profesional();
  puede_acceder_causa(_causa_de_gasto($id), false);
  db()->prepare('DELETE FROM honorarios_gastos WHERE id = ?')->execute([$id]);
  json_ok(['borrado' => true]);
}

function pago_crear() {
  $u = require_profesional();
  $causa = field('causa_id') ? (int)field('causa_id') : null;
  if ($causa) puede_acceder_causa($causa, false);
  db()->prepare('INSERT INTO pagos (estudio_id, causa_id, cliente_id, fecha, concepto, moneda, monto)
                 VALUES (?,?,?,?,?,?,?)')
      ->execute([$u['estudio_id'], $causa, field('cliente_id') ? (int)field('cliente_id') : null,
                 field('fecha') ?: date('Y-m-d'), field('concepto'),
                 field('moneda') === 'ius' ? 'ius' : 'ars', (float)field('monto', 0)]);
  json_ok(['id' => (int)db()->lastInsertId()], 201);
}

function pago_borrar($id) {
  $u = require_profesional();
  db()->prepare('DELETE FROM pagos WHERE id = ? AND estudio_id = ?')->execute([$id, $u['estudio_id']]);
  json_ok(['borrado' => true]);
}
