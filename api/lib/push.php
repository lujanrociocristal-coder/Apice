<?php
/* ============================================================================
 *  WEB PUSH (VAPID) — motor de envío de notificaciones al celular
 *
 *  Manda un "aviso" (tickle, sin contenido) al servicio de push del navegador.
 *  El service worker, al recibirlo, consulta /api/push/pendiente y muestra la
 *  notificación con el logo de ÁPICE. Así funciona con la app cerrada.
 *
 *  Las claves VAPID se generan solas la primera vez y se guardan FUERA de
 *  public_html (carpeta privada). Solo se usa openssl (nativo de PHP).
 * ========================================================================== */

function b64url($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

function vapid_file() { return dirname(__DIR__, 3) . '/apice_privado/vapid.json'; }

/* Devuelve {pem, pub}. Genera y guarda la primera vez. */
function vapid_keys() {
  $f = vapid_file();
  if (is_file($f)) {
    $d = json_decode((string)file_get_contents($f), true);
    if ($d && !empty($d['pem']) && !empty($d['pub'])) return $d;
  }
  $res = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
  if (!$res) return null;
  openssl_pkey_export($res, $pem);
  $det = openssl_pkey_get_details($res);
  $x = str_pad($det['ec']['x'], 32, "\0", STR_PAD_LEFT);
  $y = str_pad($det['ec']['y'], 32, "\0", STR_PAD_LEFT);
  $pub = b64url("\x04" . $x . $y);
  $d = ['pem' => $pem, 'pub' => $pub];
  @mkdir(dirname($f), 0700, true);
  @file_put_contents($f, json_encode($d));
  @chmod($f, 0600);
  return $d;
}

/* Convierte una firma ECDSA en formato DER a 64 bytes crudos (r||s). */
function der_to_raw($der) {
  $off = 0;
  if (ord($der[$off++]) != 0x30) return null;
  $len = ord($der[$off++]);
  if ($len & 0x80) { $n = $len & 0x7f; $off += $n; }
  if (ord($der[$off++]) != 0x02) return null;
  $rl = ord($der[$off++]); $r = substr($der, $off, $rl); $off += $rl;
  if (ord($der[$off++]) != 0x02) return null;
  $sl = ord($der[$off++]); $s = substr($der, $off, $sl); $off += $sl;
  $r = ltrim($r, "\0"); $s = ltrim($s, "\0");
  $r = str_pad($r, 32, "\0", STR_PAD_LEFT);
  $s = str_pad($s, 32, "\0", STR_PAD_LEFT);
  return $r . $s;
}

/* Arma el JWT VAPID firmado (ES256) para un servicio de push (aud = origen). */
function vapid_jwt($aud) {
  $v = vapid_keys(); if (!$v) return null;
  $header  = b64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
  $payload = b64url(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => 'mailto:soporte@abogadoscatamarca.com']));
  $input = $header . '.' . $payload;
  $key = openssl_pkey_get_private($v['pem']);
  $der = '';
  if (!openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256)) return null;
  $raw = der_to_raw($der);
  return $input . '.' . b64url($raw);
}

/* Envía un push (tickle) a una suscripción. Devuelve el código HTTP (201 = ok). */
function push_send($endpoint, $ttl = 86400) {
  $v = vapid_keys(); if (!$v) return 0;
  $parts = parse_url($endpoint);
  if (empty($parts['host'])) return 0;
  $aud = ($parts['scheme'] ?? 'https') . '://' . $parts['host'];
  $jwt = vapid_jwt($aud); if (!$jwt) return 0;
  $headers = [
    'Authorization: vapid t=' . $jwt . ', k=' . $v['pub'],
    'TTL: ' . (int)$ttl,
    'Content-Length: 0',
  ];
  $ch = curl_init($endpoint);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => '',
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 12,
  ]);
  curl_exec($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return $code;
}
