<?php
/* ============================================================================
 *  DIAGNÓSTICO DE CONEXIÓN Y TABLAS DE LA BASE DE DATOS
 *  Subilo a: public_html/api/db-check.php
 *  Abrilo en el navegador: https://abogadoscatamarca.com/api/db-check.php
 *  MUY IMPORTANTE: cuando termines el diagnóstico, BORRÁ este archivo.
 * ========================================================================== */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h2>Diagnóstico de Base de Datos - ÁPICE</h2>";

// 1. Verificar config.php
$cfgPath = __DIR__ . '/config.php';
if (!file_exists($cfgPath)) {
    die("<p style='color:red; font-weight:bold;'>❌ ERROR: El archivo config.php no existe en la carpeta /api/. Asegúrate de haberlo subido.</p>");
}
echo "<p style='color:green;'>✔️ El archivo <strong>config.php</strong> existe.</p>";

$cfg = require $cfgPath;

// 2. Conectar a MySQL
$dsn = "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "<p style='color:green;'>✔️ Conexión a MySQL exitosa.</p>";
} catch (PDOException $e) {
    die("<p style='color:red; font-weight:bold;'>❌ ERROR de Conexión a MySQL: " . htmlspecialchars($e->getMessage()) . "<br>Revisá los datos (host, db_name, db_user, db_pass) en tu <strong>config.php</strong>.</p>");
}

// 3. Verificar tablas
$tablas_esperadas = ['estudios', 'usuarios', 'estado_app', 'feriados'];
$faltanTablas = false;

foreach ($tablas_esperadas as $tabla) {
    try {
        $st = $pdo->query("SELECT 1 FROM `$tabla` LIMIT 1");
        echo "<p style='color:green;'>✔️ La tabla <strong>$tabla</strong> existe en la base de datos.</p>";
    } catch (PDOException $e) {
        echo "<p style='color:red; font-weight:bold;'>❌ ERROR: La tabla <strong>$tabla</strong> NO existe en la base de datos.</p>";
        $faltanTablas = true;
    }
}

if ($faltanTablas) {
    echo "<p style='color:orange; font-weight:bold;'>⚠️ Solución: Debes importar el archivo database/schema.sql en tu base de datos desde phpMyAdmin en Hostinger.</p>";
} else {
    echo "<p style='color:green; font-weight:bold;'>🎉 ¡Todas las tablas están listas!</p>";
}
