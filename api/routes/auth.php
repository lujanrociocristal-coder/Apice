<?php
/* ============================================================================
 *  AUTENTICACIÓN  (/api/auth/...)
 *    POST /api/auth/login         -> entrar (email + contraseña)
 *    POST /api/auth/logout        -> salir
 *    GET  /api/auth/me            -> ¿quién soy? (datos de la sesión)
 *    POST /api/auth/register      -> crear cuenta de profesional (abre estudio)
 *    POST /api/auth/aceptar       -> registrar aceptación de términos/privacidad
 *    POST /api/auth/cambiar-clave -> cambiar mi propia contraseña
 * ========================================================================== */

function handle_auth($method, $resto) {
  $accion = $resto[0] ?? '';

  if ($accion === 'login' && $method === 'POST') {
    $email = strtolower(trim((string)field('email')));
    $pass  = (string)field('password');
    if (!$email || !$pass) json_error('Ingresá email y contraseña.');

    $st = db()->prepare('SELECT u.*, e.tipo AS estudio_tipo
                         FROM usuarios u JOIN estudios e ON e.id = u.estudio_id
                         WHERE u.email = ? AND u.activo = 1');
    $st->execute([$email]);
    $u = $st->fetch();
    if (!$u || !check_password($pass, $u['password_hash'])) {
      json_error('Email o contraseña incorrectos.', 401);
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    db()->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')->execute([$u['id']]);

    json_ok([
      'id' => (int)$u['id'], 'nombre' => $u['nombre'], 'email' => $u['email'],
      'rol' => $u['rol'], 'estudio_id' => (int)$u['estudio_id'],
      'es_admin' => (int)$u['es_admin'],
      'es_superadmin' => (int)$u['es_superadmin'],
      'estudio_tipo' => $u['estudio_tipo'],
      'debe_cambiar_clave' => (int)$u['debe_cambiar_clave'],
    ]);
  }

  if ($accion === 'logout' && $method === 'POST') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $p = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    json_ok(['salio' => true]);
  }

  if ($accion === 'me' && $method === 'GET') {
    $u = current_user();
    if (!$u) json_ok(['logueada' => false]);
    json_ok([
      'logueada' => true,
      'id' => (int)$u['id'], 'nombre' => $u['nombre'], 'email' => $u['email'],
      'rol' => $u['rol'], 'estudio_id' => (int)$u['estudio_id'], 'avatar' => $u['avatar_url'],
      'es_admin' => (int)$u['es_admin'],
      'es_superadmin' => (int)$u['es_superadmin'],
      'estudio_tipo' => $u['estudio_tipo'],
      'debe_cambiar_clave' => (int)$u['debe_cambiar_clave'],
    ]);
  }

  if ($accion === 'register' && $method === 'POST') {
    // REGISTRO PÚBLICO DESHABILITADO. ÁPICE no es de acceso público:
    // solo la super-administradora crea los estudios y sus abogadas.
    json_error('El registro abierto está deshabilitado. Pedile el acceso a la administradora de ÁPICE.', 403);
  }

  if ($accion === 'aceptar' && $method === 'POST') {
    $u = require_login();
    $perfil = field('perfil') === 'cliente' ? 'cliente' : 'abogado';
    $docs   = field('documentos', ['terminos', 'privacidad', 'cookies']);
    $metodo = (string)field('metodo', 'login');
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ins = db()->prepare('INSERT INTO consentimientos (usuario_id, perfil, documento, version, metodo, ip) VALUES (?,?,?,?,?,?)');
    foreach ((array)$docs as $d) $ins->execute([$u['id'], $perfil, $d, 'v1', $metodo, $ip]);
    json_ok(['aceptado' => true]);
  }

  if ($accion === 'cambiar-clave' && $method === 'POST') {
    $u = require_login();
    $actual = (string)field('actual');
    $nueva  = (string)field('nueva');
    if (strlen($nueva) < 6) json_error('La nueva contraseña debe tener al menos 6 caracteres.');

    // Traer el hash actual.
    $st = db()->prepare('SELECT password_hash FROM usuarios WHERE id = ?');
    $st->execute([$u['id']]);
    $hash = $st->fetchColumn();
    if (!check_password($actual, $hash)) json_error('La contraseña actual no es correcta.', 403);

    db()->prepare('UPDATE usuarios SET password_hash = ?, debe_cambiar_clave = 0 WHERE id = ?')
        ->execute([hash_password($nueva), $u['id']]);
    json_ok(['cambiada' => true]);
  }

  json_error('Acción de auth no válida.', 404);
}
