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

$claves = $pdo->query('SELECT clave FROM estado_app')->fetchAll(PDO::FETCH_COLUMN);

$muestras = [];
foreach ($claves as $clave) {
    $st = $pdo->prepare('SELECT valor FROM estado_app WHERE clave = ?');
    $st->execute([$clave]);
    $raw = $st->fetchColumn();
    $decoded = json_decode($raw, true);

    if (is_array($decoded) && !empty($decoded)) {
        $primer = array_values($decoded)[0];
        $muestras[$clave] = [
            'tipo'   => 'array',
            'total'  => count($decoded),
            'keys'   => is_array($primer) ? array_keys($primer) : gettype($primer),
            'sample' => $primer
        ];
    } elseif (is_array($decoded)) {
        $muestras[$clave] = ['tipo' => 'array_vacio', 'total' => 0];
    } elseif (is_string($decoded) || is_numeric($decoded)) {
        $muestras[$clave] = ['tipo' => 'escalar', 'valor' => $decoded];
    } else {
        $muestras[$clave] = ['tipo' => 'otro', 'raw_inicio' => substr($raw, 0, 200)];
    }
}

echo json_encode(['ok' => true, 'claves' => $muestras], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
