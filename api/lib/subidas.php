<?php
/* ============================================================================
 *  VALIDACION DE ARCHIVOS SUBIDOS  (v46)
 *
 *  Por que existe: antes solo se miraba el NOMBRE del archivo. Si terminaba
 *  en ".pdf" se aceptaba, aunque por dentro fuera otra cosa. Ahora ademas se
 *  revisa el CONTENIDO real (los primeros bytes dicen que es de verdad).
 *
 *  Criterio elegido, a proposito:
 *   - Se RECHAZA siempre lo claramente peligroso (php, html, ejecutables).
 *   - PDF e imagenes: se exige que el contenido coincida con la extension.
 *   - Word (doc/docx): se aceptan las variantes conocidas, porque los .docx
 *     son en realidad archivos comprimidos y los .doc usan formatos viejos.
 *     Se prefiere ser permisivo antes que rechazar un archivo legitimo.
 * ========================================================================== */

/* Tipos que NUNCA se aceptan, sin importar la extension. */
function subidas_prohibidos() {
  return [
    'text/x-php', 'application/x-httpd-php', 'application/x-php',
    'text/html', 'application/xhtml+xml',
    'application/x-executable', 'application/x-dosexec',
    'application/x-mach-binary', 'application/x-sharedlib',
    'application/x-msdownload', 'application/javascript', 'text/javascript',
    'application/x-shellscript', 'text/x-shellscript',
  ];
}

/* Contenidos aceptados para cada extension. */
function subidas_esperados($ext) {
  switch ($ext) {
    case 'pdf':  return ['application/pdf'];
    case 'jpg':
    case 'jpeg': return ['image/jpeg'];
    case 'png':  return ['image/png'];
    case 'docx': return [
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/zip', 'application/octet-stream',
    ];
    case 'doc':  return [
      'application/msword', 'application/vnd.ms-office',
      'application/x-ole-storage', 'application/CDFV2',
      'application/octet-stream',
    ];
  }
  return [];
}

/* Detecta el tipo real. Devuelve '' si el servidor no tiene la extension
   finfo (en ese caso no se bloquea nada, para no romper la carga). */
function subidas_tipo_real($rutaTmp) {
  if (!function_exists('finfo_open')) return '';
  $fi = @finfo_open(FILEINFO_MIME_TYPE);
  if (!$fi) return '';
  $mime = @finfo_file($fi, $rutaTmp);
  @finfo_close($fi);
  return is_string($mime) ? strtolower($mime) : '';
}

/* Devuelve '' si esta todo bien, o el texto del error si hay que rechazarlo. */
function subidas_validar($rutaTmp, $ext) {
  $mime = subidas_tipo_real($rutaTmp);
  if ($mime === '') return ''; // no se pudo determinar: se deja pasar

  if (in_array($mime, subidas_prohibidos(), true)) {
    return 'Ese archivo no se puede subir por seguridad (el contenido no corresponde a un documento).';
  }
  $ok = subidas_esperados($ext);
  if (!empty($ok) && !in_array($mime, $ok, true)) {
    return 'El contenido del archivo no coincide con su extension (.' . $ext . '). Revisa que el archivo no este dañado o renombrado.';
  }
  return '';
}
