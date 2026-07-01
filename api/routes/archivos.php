<?php
/* ============================================================================
 *  ARCHIVOS ADJUNTOS  (/api/archivos/...)
 *
 *  Sube y guarda archivos REALES (PDF, Word, imágenes) asociados a una causa.
 *
 *  DÓNDE SE GUARDAN (seguro):
 *    En una carpeta PRIVADA fuera de public_html:
 *      /home/.../<tu-dominio>/apice_archivos/<estudio>/<causa>/<archivo>
 *    - No es accesible desde internet (solo se descarga por la app, con permiso).
 *    - El despliegue nunca la toca (queda fuera de la zona web).
 *
 *    GET    /api/archivos?causa=ID        -> lista de archivos de una causa
 *    POST   /api/archivos                 -> subir un archivo (multipart: file, causa_id, ...)
 *    GET    /api/archivos/{id}/descargar  -> descargar/ver el archivo (con permiso)
 *    PUT    /api/archivos/{id}            -> renombrar / cambiar visibilidad
 *    DELETE /api/archivos/{id}            -> eliminar (borra el archivo del disco)
 * ========================================================================== */

/* Carpeta base privada, FUERA de public_html (a prueba de despliegues). */
function archivos_base() {
  return dirname(__DIR__, 3) . '/apice_archivos';
}

