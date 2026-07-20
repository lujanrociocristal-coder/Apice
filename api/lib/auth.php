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
    'domain'   => '',
    'secure'   => !empty($c['cookie_secure']),
    'httponly' => true,
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

/* Genera una clave temporal fácil de leer (para blanqueos). */
function generar_clave_temporal() {
  // Sin caracteres confusos (0/O, 1/l). Ej: "Apice-7K4P".
  $letras = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
  $nums   = '23456789';
  $s = '';
  for ($i = 0; $i < 4; $i++) $s .= $letras[random_int(0, strlen($letras) - 1)];
  for ($i = 0; $i < 2; $i++) $s .= $nums[random_int(0, strlen($nums) - 1)];
  return 'Apice-' . $s;
}

/* Devuelve la usuaria logueada (arreglo) o null si no hay sesión. */
function current_user() {
  static $u = null;
  if ($u !== null) return $u ?: null;
  if (empty($_SESSION['uid'])) { $u = false; return null; }
  $st = db()->prepare('SELECT u.id, u.estudio_id, u.nombre, u.email, u.rol, u.matricula, u.avatar_url,
                              u.es_admin, u.es_superadmin, u.debe_cambiar_clave, u.activo,
                              e.tipo AS estudio_tipo
                       FROM usuarios u JOIN estudios e ON e.id = u.estudio_id
                       WHERE u.id = ? AND u.activo = 1');
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

/* Exige que sea la ADMINISTRADORA del estudio (lead de la firma). */
function require_admin() {
  $u = require_login();
  if ($u['rol'] !== 'profesional' || (int)$u['es_admin'] !== 1) {
    json_error('Solo la administradora del estudio puede hacer esta acción.', 403);
  }
  return $u;
}

/* Exige ser la SUPER-ADMINISTRADORA de la plataforma (dueña: decide qué
 * estudios/abogados acceden). Solo ella puede crear estudios nuevos. */
function require_superadmin() {
  $u = require_login();
  if ((int)$u['es_superadmin'] !== 1) {
    json_error('Solo la super-administradora de la plataforma puede hacer esta acción.', 403);
  }
  return $u;
}

/* El estudio de la usuaria actual (todas las consultas se filtran por esto). */
function estudio_actual() {
  $u = require_login();
  return (int)$u['estudio_id'];
}

/* ¿La usuaria puede ver/editar esta causa? */
function puede_acceder_causa($causaId, $soloLectura = false) {
  $u = require_login();
  /* Doble candado (v46): el filtro por estudio va en la consulta MISMA y
     ademas se verifica en PHP. Defensa en profundidad. */
  $st = db()->prepare('SELECT id, estudio_id, owner_id, cliente_id FROM causas WHERE id = ? AND estudio_id = ?');
  $st->execute([$causaId, (int)$u['estudio_id']]);
  $c = $st->fetch();
  if (!$c) json_error('La causa no existe.', 404);
  if ((int)$c['estudio_id'] !== (int)$u['estudio_id']) json_error('No tenés acceso a esta causa.', 403);

  if ($u['rol'] === 'cliente') {
    if (!$soloLectura) json_error('Los clientes tienen acceso de solo lectura.', 403);
    $cli = db()->prepare('SELECT 1 FROM clientes WHERE id = ? AND usuario_id = ?');
    $cli->execute([$c['cliente_id'], $u['id']]);
    if (!$cli->fetch()) json_error('No tenés acceso a esta causa.', 403);
    return $c;
  }

  if ((int)$c['owner_id'] === (int)$u['id']) return $c;
  $col = db()->prepare('SELECT permiso FROM causa_colaboradores WHERE causa_id = ? AND usuario_id = ?');
  $col->execute([$causaId, $u['id']]);
  $perm = $col->fetchColumn();
  if (!$perm) json_error('Esta causa no está compartida con vos.', 403);
  if ($perm === 'lectura' && !$soloLectura) json_error('Solo podés leer esta causa (sin edición).', 403);
  return $c;
}
