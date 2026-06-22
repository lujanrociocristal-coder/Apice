<?php
/* ============================================================================
 *  RECIBOS  (/api/recibos/...)
 *    GET    /api/recibos            -> lista de recibos del estudio
 *    GET    /api/recibos/{id}       -> un recibo
 *    POST   /api/recibos            -> emitir recibo (asigna número correlativo)
 *
 *  La numeración es CORRELATIVA por estudio: el número sale de
 *  estudios.recibo_seq y se incrementa de forma segura (sin saltos ni repetidos)
 *  aunque dos personas emitan al mismo tiempo.
 * ========================================================================== */

function handle_recibos($method, $resto) {
  $id = isset($resto[0]) ? (int)$resto[0] : 0;
  if ($id && $method === 'GET') return recibo_detalle($id);
  if ($method === 'GET')  return recibos_listar();
  if ($method === 'POST') return recibo_emitir();
  json_error('Método no permitido.', 405);
}

function recibos_listar() {
  $u = require_profesional();
  $st = db()->prepare('SELECT * FROM recibos WHERE estudio_id = ? ORDER BY numero DESC');
  $st->execute([$u['estudio_id']]);
  json_ok($st->fetchAll());
}

function recibo_detalle($id) {
  $u = require_profesional();
  $st = db()->prepare('SELECT * FROM recibos WHERE id = ? AND estudio_id = ?');
  $st->execute([$id, $u['estudio_id']]);
  $r = $st->fetch();
  if (!$r) json_error('Recibo no encontrado.', 404);
  json_ok($r);
}

function recibo_emitir() {
  $u = require_profesional();
  $pdo = db();
  $pdo->beginTransaction();
  try {
    // Bloquea la fila del estudio para leer y subir el contador sin choques.
    $st = $pdo->prepare('SELECT recibo_seq FROM estudios WHERE id = ? FOR UPDATE');
    $st->execute([$u['estudio_id']]);
    $numero = (int)$st->fetchColumn();
    if ($numero < 1) $numero = 1;

    $ins = $pdo->prepare('INSERT INTO recibos
      (estudio_id, numero, causa_id, cliente_nombre, fecha, concepto, moneda, monto, monto_en_letras, emitido_por)
      VALUES (?,?,?,?,?,?,?,?,?,?)');
    $ins->execute([
      $u['estudio_id'], $numero,
      field('causa_id') ? (int)field('causa_id') : null,
      field('cliente_nombre'),
      field('fecha') ?: date('Y-m-d'),
      field('concepto'),
      field('moneda') === 'ius' ? 'ius' : 'ars',
      (float)field('monto', 0),
      field('monto_en_letras'),
      $u['id'],
    ]);
    $reciboId = (int)$pdo->lastInsertId();

    // Sube el contador para el próximo.
    $pdo->prepare('UPDATE estudios SET recibo_seq = ? WHERE id = ?')->execute([$numero + 1, $u['estudio_id']]);
    $pdo->commit();

    json_ok(['id' => $reciboId, 'numero' => $numero], 201);
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}
