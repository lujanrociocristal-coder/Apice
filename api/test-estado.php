<?php
/* ============================================================================
 *  TEST DE ENDPOINT /api/estado/
 *  Subilo a: public_html/api/test-estado.php
 *  Abrilo en el navegador: https://abogadoscatamarca.com/api/test-estado.php
 *  MUY IMPORTANTE: cuando termines, BORRÁ este archivo.
 * ========================================================================== */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h2>Prueba de /api/estado/</h2>";

try {
    require __DIR__ . '/lib/respond.php';
    require __DIR__ . '/lib/db.php';
    require __DIR__ . '/lib/auth.php';

    start_secure_session();

    echo "<p><strong>ID de sesión:</strong> " . session_id() . "</p>";
    echo "<p><strong>Contenido de \$_SESSION:</strong></p><pre>";
    print_r($_SESSION);
    echo "</pre>";

    require __DIR__ . '/routes/estado.php';

    echo "<p><strong>Llamando a handle_estado('GET', ['gestor_cfg_v9'])...</strong></p>";
    
    // Ejecutamos la función de la ruta directamente
    handle_estado('GET', ['gestor_cfg_v9']);
    
} catch (Throwable $e) {
    echo "<h3 style='color:red;'>Ocurrió un error (Throwable):</h3>";
    echo "<pre style='background:#FBEBEB; color:#8A2828; padding:15px; border:1px solid #D3D7DE; border-radius:9px; font-family:monospace; font-size:13px; overflow:auto;'>" . htmlspecialchars((string)$e) . "</pre>";
}
