<?php
/* ============================================================================
 *  DIAGNÓSTICO COMPLETO DE API
 *  Subilo a: public_html/api/diag.php
 *  https://abogadoscatamarca.com/api/diag.php
 *  BORRÁ ESTE ARCHIVO CUANDO TERMINES.
 * ========================================================================== */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h2>Diagnóstico Completo de API - ÁPICE</h2>";
echo "<hr>";

// 1. Verificar la versión de db.php en el servidor
echo "<h3>1. Contenido actual de db.php en el servidor</h3>";
$dbphp = file_get_contents(__DIR__ . '/lib/db.php');
$match = [];
if (preg_match('/\$cfgPath\s*=\s*(.+);/', $dbphp, $match)) {
    echo "<p><strong>Línea de \$cfgPath encontrada:</strong> <code>" . htmlspecialchars($match[0]) . "</code></p>";
    if (strpos($match[0], 'dirname') !== false) {
        echo "<p style='color:green;'>✔️ db.php tiene el fix con <strong>dirname()</strong>.</p>";
    } else {
        echo "<p style='color:red; font-weight:bold;'>❌ db.php NO tiene el fix con dirname(). Todavía usa la ruta relativa antigua. El deploy no actualizó el archivo.</p>";
        echo "<p><strong>Solución:</strong> Subí manualmente el archivo <code>api/lib/db.php</code> de tu computadora al servidor a través del Administrador de Archivos de Hostinger.</p>";
    }
} else {
    echo "<p style='color:orange;'>⚠️ No se pudo leer la línea de \$cfgPath en db.php.</p>";
}

// 2. Verificar dónde está config.php
echo "<h3>2. Ubicación de config.php</h3>";
$paths = [
    __DIR__ . '/config.php',
    dirname(__DIR__) . '/config.php',
    __DIR__ . '/../config.php',
];
foreach ($paths as $p) {
    $real = realpath($p) ?: $p;
    $exists = file_exists($p) ? "✔️ EXISTE" : "❌ NO existe";
    $color = file_exists($p) ? "green" : "red";
    echo "<p style='color:$color;'>$exists &rarr; <code>$p</code></p>";
}

// 3. Intentar cargar config.php y conectar a la base de datos directamente
echo "<h3>3. Prueba de conexión directa a la base de datos</h3>";
$cfgPath = __DIR__ . '/config.php';
if (file_exists($cfgPath)) {
    $cfg = require $cfgPath;
    $dsn = "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo "<p style='color:green;'>✔️ Conexión a la base de datos exitosa.</p>";
        
        // Verificar si el usuario existe
        $st = $pdo->prepare('SELECT id, nombre, email, rol, activo FROM usuarios WHERE email = ?');
        $st->execute(['lujanrociocristal@gmail.com']);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if ($u) {
            echo "<p style='color:green;'>✔️ El usuario <strong>lujanrociocristal@gmail.com</strong> existe en la base de datos.</p>";
            echo "<ul><li>ID: {$u['id']}</li><li>Nombre: {$u['nombre']}</li><li>Rol: {$u['rol']}</li><li>Activo: {$u['activo']}</li></ul>";
        } else {
            echo "<p style='color:red; font-weight:bold;'>❌ El usuario lujanrociocristal@gmail.com <strong>NO</strong> existe. Aún no has ejecutado crear-admin.php.</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color:red; font-weight:bold;'>❌ Error de base de datos: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p style='color:red; font-weight:bold;'>❌ config.php no encontrado en <code>$cfgPath</code>. Subilo manualmente.</p>";
}

// 4. Verificar deploy - fecha de modificación de db.php
echo "<h3>4. Fecha de modificación de archivos clave</h3>";
$files = [
    'api/lib/db.php'   => __DIR__ . '/lib/db.php',
    'api/index.php'    => __DIR__ . '/index.php',
    'api/lib/auth.php' => __DIR__ . '/lib/auth.php',
];
foreach ($files as $label => $path) {
    if (file_exists($path)) {
        echo "<p>📄 <strong>$label</strong> — última modificación: <strong>" . date('Y-m-d H:i:s', filemtime($path)) . "</strong></p>";
    }
}
