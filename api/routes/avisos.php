<?php
/* ============================================================================
 *  AVISOS AUTOMÁTICOS  (/api/avisos)
 *
 *  Devuelve novedades del estudio que se generan solas (sin marcar nada):
 *   - documentos: archivos subidos en los últimos 7 días por OTRA persona
 *     (cliente o colega), no por vos.
 *   - clientes: clientes que ingresaron por PRIMERA vez en los últimos 7 días.
 * ========================================================================== */

function asegurar_col_primer_acceso() {
  try {
    $c = db()->query("SHOW COLUMNS FROM usuarios LIKE 'primer_acceso'")->fetch();
    if (!$c) db()->exec("ALTER TABLE usuarios ADD COLUMN primer_acceso DATETIME NULL");
  } catch (Throwable $e) { /* silencioso */ }
}

function handle_avisos($method, $resto) {
  $u = require_profesional();
  $eid = (int)$u['estudio_id'];
  asegurar_col_primer_acceso();

  // Documentos nuevos (últimos 7 días, subidos por otra persona).
  $docs = [];
  try {
    $st = db()->prepare("SELECT a.id, a.nombre, a.causa_id, a.tipo, a.creado_en, us.nombre AS quien, us.rol AS rol
                         FROM archivos a LEFT JOIN usuarios us ON us.id = a.subido_por
                         WHERE a.estudio_id = ? AND a.creado_en >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                           AND (a.subido_por IS NULL OR a.subido_por <> ?)
                         ORDER BY a.creado_en DESC LIMIT 30");
    $st->execute([$eid, (int)$u['id']]);
    $docs = $st->fetchAll();
  } catch (Throwable $e) { /* la tabla archivos puede no existir aún */ }

  // Clientes que ingresaron por primera vez (últimos 7 días).
  $clis = [];
  try {
    $st = db()->prepare("SELECT id, nombre, email, primer_acceso
                         FROM usuarios
                         WHERE estudio_id = ? AND rol = 'cliente'
                           AND primer_acceso IS NOT NULL
                           AND primer_acceso >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                         ORDER BY primer_acceso DESC");
    $st->execute([$eid]);
    $clis = $st->fetchAll();
  } catch (Throwable $e) { /* silencioso */ }

  json_ok(['documentos' => $docs, 'clientes' => $clis]);
}
