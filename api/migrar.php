<?php
/* ============================================================================
 * SCRIPT DE MIGRACIÓN: FASE 1 (JSON) -> FASE 2 (RELACIONAL)
 * Lee los bloques de la tabla estado_app y los inserta en las tablas SQL.
 * ========================================================================== */

error_reporting(E_ALL);
ini_set('display_errors', '1');
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/respond.php';
require __DIR__ . '/lib/auth.php';

// Iniciar sesión manualmente (este script se llama directo, no pasa por index.php)
start_secure_session();

// Solo permitir si está logueado como profesional
$u = require_profesional();
$eid = (int)$u['estudio_id'];
$pdo = db();

// Agrega columna uuid si no existe (sin usar IF NOT EXISTS en KEY)
function agregar_columna_uuid($pdo, $tabla) {
    $st = $pdo->query("SHOW COLUMNS FROM `{$tabla}` LIKE 'uuid'");
    if ($st->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `{$tabla}` ADD COLUMN `uuid` VARCHAR(80) NULL");
    }
    // Agregar índice único si no existe
    $st2 = $pdo->query("SHOW INDEX FROM `{$tabla}` WHERE Key_name = 'uq_{$tabla}_uuid'");
    if ($st2->rowCount() === 0) {
        // Usar ALTER IGNORE para no fallar si hay duplicados vacíos
        $pdo->exec("ALTER TABLE `{$tabla}` ADD UNIQUE KEY `uq_{$tabla}_uuid` (`uuid`)");
    }
}

$inTransaction = false;
try {
    // Paso 1: Modificar esquema FUERA de la transacción (DDL hace auto-commit)
    agregar_columna_uuid($pdo, 'clientes');
    agregar_columna_uuid($pdo, 'causas');

    // Paso 2: Migrar datos DENTRO de la transacción
    $pdo->beginTransaction();
    $inTransaction = true;

    $resultados = ['clientes_insertados' => 0, 'causas_insertadas' => 0];

    // ── MIGRAR CLIENTES ──────────────────────────────────────────────────────
    $stCli = $pdo->prepare('SELECT valor FROM estado_app WHERE estudio_id = ? AND clave = "gestor_cli_v1"');
    $stCli->execute([$eid]);
    $cliJson = $stCli->fetchColumn();

    if ($cliJson) {
        $clientesArray = json_decode($cliJson, true);
        if (is_array($clientesArray)) {
            $insCli = $pdo->prepare(
                'INSERT INTO clientes (estudio_id, uuid, nombre, dni_cuit, email, telefono, domicilio, notas)
                 VALUES (?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE nombre=VALUES(nombre)'
            );
            foreach ($clientesArray as $c) {
                if (!isset($c['id'])) continue;
                $insCli->execute([
                    $eid,
                    $c['id'],
                    $c['nombre'] ?? 'Sin Nombre',
                    $c['dni']    ?? null,
                    $c['correo'] ?? null,
                    $c['tel']    ?? null,
                    $c['dir']    ?? null,
                    $c['notas']  ?? null,
                ]);
                if ($insCli->rowCount() > 0) $resultados['clientes_insertados']++;
            }
        }
    }

    // ── MIGRAR CAUSAS ────────────────────────────────────────────────────────
    $stCau = $pdo->prepare('SELECT valor FROM estado_app WHERE estudio_id = ? AND clave = "gestor_causas_v6"');
    $stCau->execute([$eid]);
    $cauJson = $stCau->fetchColumn();

    if ($cauJson) {
        $causasArray = json_decode($cauJson, true);
        if (is_array($causasArray)) {
            $insCau = $pdo->prepare(
                'INSERT INTO causas
                   (estudio_id, owner_id, uuid, estado, caratula, cliente_nombre,
                    expediente, objeto, fuero, juzgado, juez, secretaria,
                    letrada, posicion, actor_rol, actor, demandado_rol, demandado, materias)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE estado=VALUES(estado), caratula=VALUES(caratula)'
            );
            $insMov = $pdo->prepare(
                'INSERT INTO movimientos (causa_id, fecha_txt, texto, inicio) VALUES (?,?,?,?)'
            );

            foreach ($causasArray as $c) {
                if (!isset($c['id'])) continue;
                $mat = isset($c['materia']) ? json_encode($c['materia'], JSON_UNESCAPED_UNICODE) : null;

                $insCau->execute([
                    $eid,             $u['id'],           $c['id'],
                    $c['estado']       ?? 'preparacion',  $c['caratula']    ?? 'Sin Carátula',
                    $c['cliente']      ?? null,            $c['expediente']  ?? null,
                    $c['objeto']       ?? null,            $c['fuero']       ?? null,
                    $c['juzgado']      ?? null,            $c['juez']        ?? null,
                    $c['secretaria']   ?? null,            $c['letrada']     ?? null,
                    $c['posicion']     ?? null,            $c['actorRol']    ?? null,
                    $c['actor']        ?? null,            $c['demandadoRol'] ?? null,
                    $c['demandado']    ?? null,            $mat,
                ]);

                // Buscar el id real de la causa (por uuid)
                $stId = $pdo->prepare('SELECT id FROM causas WHERE uuid = ?');
                $stId->execute([$c['id']]);
                $causa_id = $stId->fetchColumn();

                if ($causa_id) {
                    $resultados['causas_insertadas']++;
                    // Reimportar bitácora (borrar y reinsertar para evitar duplicados)
                    $pdo->prepare('DELETE FROM movimientos WHERE causa_id = ?')->execute([$causa_id]);
                    if (isset($c['bitacora']) && is_array($c['bitacora'])) {
                        foreach ($c['bitacora'] as $mov) {
                            $insMov->execute([
                                $causa_id,
                                $mov['fecha'] ?? '',
                                $mov['texto'] ?? '',
                                empty($mov['inicio']) ? 0 : 1,
                            ]);
                        }
                    }
                }
            }
        }
    }

    $pdo->commit();
    json_ok(['migracion' => 'exitosa', 'resultados' => $resultados]);

} catch (Exception $e) {
    if ($inTransaction && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_error('Error en migración: ' . $e->getMessage());
}
