<?php
/* ============================================================================
 *  AUTENTICACIÓN  (/api/auth/...)
 *    POST /api/auth/login      -> entrar (email + contraseña)
 *    POST /api/auth/logout     -> salir
 *    GET  /api/auth/me         -> ¿quién soy? (datos de la sesión)
 *    POST /api/auth/register   -> crear cuenta de profesional (abre estudio)
 *    POST /api/auth/aceptar    -> registrar aceptación de términos/privacidad
 * ========================================================================== */

function handle_auth($method, $resto) {
  $accion = $resto[0] ?? '';

  if ($accion === 'login' && $method === 'POST') {
    $email = strtolower(trim((string)field('email')));
    $pass  = (string)field('password');
    if (!$email || !$pass) json_error('Ingresá email y contraseña.');

    $st = db()->prepare('SELECT * FROM usuarios WHERE email = ? AND activo = 1');
    $st->execute([$email]);
    $u = $st->fetch();
    if (!$u || !check_password($pass, $u['password_hash'])) {
      json_error('Email o contraseña incorrectos.', 401);
    }
    // Guardar sesión
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    db()->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')->execute([$u['id']]);

    json_ok([
      'id' => (int)$u['id'], 'nombre' => $u['nombre'], 'email' => $u['email'],
      'rol' => $u['rol'], 'estudio_id' => (int)$u['estudio_id'],
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
    ]);
  }

  if ($accion === 'register' && $method === 'POST') {
    // Crea un estudio nuevo + la primera profesional de ese estudio.
    $nombre  = trim((string)field('nombre'));
    $email   = strtolower(trim((string)field('email')));
    $pass    = (string)field('password');
    $estudio = trim((string)field('estudio')) ?: ('Estudio de ' . $nombre);
    if (!$nombre || !$email || strlen($pass) < 6) {
      json_error('Completá nombre, email y una contraseña de al menos 6 caracteres.');
    }
    $chk = db()->prepare('SELECT 1 FROM usuarios WHERE email = ?');
    $chk->execute([$email]);
    if ($chk->fetch()) json_error('Ya existe una cuenta con ese email.');

    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO estudios (nombre) VALUES (?)')->execute([$estudio]);
    $estudioId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO usuarios (estudio_id, nombre, email, password_hash, rol) VALUES (?,?,?,?,?)')
        ->execute([$estudioId, $nombre, $email, hash_password($pass), 'profesional']);
    $uid = (int)$pdo->lastInsertId();
    $pdo->commit();

    session_regenerate_id(true);
    $_SESSION['uid'] = $uid;
    json_ok(['id' => $uid, 'estudio_id' => $estudioId, 'nombre' => $nombre, 'rol' => 'profesional'], 201);
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

  json_error('Acción de auth no válida.', 404);
}
