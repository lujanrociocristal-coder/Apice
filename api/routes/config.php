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
  /* Correo de salida, para "olvidé mi contraseña" (v46). Solo la administradora. */
  if ($sub === 'correo') {
    if ($method === 'GET')  return correo_ver();
    if ($method === 'PUT')  return correo_guardar();
    if ($method === 'POST') return correo_probar();
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

/* ===========================================================================
 *  CORREO DE SALIDA  (v46)
 *
 *  Para que "olvide mi contrasena" pueda enviar el enlace, hace falta una
 *  casilla del dominio. Estos datos se cargan DESDE LA APP (Configuracion),
 *  asi no hay que editar archivos en el servidor.
 *
 *  La contrasena se guarda en la base y NUNCA se devuelve al navegador:
 *  solo se informa si esta cargada o no.
 * ========================================================================== */
function correo_tabla() {
  db()->exec("CREATE TABLE IF NOT EXISTS ajustes (
    clave VARCHAR(60) NOT NULL,
    valor TEXT NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (clave)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function ajuste_leer($clave) {
  correo_tabla();
  $st = db()->prepare('SELECT valor FROM ajustes WHERE clave = ?');
  $st->execute([$clave]);
  $v = $st->fetchColumn();
  return $v === false ? null : $v;
}
function ajuste_guardar($clave, $valor) {
  correo_tabla();
  db()->prepare('INSERT INTO ajustes (clave, valor) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor), actualizado_en = NOW()')
      ->execute([$clave, $valor]);
}

function correo_ver() {
  require_admin();
  $pass = ajuste_leer('smtp_pass');
  json_ok([
    'host' => ajuste_leer('smtp_host') ?: 'smtp.hostinger.com',
    'port' => ajuste_leer('smtp_port') ?: '465',
    'user' => ajuste_leer('smtp_user') ?: '',
    'tiene_clave' => !empty($pass),
  ]);
}

function correo_guardar() {
  require_admin();
  $host = trim((string)field('host'));
  $port = (int)field('port');
  $user = trim((string)field('user'));
  $pass = (string)field('pass');
  if ($host === '' || $user === '') json_error('Completá el servidor y la casilla.');
  if ($port <= 0) $port = 465;
  ajuste_guardar('smtp_host', $host);
  ajuste_guardar('smtp_port', (string)$port);
  ajuste_guardar('smtp_user', $user);
  /* Si el campo de contrasena viene vacio, se conserva la que ya estaba. */
  if ($pass !== '') ajuste_guardar('smtp_pass', $pass);
  json_ok(['guardado' => true]);
}

/* Manda un correo de prueba a la persona que esta usando la app. */
function correo_probar() {
  $u = require_admin();
  require_once __DIR__ . '/../lib/smtp.php';
  $destino = trim((string)field('email')) ?: $u['email'];
  if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) json_error('El correo de destino no es válido.');
  $cuerpo = '<div style="font-family:system-ui,Arial,sans-serif;color:#1C2433;font-size:15px">'
    . '<p style="font-size:20px;font-weight:700;margin:0 0 4px">ÁPICE</p>'
    . '<p>Este es un correo de prueba. Si lo estás leyendo, el envío quedó funcionando '
    . 'y la recuperación de contraseña ya opera.</p></div>';
  $ok = smtp_enviar($destino, 'ÁPICE - Correo de prueba', $cuerpo);
  if (!$ok) json_error('No se pudo enviar. Revisá la casilla, la contraseña y el puerto (probá 465 o 587).', 500);
  json_ok(['enviado' => true, 'a' => $destino]);
}
