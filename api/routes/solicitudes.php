<?php
/* ============================================================================
 *  SOLICITUDES DE PRUEBA  (/api/solicitudes/...)
 *
 *  Es el "buzón" de la landing: cuando alguien completa el formulario de
 *  "Solicitá tu prueba gratuita", los datos llegan acá y:
 *    1) se guardan en la tabla `solicitudes` (para verlos en el panel), y
 *    2) se manda un aviso por correo a la casilla de contacto.
 *
 *  Rutas:
 *    POST /api/solicitudes            -> PÚBLICA (la usa el formulario)
 *    GET  /api/solicitudes            -> solo super-administradora (lista)
 *    POST /api/solicitudes/{id}/estado-> solo super-administradora (cambia estado)
 *
 *  A dónde llega el aviso: por defecto contacto@abogadoscatamarca.com. Se puede
 *  cambiar guardando la clave 'solicitudes_email' en la tabla `ajustes`.
 * ========================================================================== */

/* A dónde llegan los avisos de solicitudes. Va directo al Gmail de Ro porque el
 * plan de correo permite una sola casilla (ya usada por no-responder@). El envío
 * SALE igual desde no-responder@ (SMTP), que puede mandar a cualquier dirección.
 * Si en el futuro se crea contacto@ (mejorando el plan), se cambia acá o con la
 * clave 'solicitudes_email' en la tabla ajustes. */
define('SOLICITUDES_EMAIL_DEFECTO', 'lujanrociocristal@gmail.com');

