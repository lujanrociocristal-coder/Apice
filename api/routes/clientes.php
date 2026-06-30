<?php
/* ============================================================================
 *  CLIENTES  (/api/clientes/...)
 *    GET    /api/clientes          -> lista de clientes del estudio
 *    GET    /api/clientes/{id}     -> un cliente (con sus causas)
 *    POST   /api/clientes          -> crear cliente
 *    PUT    /api/clientes/{id}     -> editar cliente
 *    DELETE /api/clientes/{id}     -> borrar cliente
 *    POST   /api/clientes/{id}/acceso -> darle acceso al portal (crea usuario rol=cliente)
 * ========================================================================== */

function handle_clientes($method, $resto) {
  $id  = isset($resto[0]) ? (int)$resto[0] : 0;
  $sub = $resto[1] ?? '';

  if ($id && $sub === 'acceso' && $method === 'POST') return cliente_dar_acceso($id);

  if ($id) {
    if ($method === 'GET')    return cliente_detalle($id);
    if ($method === 'PUT')    return cliente_editar($id);
    if ($method === 'DELETE') return cliente_borrar($id);
  }
  if ($method === 'GET')  return clientes_listar();
  if ($method === 'POST') return cliente_crear();
  json_error('Método no permitido.', 405);
}

function clientes_listar() {
  $u = require_profesional();
  $st = db()->prepare('SELECT * FROM clientes WHERE estudio_id = ? ORDER BY nombre ASC');
  $st->execute([$u['estudio_id']]);
  json_ok($st->fetchAll());
}

function cliente_detalle($id) {
  $u = require_profesional();
  $st = db()->prepare('SELECT * FROM clientes WHERE id = ? AND estudio_id = ?');
  $st->execute([$id, $u['estudio_id']]);
  $cli = $st->fetch();
  if (!$cli) json_error('Cliente no encontrado.', 404);
  $cz = db()->prepare('SELECT id, caratula, estado, expediente FROM causas WHERE cliente_id = ? AND estudio_id = ?');
  $cz->execute([$id, $u['estudio_id']]);
  $cli['causas'] = $cz->fetchAll();
  json_ok($cli);
}

function _cliente_campos() { return ['nombre','tipo','dni_cuit','email','telefono','domicilio','notas']; }

function cliente_crear() {
  $u = require_profesional();
  $nombre = trim((string)field('nombre'));
  if (!$nombre) json_error('El nombre del cliente es obligatorio.');
  $cols = ['estudio_id']; $vals = [$u['estudio_id']]; $ph = ['?'];
  foreach (_cliente_campos() as $f) { $cols[] = $f; $vals[] = field($f); $ph[] = '?'; }
  db()->prepare('INSERT INTO clientes (' . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')')->execute($vals);
  json_ok(['id' => (int)db()->lastInsertId()], 201);
}

function cliente_editar($id) {
  $u = require_profesional();
  $sets = []; $vals = [];
  foreach (_cliente_campos() as $f) {
    $v = field($f, '__NO__');
    if ($v !== '__NO__') { $sets[] = "$f = ?"; $vals[] = $v; }
  }
  if (!$sets) json_error('No hay nada para actualizar.');
  $vals[] = $id; $vals[] = $u['estudio_id'];
  db()->prepare('UPDATE clientes SET ' . implode(', ', $sets) . ' WHERE id = ? AND estudio_id = ?')->execute($vals);
  json_ok(['actualizado' => true]);
}

function cliente_borrar($id) {
  $u = require_profesional();
  db()->prepare('DELETE FROM clientes WHERE id = ? AND estudio_id = ?')->execute([$id, $u['estudio_id']]);
  json_ok(['borrado' => true]);
}

/* Crea un usuario de portal (rol=cliente) y lo enlaza al cliente. */
function cliente_dar_acceso($id) {
  $u = require_profesional();
  $email = strtolower(trim((string)field('email')));
  $pass  = (string)field('password');
  if (!$email || strlen($pass) < 6) json_error('Indicá email y una contraseña de al menos 6 caracteres.');
  $cli = db()->prepare('SELECT * FROM clientes WHERE id = ? AND estudio_id = ?');
  $cli->execute([$id, $u['estudio_id']]);
  $c = $cli->fetch();
  if (!$c) json_error('Cliente no encontrado.', 404);
  $chk = db()->prepare('SELECT 1 FROM usuarios WHERE email = ?');
  $chk->execute([$email]);
  if ($chk->fetch()) json_error('Ya existe un usuario con ese email.');

  $pdo = db(); $pdo->beginTransaction();
  // El cliente entra con una clave inicial y debe cambiarla en el primer ingreso.
  $pdo->prepare('INSERT INTO usuarios (estudio_id, nombre, email, password_hash, rol, debe_cambiar_clave) VALUES (?,?,?,?,?,1)')
      ->execute([$u['estudio_id'], $c['nombre'], $email, hash_password($pass), 'cliente']);
  $uid = (int)$pdo->lastInsertId();
  $pdo->prepare('UPDATE clientes SET usuario_id = ?, email = ? WHERE id = ?')->execute([$uid, $email, $id]);
  $pdo->commit();
  json_ok(['usuario_id' => $uid], 201);
}
