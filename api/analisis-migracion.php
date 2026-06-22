<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$cfg = require __DIR__ . '/config.php';
$dsn = "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset=utf8mb4";
$pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

header('Content-Type: application/json');

// Listar todas las claves existentes
$claves = $pdo->query('SELECT clave, LENGTH(valor) as bytes, SUBSTRING(valor,1,2) as tipo FROM estado_app')->fetchAll();

$muestras = [];
foreach ($claves as $row) {
    $st = $pdo->prepare('SELECT valor FROM estado_app WHERE clave = ?');
    $st->execute([$row['clave']]);
    $raw = $st->fetchColumn();
    $arr = json_decode($raw, true);
    
    if (is_array($arr)) {
        $primer = !empty($arr) ? array_values($arr)[0] : null;
        $muestras[$row['clave']] = [
            'total' => count($arr),
            'keys'  => $primer ? array_keys($primer) : [],
            'sample'=> $primer
        ];
    } elseif (is_object(json_decode($raw))) {
        $obj = json_decode($raw, true);
        $muestras[$row['clave']] = [
            'tipo'  => 'objeto',
            'keys'  => array_keys($obj),
            'sample'=> $obj
        ];
    } else {
        $muestras[$row['clave']] = ['tipo' => 'otro', 'bytes' => $row['bytes']];
    }
}

echo json_encode(['ok' => true, 'claves' => $muestras], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
