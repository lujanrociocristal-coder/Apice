<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/respond.php';

header('Content-Type: application/json');

try {
    $pdo = db();
    $st = $pdo->prepare('SELECT clave, valor FROM estado_app WHERE clave IN ("gestor_cli_v1", "gestor_causas_v6", "gestor_aud_v1", "gestor_dir_v1") LIMIT 4');
    $st->execute();
    
    $muestras = [];
    while ($row = $st->fetch()) {
        $arr = json_decode($row['valor'], true);
        if (is_array($arr) && count($arr) > 0) {
            // Tomar el primer elemento como muestra de estructura
            $muestras[$row['clave']] = [
                'total_records' => count($arr),
                'sample' => $arr[0] ?? null
            ];
        } else {
            $muestras[$row['clave']] = 'Vacío o no es array';
        }
    }
    
    echo json_encode(['ok' => true, 'muestras' => $muestras], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
