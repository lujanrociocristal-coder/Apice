<?php
/* ============================================================================
 *  TEST DEL ROUTER index.php
 *  https://abogadoscatamarca.com/api/router-test.php
 *  BORRÁ ESTE ARCHIVO CUANDO TERMINES.
 * ========================================================================== */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h2>Prueba del Router de API</h2><hr>";

// 1. Ver si mod_rewrite está activo
echo "<h3>1. Estado de mod_rewrite</h3>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        echo "<p style='color:green;'>✔️ mod_rewrite está ACTIVO.</p>";
    } else {
        echo "<p style='color:red; font-weight:bold;'>❌ mod_rewrite NO está cargado. Eso explica los errores 500.</p>";
    }
} else {
    echo "<p style='color:orange;'>⚠️ No se puede verificar mod_rewrite (apache_get_modules no disponible). Esto es normal en Hostinger.</p>";
}

// 2. Simular una llamada al router cargando index.php directamente
echo "<h3>2. Carga directa de index.php (simulación)</h3>";
echo "<p>Intentando incluir el router con variables simuladas de /api/auth/me...</p>";

// Simular las variables de servidor que usaría el router
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/auth/me';

ob_start();
$error = null;
try {
    require __DIR__ . '/index.php';
} catch (Throwable $e) {
    $error = $e;
}
$output = ob_get_clean();

if ($error) {
    echo "<p style='color:red; font-weight:bold;'>❌ Error al cargar index.php:</p>";
    echo "<pre style='background:#FBEBEB; color:#8A2828; padding:15px; border-radius:9px; font-family:monospace;'>";
    echo htmlspecialchars((string)$error);
    echo "</pre>";
} else {
    echo "<p style='color:green;'>✔️ index.php cargó y ejecutó sin error.</p>";
    echo "<p><strong>Respuesta generada:</strong></p>";
    echo "<pre style='background:#EFF6F1; padding:12px; border-radius:9px; font-family:monospace;'>";
    echo htmlspecialchars($output);
    echo "</pre>";
}

// 3. Verificar el contenido del .htaccess en el servidor
echo "<h3>3. Contenido del .htaccess del servidor</h3>";
$htpath = __DIR__ . '/.htaccess';
if (file_exists($htpath)) {
    echo "<pre style='background:#F5F5F5; padding:12px; border-radius:9px; font-size:12px;'>";
    echo htmlspecialchars(file_get_contents($htpath));
    echo "</pre>";
} else {
    echo "<p style='color:red;'>❌ No existe el .htaccess en la carpeta api/.</p>";
}