/* Crea la tabla de archivos si todavía no existe (auto-instala). */
function asegurar_tabla_archivos() {
  db()->exec("CREATE TABLE IF NOT EXISTS archivos (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    estudio_id      INT UNSIGNED NOT NULL,
    causa_id        INT UNSIGNED NOT NULL,
    nombre          VARCHAR(255) NOT NULL,
    archivo         VARCHAR(120) NOT NULL,
    tipo            VARCHAR(12)  NOT NULL,
    tamano          INT UNSIGNED NOT NULL DEFAULT 0,
    visible_cliente TINYINT(1)   NOT NULL DEFAULT 0,
    subido_por      INT UNSIGNED NULL,
    creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_archivos_causa (causa_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function handle_archivos($method, $resto) {
  asegurar_tabla_archivos();
  $id  = isset($resto[0]) ? (int)$resto[0] : 0;
  $sub = $resto[1] ?? '';

  if ($id && $sub === 'descargar' && $method === 'GET') return archivo_descargar($id);
  if ($id && $method === 'PUT')    return archivo_editar($id);
  if ($id && $method === 'DELETE') return archivo_eliminar($id);
  if ($method === 'GET')  return archivos_listar();
  if ($method === 'POST') return archivo_subir();
  json_error('Método no permitido.', 405);
}

/* ---- Formatos y límite ---- */
function archivos_permitidos() { return ['pdf','doc','docx','jpg','jpeg','png']; }
function archivos_max_bytes()  { return 20 * 1024 * 1024; } // 20 MB

/* Subir un archivo (los datos vienen como formulario multipart). */
function archivo_subir() {
  $u = require_profesional();
  $causaId = (int)($_POST['causa_id'] ?? 0);
  puede_acceder_causa($causaId, false);

  if (empty($_FILES['file']) || !isset($_FILES['file']['error']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    json_error('No se recibió el archivo (puede ser demasiado grande).');
  }
  $f = $_FILES['file'];
  $orig = (string)$f['name'];
  $size = (int)$f['size'];
  if ($size <= 0) json_error('El archivo está vacío.');
  if ($size > archivos_max_bytes()) json_error('El archivo supera el límite de 20 MB.');

  $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  if (!in_array($ext, archivos_permitidos(), true)) {
    json_error('Formato no permitido. Solo PDF, Word (doc/docx) o imágenes (jpg, png).');
  }

  $estudioId = (int)$u['estudio_id'];
  $dir = archivos_base() . '/' . $estudioId . '/' . $causaId;
  if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
  if (!is_dir($dir)) json_error('No se pudo crear la carpeta de archivos en el servidor.', 500);

  $stored = bin2hex(random_bytes(10)) . '.' . $ext;   // nombre interno al azar (seguridad)
  $dest = $dir . '/' . $stored;
  if (!move_uploaded_file($f['tmp_name'], $dest)) json_error('No se pudo guardar el archivo.', 500);

  $nombre = trim((string)($_POST['nombre'] ?? '')) ?: $orig;
  $visible = !empty($_POST['visible_cliente']) ? 1 : 0;

  db()->prepare('INSERT INTO archivos (estudio_id, causa_id, nombre, archivo, tipo, tamano, visible_cliente, subido_por)
                 VALUES (?,?,?,?,?,?,?,?)')
      ->execute([$estudioId, $causaId, $nombre, $stored, $ext, $size, $visible, $u['id']]);
  json_ok(['id' => (int)db()->lastInsertId(), 'nombre' => $nombre], 201);
}

/* Listar archivos de una causa. El cliente solo ve los marcados visibles. */
function archivos_listar() {
  $u = require_login();
  $causaId = (int)($_GET['causa'] ?? 0);
  puede_acceder_causa($causaId, $u['rol'] === 'cliente');
  $st = db()->prepare('SELECT id, nombre, tipo, tamano, visible_cliente, creado_en
                       FROM archivos WHERE causa_id = ? ORDER BY creado_en DESC');
  $st->execute([$causaId]);
  $rows = $st->fetchAll();
  if ($u['rol'] === 'cliente') {
    $rows = array_values(array_filter($rows, function ($r) { return (int)$r['visible_cliente'] === 1; }));
  }
  json_ok($rows);
}

/* Descargar / ver un archivo (verifica permiso y entrega el archivo). */
function archivo_descargar($id) {
  $u = require_login();
  $st = db()->prepare('SELECT * FROM archivos WHERE id = ?');
  $st->execute([$id]);
  $a = $st->fetch();
  if (!$a) json_error('Archivo no encontrado.', 404);
  puede_acceder_causa((int)$a['causa_id'], $u['rol'] === 'cliente');
  if ($u['rol'] === 'cliente' && (int)$a['visible_cliente'] !== 1) json_error('No tenés acceso a este archivo.', 403);

  $path = archivos_base() . '/' . (int)$a['estudio_id'] . '/' . (int)$a['causa_id'] . '/' . $a['archivo'];
  if (!is_file($path)) json_error('El archivo ya no está en el servidor.', 404);

  $mimes = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
  ];
  $mime = $mimes[strtolower($a['tipo'])] ?? 'application/octet-stream';
  $descarga = preg_replace('/[^A-Za-z0-9 ._\-]/', '_', $a['nombre']);
  if (stripos($descarga, '.' . $a['tipo']) === false) $descarga .= '.' . $a['tipo'];

  header('Content-Type: ' . $mime);
  header('Content-Disposition: inline; filename="' . $descarga . '"');
  header('Content-Length: ' . filesize($path));
  header('X-Content-Type-Options: nosniff');
  readfile($path);
  exit;
}

/* Renombrar / cambiar visibilidad. */
function archivo_editar($id) {
  require_profesional();
  $st = db()->prepare('SELECT causa_id FROM archivos WHERE id = ?');
  $st->execute([$id]);
  $cid = $st->fetchColumn();
  if (!$cid) json_error('Archivo no encontrado.', 404);
  puede_acceder_causa((int)$cid, false);

  $sets = []; $vals = [];
  if (field('nombre', '__NO__') !== '__NO__') {
    $n = trim((string)field('nombre'));
    if ($n !== '') { $sets[] = 'nombre = ?'; $vals[] = $n; }
  }
  if (field('visible_cliente', '__NO__') !== '__NO__') {
    $sets[] = 'visible_cliente = ?'; $vals[] = field('visible_cliente') ? 1 : 0;
  }
  if (!$sets) json_error('No hay nada para actualizar.');
  $vals[] = $id;
  db()->prepare('UPDATE archivos SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
  json_ok(['actualizado' => true]);
}

/* Eliminar el archivo (de la base y del disco). */
function archivo_eliminar($id) {
  require_profesional();
  $st = db()->prepare('SELECT * FROM archivos WHERE id = ?');
  $st->execute([$id]);
  $a = $st->fetch();
  if (!$a) json_error('Archivo no encontrado.', 404);
  puede_acceder_causa((int)$a['causa_id'], false);
  $path = archivos_base() . '/' . (int)$a['estudio_id'] . '/' . (int)$a['causa_id'] . '/' . $a['archivo'];
  if (is_file($path)) @unlink($path);
  db()->prepare('DELETE FROM archivos WHERE id = ?')->execute([$id]);
  json_ok(['eliminado' => true]);
}
