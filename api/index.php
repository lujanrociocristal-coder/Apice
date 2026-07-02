<?php
/* ============================================================================
 *  ROUTER (el "recepcionista" de la API)
 *  Recibe TODOS los pedidos y los manda al archivo correcto según el tema.
 *  Ej:  GET /api/causas        -> routes/causas.php
 *       POST /api/auth/login   -> routes/auth.php
 * ========================================================================== */

error_reporting(E_ALL);
ini_set('display_errors', '0');   // no mostrar errores técnicos al usuario

require __DIR__ . '/lib/respond.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/auth.php';

start_secure_session();

// --- Averiguar qué se pidió (tema + método) ---
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Quitar todo lo anterior a "/api/" para quedarnos con el camino interno.
$pos = strpos($uri, '/api/');
$path = $pos !== false ? substr($uri, $pos + 5) : ltrim($uri, '/');
$segments = array_values(array_filter(explode('/', $path), 'strlen'));

$recurso = $segments[0] ?? '';
$resto   = array_slice($segments, 1);   // ej: ["login"] o ["123"]

// --- Mapa de temas -> archivos ---
$rutas = [
  'auth'        => 'auth.php',
  'causas'      => 'causas.php',
  'clientes'    => 'clientes.php',
  'documentos'  => 'documentos.php',
  'tareas'      => 'tareas.php',
  'audiencias'  => 'audiencias.php',
  'honorarios'  => 'honorarios.php',
  'recibos'     => 'recibos.php',
  'convenios'   => 'convenios.php',
  'guia'        => 'guia.php',
  'config'      => 'config.php',
  'estado'      => 'estado.php',  // estado de la app por estudio (conexión rápida)
  'usuarios'    => 'usuarios.php',// gestión de usuarios y claves (solo admin)
  'archivos'    => 'archivos.php',// archivos adjuntos reales (PDF, Word, imágenes)
  'compartir'   => 'compartir.php',// compartir una causa con un abogado externo
  'acceso'      => 'acceso.php',  // portal de clientes (acceso por causa)
  'avisos'      => 'avisos.php',  // novedades automáticas (docs nuevos, primer ingreso)
  'push'        => 'push.php',    // notificaciones al celular (Web Push / VAPID)
  'ia'          => 'ia.php',      // etapa 2 (preparado)
];

if (!isset($rutas[$recurso])) {
  json_error('Recurso no encontrado: ' . htmlspecialchars($recurso), 404);
}

$archivo = __DIR__ . '/routes/' . $rutas[$recurso];
if (!file_exists($archivo)) json_error('Falta el archivo de ruta en el servidor.', 500);
require $archivo;

// Cada archivo de ruta define una función handle_<tema>($method, $resto).
$fn = 'handle_' . $recurso;
if (!function_exists($fn)) json_error('Ruta mal configurada.', 500);

try {
  $fn($method, $resto);
} catch (Throwable $e) {
  // Log interno (no se muestra al usuario).
  error_log('[APICE] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
  json_error('Error interno del servidor. Intentá de nuevo.', 500);
}