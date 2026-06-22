<?php
/* ============================================================================
 *  VERIFICACIÓN DE ARCHIVO CONFIG.PHP
 *  Subilo a: public_html/api/check-file.php
 *  Abrilo en el navegador: https://abogadoscatamarca.com/api/check-file.php
 *  MUY IMPORTANTE: cuando termines, BORRÁ este archivo.
 * ========================================================================== */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h2>Verificación de config.php</h2>";

$path = __DIR__ . '/config.php';
echo "<p><strong>Intentando cargar:</strong> <code>$path</code></p>";

if (file_exists($path)) {
    echo "<p style='color:green;'>✔️ file_exists() -> <strong>EXISTE</strong></p>";
    if (is_readable($path)) {
        echo "<p style='color:green;'>✔️ is_readable() -> <strong>LEÍBLE</strong></p>";
        echo "<p><strong>Tamaño:</strong> " . filesize($path) . " bytes</p>";
        try {
            $res = require $path;
            echo "<p style='color:green;'>✔️ require() -> <strong>EXITOSO</strong></p>";
            echo "<p><strong>Campos cargados:</strong> " . implode(', ', array_keys($res)) . "</p>";
        } catch (Throwable $e) {
            echo "<p style='color:red; font-weight:bold;'>❌ ERROR al hacer require: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    } else {
        echo "<p style='color:red; font-weight:bold;'>❌ ERROR: El archivo existe pero NO es leíble por el servidor (revisa los permisos chmod, debe ser 644).</p>";
    }
} else {
    echo "<p style='color:red; font-weight:bold;'>❌ ERROR: El archivo NO existe en esta ruta.</p>";
}
