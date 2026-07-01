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
 *  AISLAMIENTO (multi-estudio):
 *    Todo se separa por ESTUDIO. El estudio SIEMPRE se toma de la sesión de la
 *    persona logueada (nunca de lo que manda el navegador), así un estudio no
 *    puede ver archivos de otro. La "causa" es un identificador interno de la
 *    app (modo rápido: las causas viven en el bloque de estado, no en tablas),
 *    por eso NO se valida contra una tabla de causas.
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

/* Deja el id de causa apto para usar como nombre de carpeta (sin caracteres raros). */
function archivos_causa_safe($causaId) {
  $s = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$causaId);
  return substr($s, 0, 64);
}

/* Carpetas donde se clasifica cada documento (mismas que muestra la app). */
function archivos_carpetas() { return ['prueba','escritos','actuaciones','resoluciones','sentencias']; }
function archivos_carpeta_ok($c) {
  $c = strtolower(trim((string)$c));
  return in_array($c, archivos_carpetas(), true) ? $c : 'actuaciones';
}

/* Crea la tabla de archivos si todavía no existe (auto-instala). */
function asegurar_tabla_archivos() {
  db()->exec("CREATE TABLE IF NOT EXISTS archivos (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    estudio_id      INT UNSIGNED NOT NULL,
    causa_id        VARCHAR(64)  NOT NULL,
    carpeta         VARCHAR(24)  NOT NULL DEFAULT 'actuaciones',
    nombre          VARCHAR(255) NOT NULL,
    archivo         VARCHAR(120) NOT NULL,
    tipo            VARCHAR(12)  NOT NULL,
    tamano          INT UNSIGNED NOT NULL DEFAULT 0,
    visible_cliente TINYINT(1)   NOT NULL DEFAULT 0,
    subido_por      INT UNSIGNED NULL,
    creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_archivos_causa (causa_id),
    KEY idx_archivos_estudio (estudio_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  /* Migración: si una versión previa creó causa_id como entero, pasarlo a texto. */
  try {
    $col = db()->query("SHOW COLUMNS FROM archivos LIKE 'causa_id'")->fetch();
    if ($col && stripos((string)$col['Type'], 'int') !== false) {
      db()->exec("ALTER TABLE archivos MODIFY causa_id VARCHAR(64) NOT NULL");
    }
  } catch (Throwable $e) { /* silencioso */ }

  /* Migración: agregar la columna carpeta si la tabla ya existía sin ella. */
  try {
    $col = db()->query("SHOW COLUMNS FROM archivos LIKE 'carpeta'")->fetch();
    if (!$col) {
      db()->exec("ALTER TABLE archivos ADD COLUMN carpeta VARCHAR(24) NOT NULL DEFAULT 'actuaciones' AFTER causa_id");
    }
  } catch (Throwable $e) { /* silencioso */ }
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

/* Busca un archivo por id y verifica que sea del MISMO estudio de la persona. */
function archivo_del_estudio($id, $u) {
  $st = db()->prepare('SELECT * FROM archivos WHERE id = ?');
  $st->execute([$id]);
  $a = $st->fetch();
  if (!$a) json_error('Archivo no encontrado.', 404);
  if ((int)$a['estudio_id'] !== (int)$u['estudio_id']) json_error('No tenés acceso a este archivo.', 403);
  return $a;
}

/* Subir un archivo (los datos vienen como formulario multipart). */
function archivo_subir() {
  $u = require_profesional();
  $causaId = archivos_causa_safe($_POST['causa_id'] ?? '');
  if ($causaId === '') json_error('Falta indicar la causa.');

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
  $carpeta = archivos_carpeta_ok($_POST['carpeta'] ?? 'actuaciones');

  db()->prepare('INSERT INTO archivos (estudio_id, causa_id, carpeta, nombre, archivo, tipo, tamano, visible_cliente, subido_por)
                 VALUES (?,?,?,?,?,?,?,?,?)')
      ->execute([$estudioId, $causaId, $carpeta, $nombre, $stored, $ext, $size, $visible, $u['id']]);
  json_ok(['id' => (int)db()->lastInsertId(), 'nombre' => $nombre], 201);
}

/* Listar archivos de una causa (siempre acotado al estudio de la persona). */
function archivos_listar() {
  $u = require_login();
  $causaId = archivos_causa_safe($_GET['causa'] ?? '');
  if ($causaId === '') json_error('Falta indicar la causa.');
  $st = db()->prepare('SELECT id, carpeta, nombre, tipo, tamano, visible_cliente, creado_en
                       FROM archivos WHERE causa_id = ? AND estudio_id = ? ORDER BY creado_en DESC');
  $st->execute([$causaId, (int)$u['estudio_id']]);
  $rows = $st->fetchAll();
  if ($u['rol'] === 'cliente') {
    $rows = array_values(array_filter($rows, function ($r) { return (int)$r['visible_cliente'] === 1; }));
  }
  json_ok($rows);
}

/* Descargar / ver un archivo (verifica permiso y entrega el archivo). */
function archivo_descargar($id) {
  $u = require_login();
  $a = archivo_del_estudio($id, $u);
  if ($u['rol'] === 'cliente' && (int)$a['visible_cliente'] !== 1) json_error('No tenés acceso a este archivo.', 403);

  $path = archivos_base() . '/' . (int)$a['estudio_id'] . '/' . archivos_causa_safe($a['causa_id']) . '/' . $a['archivo'];
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
  $u = require_profesional();
  archivo_del_estudio($id, $u);

  $sets = []; $vals = [];
  if (field('nombre', '__NO__') !== '__NO__') {
    $n = trim((string)field('nombre'));
    if ($n !== '') { $sets[] = 'nombre = ?'; $vals[] = $n; }
  }
  if (field('visible_cliente', '__NO__') !== '__NO__') {
    $sets[] = 'visible_cliente = ?'; $vals[] = field('visible_cliente') ? 1 : 0;
  }
  if (field('carpeta', '__NO__') !== '__NO__') {
    $sets[] = 'carpeta = ?'; $vals[] = archivos_carpeta_ok(field('carpeta'));
  }
  if (!$sets) json_error('No hay nada para actualizar.');
  $vals[] = $id;
  db()->prepare('UPDATE archivos SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
  json_ok(['actualizado' => true]);
}

/* Eliminar el archivo (de la base y del disco). */
function archivo_eliminar($id) {
  $u = require_profesional();
  $a = archivo_del_estudio($id, $u);
  $path = archivos_base() . '/' . (int)$a['estudio_id'] . '/' . archivos_causa_safe($a['causa_id']) . '/' . $a['archivo'];
  if (is_file($path)) @unlink($path);
  db()->prepare('DELETE FROM archivos WHERE id = ?')->execute([$id]);
  json_ok(['eliminado' => true]);
}
