<?php
/* ============================================================================
 *  SEGURIDAD Y SESIONES
 *  - Inicia la sesión de forma segura.
 *  - Funciones para saber quién está logueada y qué puede hacer.
 *  - Cifrado de contraseñas (bcrypt) y verificación.
 * ========================================================================== */

/* Arranca la sesión con cookies seguras (HTTPS). */
function start_secure_session() {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  $c = cfg();
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',                       // mismo dominio
    'secure'   => !empty($c['cookie_secure']),
    'httponly' => true,                     // la cookie no es accesible por JavaScript
    'samesite' => 'Lax',
  ]);
  session_name('APICE_SES');
  session_start();
}

/* Cifra una contraseña para guardarla (nunca se guarda en texto plano). */
function hash_password($plano) {
  return password_hash($plano, PASSWORD_BCRYPT);
}

/* Verifica una contraseña tecleada contra el hash guardado. */
function check_password($plano, $hash) {
  return $hash && password_verify($plano, $hash);
}

/* Devuelve la usuaria logueada (arreglo) o null si no hay sesión. */
function current_user() {
  static $u = null;
  if ($u !== null) return $u ?: null;
  if (empty($_SESSION['uid'])) { $u = false; return null; }
  $st = db()->prepare('SELECT id, estudio_id, nombre, email, rol, matricula, avatar_url FROM usuarios WHERE id = ? AND activo = 1');
  $st->execute([$_SESSION['uid']]);
  $u = $st->fetch() ?: false;
  return $u ?: null;
}

/* Exige sesión iniciada; si no, corta con error 401. */
function require_login() {
  $u = current_user();
  if (!$u) json_error('Tenés que iniciar sesión.', 401);
  return $u;
}

/* Exige que sea profesional (no cliente). */
function require_profesional() {
  $u = require_login();
  if ($u['rol'] !== 'profesional') json_error('Acción permitida solo para profesionales.', 403);
  return $u;
}

/* El estudio de la usuaria actual (todas las consultas se filtran por esto). */
function estudio_actual() {
  $u = require_login();
  return (int)$u['estudio_id'];
}

/* ¿La usuaria puede ver/editar esta causa?
 * Reglas: misma firma + (es la dueña  O  está como colaboradora  O
 *         es el cliente de la causa, en modo lectura). */
function puede_acceder_causa($causaId, $soloLectura = false) {
  $u = require_login();
  $st = db()->prepare('SELECT id, estudio_id, owner_id, cliente_id FROM causas WHERE id = ?');
  $st->execute([$causaId]);
  $c = $st->fetch();
  if (!$c) json_error('La causa no existe.', 404);
  if ((int)$c['estudio_id'] !== (int)$u['estudio_id']) json_error('No tenés acceso a esta causa.', 403);

  if ($u['rol'] === 'cliente') {
    // El cliente solo puede LEER sus propias causas.
    if (!$soloLectura) json_error('Los clientes tienen acceso de solo lectura.', 403);
    $cli = db()->prepare('SELECT 1 FROM clientes WHERE id = ? AND usuario_id = ?');
    $cli->execute([$c['cliente_id'], $u['id']]);
    if (!$cli->fetch()) json_error('No tenés acceso a esta causa.', 403);
    return $c;
  }

  // Profesional: dueña o colaboradora.
  if ((int)$c['owner_id'] === (int)$u['id']) return $c;
  $col = db()->prepare('SELECT permiso FROM causa_colaboradores WHERE causa_id = ? AND usuario_id = ?');
  $col->execute([$causaId, $u['id']]);
  $perm = $col->fetchColumn();
  if (!$perm) json_error('Esta causa no está compartida con vos.', 403);
  if ($perm === 'lectura' && !$soloLectura) json_error('Solo podés leer esta causa (sin edición).', 403);
  return $c;
}
