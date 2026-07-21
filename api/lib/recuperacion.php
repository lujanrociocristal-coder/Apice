<?php
/* ============================================================================
 *  RECUPERAR LA CONTRASENA POR CORREO  (v46)
 *
 *  Como funciona:
 *   1) La persona pide recuperar poniendo su email.
 *   2) Si ese email existe y esta activo, se le manda un enlace con una clave
 *      de un solo uso que vence en 1 hora.
 *   3) Con ese enlace elige una contrasena nueva.
 *
 *  DECISIONES DE SEGURIDAD (a proposito):
 *   - La respuesta es SIEMPRE la misma, exista o no el email. Asi nadie puede
 *     usar esta pantalla para averiguar quien tiene cuenta.
 *   - En la base NO se guarda el codigo, sino su huella (hash). Si alguien
 *     leyera la tabla, no podria usar los enlaces.
 *   - Un solo uso y vencimiento de 1 hora.
 *   - Al cambiar la clave se invalidan los demas pedidos de esa persona.
 *   - Maximo 3 pedidos por hora por email, para que no sirva de molestia.
 * ========================================================================== */

function recup_tabla($pdo) {
  $pdo->exec("CREATE TABLE IF NOT EXISTS recuperacion_clave (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_en DATETIME NOT NULL,
    usado TINYINT(1) NOT NULL DEFAULT 0,
    ip VARCHAR(45) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_token (token_hash),
    KEY idx_usuario (usuario_id, usado)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function recup_dominio() {
  $cfg = @include __DIR__ . '/../config.php';
  $d = (is_array($cfg) && !empty($cfg['app_domain'])) ? $cfg['app_domain'] : 'abogadoscatamarca.com';
  return preg_replace('#^https?://#', '', trim($d, '/'));
}

/* Envia el correo. Devuelve true si el servidor lo acepto. */
function recup_enviar_mail($para, $nombre, $enlace) {
  $dominio = recup_dominio();
  $de      = 'no-responder@' . $dominio;
  $asunto  = 'APICE - Recuperar tu contrasena';

  $saludo = $nombre ? ('Hola ' . $nombre . ',') : 'Hola,';
  $cuerpo =
    '<div style="font-family:system-ui,Arial,sans-serif;color:#1C2433;font-size:15px;line-height:1.55">'
    . '<p style="font-size:20px;font-weight:700;margin:0 0 4px">ÁPICE</p>'
    . '<p style="color:#6B7280;margin:0 0 18px">Gestión Jurídica</p>'
    . '<p>' . htmlspecialchars($saludo, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p>Pediste recuperar la contraseña de tu cuenta. Tocá el botón para elegir una nueva:</p>'
    . '<p style="margin:22px 0"><a href="' . htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8') . '" '
    . 'style="background:#1C2433;color:#fff;text-decoration:none;padding:13px 22px;border-radius:9px;font-weight:600;display:inline-block">'
    . 'Elegir contraseña nueva</a></p>'
    . '<p style="color:#6B7280;font-size:13px">El enlace vence en 1 hora y se puede usar una sola vez.</p>'
    . '<p style="color:#6B7280;font-size:13px">Si no pediste esto, podés ignorar este correo: tu contraseña actual sigue funcionando.</p>'
    . '<p style="color:#9AA1AC;font-size:12px;margin-top:24px">Si el botón no funciona, copiá y pegá esta dirección:<br>'
    . htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8') . '</p>'
    . '</div>';

  /* 1) Se intenta por SMTP con la casilla del dominio (lo confiable). */
  require_once __DIR__ . '/smtp.php';
  if (smtp_enviar($para, $asunto, $cuerpo)) return true;

  /* 2) Respaldo: envio directo de PHP. En este plan no suele funcionar,
        pero se deja por si el servidor lo habilita mas adelante. */
  $headers  = "MIME-Version: 1.0\r\n";
  $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
  $headers .= 'From: APICE <' . $de . ">\r\n";
  $headers .= 'Reply-To: ' . $de . "\r\n";
  $headers .= "X-Mailer: APICE\r\n";

  if (!function_exists('mail')) return false;
  return @mail($para, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpo, $headers, '-f' . $de);
}

/* Paso 1: pedir el enlace. Responde SIEMPRE igual. */
function recup_pedir($email) {
  $pdo = db();
  recup_tabla($pdo);
  $email = strtolower(trim((string)$email));

  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Escribí un correo válido.');
  }

  /* Tope de pedidos por hora (evita que sirva para molestar a alguien). */
  $st = $pdo->prepare('SELECT COUNT(*) FROM recuperacion_clave r
                       JOIN usuarios u ON u.id = r.usuario_id
                       WHERE u.email = ? AND r.creado_en > (NOW() - INTERVAL 1 HOUR)');
  $st->execute([$email]);
  $reciente = (int)$st->fetchColumn();

  if ($reciente < 3) {
    $su = $pdo->prepare('SELECT id, nombre, email FROM usuarios WHERE email = ? AND activo = 1');
    $su->execute([$email]);
    $u = $su->fetch();
    if ($u) {
      $token = bin2hex(random_bytes(32));
      $hash  = hash('sha256', $token);
      $pdo->prepare('INSERT INTO recuperacion_clave (usuario_id, token_hash, expira_en, ip)
                     VALUES (?, ?, (NOW() + INTERVAL 1 HOUR), ?)')
          ->execute([(int)$u['id'], $hash, substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]);
      $enlace = 'https://' . recup_dominio() . '/?recuperar=' . $token;
      $ok = recup_enviar_mail($u['email'], $u['nombre'], $enlace);
      if (!$ok) error_log('[APICE] recuperacion: el servidor no pudo enviar el correo a ' . $email);
    }
  }

  /* Misma respuesta siempre: no revela si el correo existe. */
  json_ok(['mensaje' => 'Si ese correo tiene una cuenta, te enviamos un enlace para elegir una contraseña nueva. Revisá tu bandeja y el correo no deseado.']);
}

/* Paso 2: usar el enlace y poner la contrasena nueva. */
function recup_restablecer($token, $nueva) {
  $pdo = db();
  recup_tabla($pdo);
  $token = trim((string)$token);
  $nueva = (string)$nueva;

  if ($token === '') json_error('Falta el código del enlace.');
  if (strlen($nueva) < 6) json_error('La contraseña nueva tiene que tener al menos 6 caracteres.');

  $hash = hash('sha256', $token);
  $st = $pdo->prepare('SELECT id, usuario_id FROM recuperacion_clave
                       WHERE token_hash = ? AND usado = 0 AND expira_en > NOW()');
  $st->execute([$hash]);
  $r = $st->fetch();
  if (!$r) json_error('El enlace no es válido o ya venció. Pedí uno nuevo.', 400);

  $pdo->prepare('UPDATE usuarios SET password_hash = ?, debe_cambiar_clave = 0 WHERE id = ?')
      ->execute([hash_password($nueva), (int)$r['usuario_id']]);
  /* Se anulan TODOS los pedidos de esa persona (incluido este). */
  $pdo->prepare('UPDATE recuperacion_clave SET usado = 1 WHERE usuario_id = ?')
      ->execute([(int)$r['usuario_id']]);
  /* Limpieza de pedidos viejos. */
  $pdo->exec('DELETE FROM recuperacion_clave WHERE creado_en < (NOW() - INTERVAL 7 DAY)');

  json_ok(['listo' => true, 'mensaje' => 'Tu contraseña quedó cambiada. Ya podés ingresar.']);
}
