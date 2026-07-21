<?php
/* ============================================================================
 *  ENVIO DE CORREO POR SMTP  (v46)
 *
 *  Por que existe: el envio directo de PHP (mail()) no funciona en este plan
 *  de Hostinger, asi que los correos se mandan por SMTP con una casilla del
 *  propio dominio. Eso ademas mejora mucho que el correo NO caiga en spam,
 *  porque sale autenticado desde el dominio.
 *
 *  QUE HAY QUE COMPLETAR en api/config.php:
 *      'smtp_host' => 'smtp.hostinger.com',
 *      'smtp_port' => 465,
 *      'smtp_user' => 'no-responder@abogadoscatamarca.com',
 *      'smtp_pass' => 'LA CONTRASENA DE ESA CASILLA',
 *
 *  Si esos datos NO estan cargados, se intenta con mail() como respaldo.
 * ========================================================================== */

function smtp_config() {
  $cfg = @include __DIR__ . '/../config.php';
  if (!is_array($cfg)) return null;
  if (empty($cfg['smtp_host']) || empty($cfg['smtp_user']) || empty($cfg['smtp_pass'])) return null;
  return [
    'host' => $cfg['smtp_host'],
    'port' => !empty($cfg['smtp_port']) ? (int)$cfg['smtp_port'] : 465,
    'user' => $cfg['smtp_user'],
    'pass' => $cfg['smtp_pass'],
  ];
}

/* Lee la respuesta del servidor y verifica que empiece con el codigo esperado. */
function smtp_leer($fp, $esperado, &$error) {
  $resp = '';
  while (($linea = fgets($fp, 515)) !== false) {
    $resp .= $linea;
    if (strlen($linea) < 4 || $linea[3] !== '-') break;
  }
  $codigo = substr(trim($resp), 0, 3);
  if ((string)$codigo !== (string)$esperado) {
    $error = 'SMTP esperaba ' . $esperado . ' y respondio: ' . trim($resp);
    return false;
  }
  return true;
}

function smtp_decir($fp, $texto) { fwrite($fp, $texto . "\r\n"); }

/* Envia un correo HTML. Devuelve true si salio bien. */
function smtp_enviar($para, $asunto, $htmlCuerpo, $deNombre = 'APICE') {
  $c = smtp_config();
  if (!$c) return false;

  $error = '';
  $seguro = ((int)$c['port'] === 465);
  $destino = ($seguro ? 'ssl://' : 'tcp://') . $c['host'] . ':' . (int)$c['port'];

  $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
  $fp = @stream_socket_client($destino, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
  if (!$fp) { error_log('[APICE] SMTP: no se pudo conectar a ' . $destino . ' -> ' . $errstr); return false; }
  stream_set_timeout($fp, 20);

  $ok = smtp_leer($fp, 220, $error);
  if ($ok) { smtp_decir($fp, 'EHLO ' . $c['host']); $ok = smtp_leer($fp, 250, $error); }

  /* Si el puerto es 587, se pide cifrado antes de mandar la clave. */
  if ($ok && !$seguro) {
    smtp_decir($fp, 'STARTTLS');
    $ok = smtp_leer($fp, 220, $error);
    if ($ok) {
      $ok = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
      if (!$ok) $error = 'SMTP: no se pudo activar el cifrado (STARTTLS)';
    }
    if ($ok) { smtp_decir($fp, 'EHLO ' . $c['host']); $ok = smtp_leer($fp, 250, $error); }
  }

  if ($ok) { smtp_decir($fp, 'AUTH LOGIN'); $ok = smtp_leer($fp, 334, $error); }
  if ($ok) { smtp_decir($fp, base64_encode($c['user'])); $ok = smtp_leer($fp, 334, $error); }
  if ($ok) { smtp_decir($fp, base64_encode($c['pass'])); $ok = smtp_leer($fp, 235, $error); }
  if ($ok) { smtp_decir($fp, 'MAIL FROM:<' . $c['user'] . '>'); $ok = smtp_leer($fp, 250, $error); }
  if ($ok) { smtp_decir($fp, 'RCPT TO:<' . $para . '>'); $ok = smtp_leer($fp, 250, $error); }
  if ($ok) { smtp_decir($fp, 'DATA'); $ok = smtp_leer($fp, 354, $error); }

  if ($ok) {
    $cabeceras =
        'From: ' . $deNombre . ' <' . $c['user'] . ">\r\n"
      . 'To: <' . $para . ">\r\n"
      . 'Subject: =?UTF-8?B?' . base64_encode($asunto) . "?=\r\n"
      . 'Date: ' . date('r') . "\r\n"
      . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $c['host'] . ">\r\n"
      . "MIME-Version: 1.0\r\n"
      . "Content-Type: text/html; charset=UTF-8\r\n"
      . "Content-Transfer-Encoding: base64\r\n";
    $cuerpo = chunk_split(base64_encode($htmlCuerpo));
    /* Un punto solo en una linea termina el mensaje: hay que protegerlo. */
    $cuerpo = str_replace("\r\n.", "\r\n..", $cuerpo);
    smtp_decir($fp, $cabeceras . "\r\n" . $cuerpo . "\r\n.");
    $ok = smtp_leer($fp, 250, $error);
  }

  smtp_decir($fp, 'QUIT');
  fclose($fp);

  if (!$ok) error_log('[APICE] SMTP fallo: ' . $error);
  return $ok;
}