function asegurar_tabla_solicitudes() {
  db()->exec("CREATE TABLE IF NOT EXISTS solicitudes (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre       VARCHAR(120) NOT NULL,
    estudio      VARCHAR(160) NULL,
    provincia    VARCHAR(120) NULL,
    whatsapp     VARCHAR(60)  NULL,
    email        VARCHAR(160) NULL,
    causas       VARCHAR(40)  NULL,
    abogados     VARCHAR(40)  NULL,
    dolor        TEXT NULL,
    origen       VARCHAR(60)  NULL,
    estado       VARCHAR(20)  NOT NULL DEFAULT 'nueva',
    ip           VARCHAR(45)  NULL,
    creado_en    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_estado (estado),
    KEY idx_fecha (creado_en)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function handle_solicitudes($method, $resto) {
  asegurar_tabla_solicitudes();
  $a = $resto[0] ?? '';
  if ($method === 'POST' && $a === '')                 return solicitud_crear();
  if ($method === 'GET'  && $a === '')                 return solicitud_listar();
  if ($method === 'POST' && $a !== '' && ($resto[1] ?? '') === 'estado')
                                                        return solicitud_estado((int)$a);
  json_error('Acción no válida.', 404);
}

/* Recorta un texto a un largo máximo (defensa: nadie manda 10.000 caracteres). */
function sol_limpiar($v, $max) {
  $v = trim((string)$v);
  if ($v === '') return null;
  if (function_exists('mb_substr')) $v = mb_substr($v, 0, $max);
  else $v = substr($v, 0, $max);
  return $v;
}

/* ---- POST público: recibir una solicitud del formulario ---- */
function solicitud_crear() {
  $nombre    = sol_limpiar(field('nombre'), 120);
  $estudio   = sol_limpiar(field('estudio'), 160);
  $provincia = sol_limpiar(field('provincia'), 120);
  $whatsapp  = sol_limpiar(field('whatsapp'), 60);
  $email     = sol_limpiar(field('email'), 160);
  $causas    = sol_limpiar(field('causas'), 40);
  $abogados  = sol_limpiar(field('abogados'), 40);
  $dolor     = sol_limpiar(field('dolor'), 1000);
  $origen    = sol_limpiar(field('origen'), 60) ?: 'landing';

  // Mínimos para que la solicitud sirva: nombre + una forma de contacto.
  if (!$nombre)               json_error('Necesitamos tu nombre.');
  if (!$email && !$whatsapp)  json_error('Dejanos un email o un WhatsApp para contactarte.');
  if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('El email no es válido.');

  $ip = $_SERVER['REMOTE_ADDR'] ?? null;

  db()->prepare('INSERT INTO solicitudes
      (nombre, estudio, provincia, whatsapp, email, causas, abogados, dolor, origen, ip)
      VALUES (?,?,?,?,?,?,?,?,?,?)')
    ->execute([$nombre, $estudio, $provincia, $whatsapp, $email, $causas, $abogados, $dolor, $origen, $ip]);
  $id = (int)db()->lastInsertId();

  // Aviso por correo (si el SMTP está configurado). No bloquea la respuesta.
  solicitud_avisar_correo(compact('nombre','estudio','provincia','whatsapp','email','causas','abogados','dolor'));

  json_ok(['recibida' => true, 'id' => $id], 201);
}

/* Manda el aviso por correo a la casilla de contacto. */
function solicitud_avisar_correo($d) {
  $para = SOLICITUDES_EMAIL_DEFECTO;
  try {
    $st = db()->prepare("SELECT valor FROM ajustes WHERE clave = 'solicitudes_email' LIMIT 1");
    $st->execute();
    $v = $st->fetchColumn();
    if ($v) $para = $v;
  } catch (Throwable $e) { /* la tabla ajustes puede no existir: usamos el defecto */ }

  require_once __DIR__ . '/../lib/smtp.php';

  $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
  $fila = function ($etq, $val) use ($e) {
    if (!$val) return '';
    return '<tr><td style="padding:6px 10px;color:#6b7a86;font-size:13px;white-space:nowrap">'
         . $e($etq) . '</td><td style="padding:6px 10px;color:#1C2433;font-size:14px"><b>'
         . $e($val) . '</b></td></tr>';
  };

  $html =
    '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:560px;margin:0 auto">'
    . '<div style="background:#1C2433;color:#fff;padding:16px 18px;border-radius:12px 12px 0 0">'
    . '<b style="letter-spacing:.12em">ÁPICE</b> · Nueva solicitud de prueba</div>'
    . '<table style="width:100%;border-collapse:collapse;background:#f6f9fa;border-radius:0 0 12px 12px">'
    . $fila('Nombre', $d['nombre'])
    . $fila('Estudio', $d['estudio'])
    . $fila('Provincia / fuero', $d['provincia'])
    . $fila('WhatsApp', $d['whatsapp'])
    . $fila('Email', $d['email'])
    . $fila('Causas', $d['causas'])
    . $fila('Abogados', $d['abogados'])
    . $fila('Qué le complica', $d['dolor'])
    . '</table>'
    . '<p style="color:#8a97a2;font-size:12px;margin-top:14px">'
    . 'Este aviso se generó automáticamente desde la web de ÁPICE. '
    . 'También quedó guardado en tu panel de solicitudes.</p></div>';

  try { smtp_enviar($para, 'Nueva solicitud de prueba · ÁPICE', $html, 'ÁPICE Web'); }
  catch (Throwable $e2) { error_log('[APICE] aviso solicitud falló: ' . $e2->getMessage()); }
}

/* ---- GET privado: lista para la super-administradora ---- */
function solicitud_listar() {
  require_superadmin();
  $st = db()->query('SELECT * FROM solicitudes ORDER BY creado_en DESC');
  json_ok($st->fetchAll());
}

/* ---- POST privado: cambiar el estado (nueva/contactada/prueba/cliente/descartada) ---- */
function solicitud_estado($id) {
  require_superadmin();
  $estado = strtolower(trim((string)field('estado')));
  $validos = ['nueva','contactada','prueba','cliente','descartada'];
  if (!in_array($estado, $validos, true)) json_error('Estado no válido.');
  $st = db()->prepare('UPDATE solicitudes SET estado = ? WHERE id = ?');
  $st->execute([$estado, $id]);
  json_ok(['actualizada' => true]);
}
