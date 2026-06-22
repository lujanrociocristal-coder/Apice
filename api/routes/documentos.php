<?php
/* ============================================================================
 *  DOCUMENTOS  (/api/documentos/...)
 *    GET    /api/documentos?causa=ID   -> documentos de una causa
 *    POST   /api/documentos            -> agregar documento (metadatos/enlace)
 *    PUT    /api/documentos/{id}       -> editar
 *    DELETE /api/documentos/{id}       -> borrar
 *
 *  Nota: este endpoint guarda los DATOS del documento (nombre, carpeta,
 *  visibilidad, enlace). La subida del archivo en sí (a Google Drive u otro)
 *  queda preparada para la etapa 2.
 * ========================================================================== */

function handle_documentos($method, $resto) {
  $id = isset($resto[0]) ? (int)$resto[0] : 0;

  if ($id) {
    if ($method === 'PUT')    return doc_editar($id);
    if ($method === 'DELETE') return doc_borrar($id);
  }
  if ($method === 'GET')  return docs_listar();
  if ($method === 'POST') return doc_crear();
  json_error('Método no permitido.', 405);
}

function docs_listar() {
  $u = require_login();
  $causaId = (int)($_GET['causa'] ?? 0);
  puede_acceder_causa($causaId, $u['rol'] === 'cliente');
  $st = db()->prepare('SELECT * FROM documentos WHERE causa_id = ? ORDER BY id ASC');
  $st->execute([$causaId]);
  $docs = $st->fetchAll();
  // El cliente solo ve los documentos marcados como visibles.
  if ($u['rol'] === 'cliente') $docs = array_values(array_filter($docs, fn($d) => (int)$d['visible_cliente'] === 1));
  foreach ($docs as &$d) decode_json_fields($d, ['etiquetas', 'historial']);
  json_ok($docs);
}

function _doc_campos() { return ['nombre','tipo','carpeta','relevancia','visible_cliente','url','fecha_txt','usuario_nombre']; }

function doc_crear() {
  require_profesional();
  $causaId = (int)field('causa_id');
  puede_acceder_causa($causaId, false);
  $nombre = trim((string)field('nombre'));
  if (!$nombre) json_error('El documento necesita un nombre.');
  $cols = ['causa_id']; $vals = [$causaId]; $ph = ['?'];
  foreach (_doc_campos() as $f) { $cols[] = $f; $vals[] = field($f); $ph[] = '?'; }
  if (field('etiquetas') !== null) { $cols[]='etiquetas'; $vals[]=json_encode(field('etiquetas'), JSON_UNESCAPED_UNICODE); $ph[]='?'; }
  db()->prepare('INSERT INTO documentos (' . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')')->execute($vals);
  json_ok(['id' => (int)db()->lastInsertId()], 201);
}

function doc_editar($id) {
  require_profesional();
  $st = db()->prepare('SELECT causa_id FROM documentos WHERE id = ?');
  $st->execute([$id]); $cid = $st->fetchColumn();
  if (!$cid) json_error('Documento no encontrado.', 404);
  puede_acceder_causa((int)$cid, false);
  $sets = []; $vals = [];
  foreach (_doc_campos() as $f) {
    $v = field($f, '__NO__');
    if ($v !== '__NO__') { $sets[] = "$f = ?"; $vals[] = $v; }
  }
  if (field('etiquetas') !== null) { $sets[]='etiquetas = ?'; $vals[]=json_encode(field('etiquetas'), JSON_UNESCAPED_UNICODE); }
  if (!$sets) json_error('No hay nada para actualizar.');
  $vals[] = $id;
  db()->prepare('UPDATE documentos SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
  json_ok(['actualizado' => true]);
}

function doc_borrar($id) {
  require_profesional();
  $st = db()->prepare('SELECT causa_id FROM documentos WHERE id = ?');
  $st->execute([$id]); $cid = $st->fetchColumn();
  if (!$cid) json_error('Documento no encontrado.', 404);
  puede_acceder_causa((int)$cid, false);
  db()->prepare('DELETE FROM documentos WHERE id = ?')->execute([$id]);
  json_ok(['borrado' => true]);
}
