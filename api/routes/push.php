<?php
/* ============================================================================
 *  PUSH  (/api/push/...)
 *    GET  /api/push/clave       -> clave pública VAPID (para suscribirse)
 *    POST /api/push/subscribe   -> guarda la suscripción del dispositivo
 *    GET  /api/push/pendiente    -> qué aviso mostrar (lo consulta el celular)
 *    GET  /api/push/probar       -> envía un push de prueba a MIS dispositivos
 * ========================================================================== */

require_once __DIR__ . '/../lib/push.php';

function asegurar_tabla_push() {
  db()->exec("CREATE TABLE IF NOT EXISTS push_subs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    estudio_id INT UNSIGNED NOT NULL,
    endpoint VARCHAR(500) NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ep (endpoint(255)),
    KEY idx_est (estudio_id),
    KEY idx_usr (usuario_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function handle_push($method, $resto) {
  asegurar_tabla_push();
  $a = $resto[0] ?? '';
  if ($method === 'GET'  && $a === 'clave')     return push_clave();
  if ($method === 'POST' && $a === 'subscribe') return push_subscribe();
  if ($method === 'GET'  && $a === 'pendiente') return push_pendiente();
  if ($method === 'GET'  && $a === 'probar')    return push_probar();
  json_error('Acción de push no válida.', 404);
}

function push_clave() {
  $v = vapid_keys();
  if (!$v) json_error('No se pudo preparar las claves de push.', 500);
  json_ok(['clave' => $v['pub']]);
}

function push_subscribe() {
  $u = require_login();
  $endpoint = trim((string)field('endpoint'));
  if ($endpoint === '' || stripos($endpoint, 'http') !== 0) json_error('Suscripción inválida.');
  db()->prepare('INSERT INTO push_subs (usuario_id, estudio_id, endpoint) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE usuario_id = VALUES(usuario_id), estudio_id = VALUES(estudio_id)')
      ->execute([(int)$u['id'], (int)$u['estudio_id'], $endpoint]);
  json_ok(['suscripto' => true]);
}

/* Qué mostrar cuando llega un push: audiencias/citas de hoy y mañana. */
function push_pendiente() {
  $u = require_login();
  $eid = (int)$u['estudio_id'];
  $hoy = date('Y-m-d');
  $man = date('Y-m-d', strtotime('+1 day'));
  $st = db()->prepare("SELECT tipo, cliente_nombre, hora, fecha FROM audiencias
                       WHERE estudio_id = ? AND fecha IN (?, ?) ORDER BY fecha ASC, hora ASC");
  $st->execute([$eid, $hoy, $man]);
  $rows = $st->fetchAll();
  if (!$rows) { json_ok(['title' => 'ÁPICE', 'body' => 'Tenés novedades en tu estudio.']); return; }
  if (count($rows) === 1) {
    $r = $rows[0];
    $cuando = $r['fecha'] === $hoy ? 'hoy' : 'mañana';
    $que = $r['tipo'] === 'cita' ? ('Cita con ' . ($r['cliente_nombre'] ?: 'un cliente')) : 'Audiencia';
    $body = $que . ' ' . $cuando . ($r['hora'] ? (' a las ' . $r['hora'] . ' hs') : '') . '.';
    json_ok(['title' => '📅 Recordatorio ÁPICE', 'body' => $body]);
    return;
  }
  json_ok(['title' => '📅 Recordatorio ÁPICE', 'body' => 'Tenés ' . count($rows) . ' audiencias/citas para hoy y mañana.']);
}

/* Envía un push de prueba a los dispositivos del usuario logueado. */
function push_probar() {
  $u = require_login();
  $st = db()->prepare('SELECT endpoint FROM push_subs WHERE usuario_id = ?');
  $st->execute([(int)$u['id']]);
  $eps = $st->fetchAll(PDO::FETCH_COLUMN);
  $res = [];
  foreach ($eps as $ep) {
    $code = push_send($ep);
    $res[] = $code;
    if ($code === 404 || $code === 410) {
      db()->prepare('DELETE FROM push_subs WHERE endpoint = ?')->execute([$ep]);
    }
  }
  json_ok(['enviados' => count($eps), 'codigos' => $res]);
}
