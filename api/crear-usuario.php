<?php
/* ============================================================================
 *  CREAR EL PRIMER USUARIO (página de un solo uso)
 *
 *  PARA QUÉ SIRVE:
 *    Crear el estudio inicial y la primera profesional (Breppe), eligiendo VOS
 *    el email y la contraseña. La contraseña se guarda cifrada.
 *
 *  CÓMO SE USA (ver GUIA-HOSTINGER.md, paso "Crear el primer usuario"):
 *    1) Subí este archivo dentro de /public_html/api/
 *    2) Abrí en el navegador: https://abogadoscatamarca.com/api/crear-usuario.php
 *    3) Completá el formulario y enviá.
 *    4) MUY IMPORTANTE: cuando termine, BORRÁ este archivo del servidor
 *       (desde el Administrador de archivos de Hostinger). Es por seguridad.
 *
 *  Medida de protección: si ya existe al menos un usuario, esta página se
 *  bloquea sola y no deja crear más por acá.
 * ========================================================================== */

require __DIR__ . '/lib/respond.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/auth.php';

$ya = (int) db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
$mensaje = '';
$hecho = false;

if ($ya > 0) {
  $mensaje = 'Ya existe al menos un usuario. Por seguridad, esta página está deshabilitada. '
           . 'Borrá este archivo (crear-usuario.php) del servidor.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $estudio = trim($_POST['estudio'] ?? '');
  $nombre  = trim($_POST['nombre'] ?? '');
  $email   = strtolower(trim($_POST['email'] ?? ''));
  $pass    = $_POST['password'] ?? '';
  $pass2   = $_POST['password2'] ?? '';

  if (!$estudio || !$nombre || !$email || strlen($pass) < 8) {
    $mensaje = 'Completá todo. La contraseña debe tener al menos 8 caracteres.';
  } elseif ($pass !== $pass2) {
    $mensaje = 'Las dos contraseñas no coinciden.';
  } else {
    $pdo = db(); $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO estudios (nombre) VALUES (?)')->execute([$estudio]);
    $eid = (int)$pdo->lastInsertId();
    // La PRIMERA usuaria (vos) queda como SUPER-ADMINISTRADORA de la plataforma
    // (es_superadmin = 1) y además administradora de su propio estudio (es_admin = 1).
    $pdo->prepare('INSERT INTO usuarios (estudio_id, nombre, email, password_hash, rol, es_admin, es_superadmin) VALUES (?,?,?,?,?,1,1)')
        ->execute([$eid, $nombre, $email, hash_password($pass), 'profesional']);
    $uid = (int)$pdo->lastInsertId();
    // Feriados y guía judicial globales -> también para este estudio (copia inicial).
    $pdo->prepare('INSERT INTO feriados (estudio_id, fecha, anual, nombre, tipo)
                   SELECT ?, fecha, anual, nombre, tipo FROM feriados WHERE estudio_id IS NULL')
        ->execute([$eid]);
    $pdo->commit();
    $hecho = true;
    $mensaje = 'Listo. Se creó el estudio "' . htmlspecialchars($estudio) . '" y tu usuaria. '
             . 'Ahora BORRÁ este archivo del servidor y entrá a ÁPICE con tu email y contraseña.';
  }
}
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ÁPICE · Crear primer usuario</title>
<style>
  body{font-family:system-ui,sans-serif;background:#EEF1F5;color:#1C2433;margin:0;padding:40px 16px}
  .card{max-width:440px;margin:0 auto;background:#fff;border:1px solid #E5E7EB;border-radius:14px;padding:28px}
  h1{font-size:20px;margin:0 0 6px} p.sub{color:#6B7280;font-size:13px;margin:0 0 18px}
  label{display:block;font-size:13px;font-weight:600;margin:12px 0 5px}
  input{width:100%;padding:11px 12px;border:1px solid #D3D7DE;border-radius:9px;font-size:14px;box-sizing:border-box}
  button{margin-top:18px;width:100%;background:#1C2433;color:#fff;border:0;padding:12px;border-radius:9px;font-size:15px;font-weight:600;cursor:pointer}
  .msg{margin-top:16px;padding:12px 14px;border-radius:9px;font-size:13px}
  .ok{background:#E7F1EA;color:#14532D} .err{background:#FBEBEB;color:#8a2828}
</style></head><body>
<div class="card">
  <h1>Crear el primer usuario de ÁPICE</h1>
  <p class="sub">Esta página sirve una sola vez. Cuando termines, borrá el archivo del servidor.</p>
  <?php if ($mensaje): ?>
    <div class="msg <?= $hecho ? 'ok' : 'err' ?>"><?= htmlspecialchars($mensaje) ?></div>
  <?php endif; ?>
  <?php if (!$hecho && $ya === 0): ?>
  <form method="post">
    <label>Nombre del estudio</label>
    <input name="estudio" value="Estudio Luján & Breppe" required>
    <label>Tu nombre completo</label>
    <input name="nombre" value="Rocio Cristal Lujan" required>
    <label>Email (para iniciar sesión)</label>
    <input name="email" type="email" value="lujanrociocristal@gmail.com" placeholder="tu@email.com" required>
    <label>Contraseña (mínimo 8 caracteres)</label>
    <input name="password" type="password" required>
    <label>Repetir contraseña</label>
    <input name="password2" type="password" required>
    <button type="submit">Crear usuario</button>
  </form>
  <?php endif; ?>
</div>
</body></html>
