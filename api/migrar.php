<?php
/* ============================================================================
 * SCRIPT DE MIGRACIÓN: FASE 1 (JSON) -> FASE 2 (RELACIONAL)
 * Lee los bloques de la tabla estado_app y los inserta en las tablas SQL.
 * ========================================================================== */

error_reporting(E_ALL);
ini_set('display_errors', '1');
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/respond.php';

// Solo permitir si está logueado como profesional
$u = require_profesional();
$eid = (int)$u['estudio_id'];
$pdo = db();

try {
    $pdo->beginTransaction();

    // 1. Asegurar que existan las columnas uuid para mapeo de frontend
    $pdo->exec("ALTER TABLE clientes ADD COLUMN IF NOT EXISTS uuid VARCHAR(80) NULL AFTER id, ADD UNIQUE KEY IF NOT EXISTS uq_clientes_uuid (uuid)");
    $pdo->exec("ALTER TABLE causas ADD COLUMN IF NOT EXISTS uuid VARCHAR(80) NULL AFTER id, ADD UNIQUE KEY IF NOT EXISTS uq_causas_uuid (uuid)");

    $resultados = ['clientes_insertados' => 0, 'causas_insertadas' => 0];

    // 2. MIGRAR CLIENTES
    $stCli = $pdo->prepare('SELECT valor FROM estado_app WHERE estudio_id = ? AND clave = "gestor_cli_v1"');
    $stCli->execute([$eid]);
    $cliJson = $stCli->fetchColumn();
    
    $mapaClientes = []; // uuid (front) => id (db)

    if ($cliJson) {
        $clientesArray = json_decode($cliJson, true);
        if (is_array($clientesArray)) {
            $insCli = $pdo->prepare('INSERT IGNORE INTO clientes (estudio_id, uuid, nombre, dni_cuit, email, telefono, domicilio, notas) VALUES (?,?,?,?,?,?,?,?)');
            foreach ($clientesArray as $c) {
                // frontend structure: {id, nombre, dni, correo, tel, dir, notas}
                if (!isset($c['id'])) continue;
                $uuid = $c['id'];
                $nombre = $c['nombre'] ?? 'Sin Nombre';
                $dni = $c['dni'] ?? null;
                $email = $c['correo'] ?? null;
                $tel = $c['tel'] ?? null;
                $dir = $c['dir'] ?? null;
                $notas = $c['notas'] ?? null;

                $insCli->execute([$eid, $uuid, $nombre, $dni, $email, $tel, $dir, $notas]);
                $resultados['clientes_insertados']++;
            }
        }
    }

    // Cargar mapa de clientes para enlazar causas
    $stIds = $pdo->prepare('SELECT id, uuid FROM clientes WHERE estudio_id = ? AND uuid IS NOT NULL');
    $stIds->execute([$eid]);
    while ($r = $stIds->fetch()) {
        $mapaClientes[$r['uuid']] = $r['id'];
    }

    // 3. MIGRAR CAUSAS
    $stCau = $pdo->prepare('SELECT valor FROM estado_app WHERE estudio_id = ? AND clave = "gestor_causas_v6"');
    $stCau->execute([$eid]);
    $cauJson = $stCau->fetchColumn();

    if ($cauJson) {
        $causasArray = json_decode($cauJson, true);
        if (is_array($causasArray)) {
            $insCau = $pdo->prepare('INSERT IGNORE INTO causas (estudio_id, owner_id, uuid, estado, caratula, cliente_nombre, expediente, objeto, fuero, juzgado, juez, secretaria, letrada, posicion, actor_rol, actor, demandado_rol, demandado, materias) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            
            $insMov = $pdo->prepare('INSERT INTO movimientos (causa_id, fecha_txt, texto, inicio) VALUES (?,?,?,?)');

            foreach ($causasArray as $c) {
                if (!isset($c['id'])) continue;
                $uuid = $c['id'];
                $estado = $c['estado'] ?? 'preparacion';
                $caratula = $c['caratula'] ?? 'Sin Carátula';
                $clienteNom = $c['cliente'] ?? null;
                $exp = $c['expediente'] ?? null;
                $obj = $c['objeto'] ?? null;
                $fuero = $c['fuero'] ?? null;
                $juz = $c['juzgado'] ?? null;
                $juez = $c['juez'] ?? null;
                $sec = $c['secretaria'] ?? null;
                $let = $c['letrada'] ?? null;
                $pos = $c['posicion'] ?? null;
                $actRol = $c['actorRol'] ?? null;
                $act = $c['actor'] ?? null;
                $demRol = $c['demandadoRol'] ?? null;
                $dem = $c['demandado'] ?? null;
                $mat = isset($c['materia']) ? json_encode($c['materia'], JSON_UNESCAPED_UNICODE) : null;

                $insCau->execute([
                    $eid, $u['id'], $uuid, $estado, $caratula, $clienteNom, $exp, $obj, $fuero, $juz, $juez, $sec, $let, $pos, $actRol, $act, $demRol, $dem, $mat
                ]);
                
                // Si la insertó (INSERT IGNORE)
                if ($insCau->rowCount() > 0) {
                    $resultados['causas_insertadas']++;
                    $causa_id = $pdo->lastInsertId();

                    // Migrar movimientos (bitacora)
                    if (isset($c['bitacora']) && is_array($c['bitacora'])) {
                        foreach ($c['bitacora'] as $mov) {
                            $fecha = $mov['fecha'] ?? '';
                            $texto = $mov['texto'] ?? '';
                            $inicio = empty($mov['inicio']) ? 0 : 1;
                            $insMov->execute([$causa_id, $fecha, $texto, $inicio]);
                        }
                    }
                }
            }
        }
    }

    $pdo->commit();
    json_ok(['migracion' => 'exitosa', 'resultados' => $resultados]);

} catch (Exception $e) {
    $pdo->rollBack();
    json_error('Error en migración: ' . $e->getMessage());
}
