<?php
/* ============================================================================
 *  CONVENIOS DE HONORARIOS  (/api/convenios/...)
 *    GET  /api/convenios?causa=ID   -> convenio de una causa (si existe)
 *    POST /api/convenios            -> crear o actualizar el convenio de la causa
 *
 *  IMPORTANTE: la app solo GUARDA y MUESTRA el convenio. El texto legal y el
 *  clausulado los redactás/revisás vos. No se genera contenido jurídico solo.
 * ========================================================================== */

function handle_convenios($method, $resto) {
  if ($method === 'GET')  return convenio_ver();
  if ($method === 'POST') return convenio_guardar();
  json_error('Método no permitido.', 405);
}

function convenio_ver() {
  $u = require_login();
  $causa = (int)($_GET['causa'] ?? 0);
  puede_acceder_causa($causa, $u['rol'] === 'cliente');
  $st = db()->prepare('SELECT * FROM convenios WHERE causa_id = ?');
  $st->execute([$causa]);
  $c = $st->fetch();
  if ($c) decode_json_fields($c, ['datos']);
  json_ok($c ?: null);
}

function convenio_guardar() {
  require_profesional();
  $causa = (int)field('causa_id');
  puede_acceder_causa($causa, false);
  $datos = field('datos') !== null ? json_encode(field('datos'), JSON_UNESCAPED_UNICODE) : null;
  $texto = field('texto');
  // ¿Ya existe? -> actualizar; si no, crear.
  $st = db()->prepare('SELECT id FROM convenios WHERE causa_id = ?');
  $st->execute([$causa]);
  if ($st->fetchColumn()) {
    db()->prepare('UPDATE convenios SET datos = ?, texto = ? WHERE causa_id = ?')->execute([$datos, $texto, $causa]);
  } else {
    db()->prepare('INSERT INTO convenios (causa_id, datos, texto) VALUES (?,?,?)')->execute([$causa, $datos, $texto]);
  }
  json_ok(['guardado' => true]);
}
