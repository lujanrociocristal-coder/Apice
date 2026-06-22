<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: application/json');

$paths = [
    __DIR__ . '/config.php',
    dirname(__DIR__) . '/config.php',
    dirname(dirname(__DIR__)) . '/config.php'
];

$found = false;
$cfgPath = '';
foreach ($paths as $p) {
    if (file_exists($p)) {
        $found = true;
        $cfgPath = $p;
        break;
    }
}

if (!$found) {
    echo json_encode(['ok' => false, 'error' => 'config.php no encontrado en ninguna ruta', 'buscado' => $paths]);
    exit;
}

$cfg = require $cfgPath;
$dsn = "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

    $st = $pdo->prepare('SELECT clave, valor FROM estado_app WHERE clave IN ("gestor_cli_v1", "gestor_causas_v6", "gestor_aud_v1", "gestor_dir_v1") LIMIT 4');
    $st->execute();
    
    $muestras = [];
    while ($row = $st->fetch()) {
        $arr = json_decode($row['valor'], true);
        if (is_array($arr) && count($arr) > 0) {
            $muestras[$row['clave']] = [
                'total_records' => count($arr),
                'sample' => $arr[0] ?? null
            ];
        } else {
            $muestras[$row['clave']] = 'Vacío o no es array';
        }
    }
    
    echo json_encode(['ok' => true, 'config_path' => $cfgPath, 'muestras' => $muestras], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'config_path' => $cfgPath, 'error' => $e->getMessage()]);
}
