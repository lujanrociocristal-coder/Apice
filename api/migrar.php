<?php
/* ============================================================================
 * SCRIPT DE MIGRACIÓN COMPLETA: JSON -> RELACIONAL
 * Migra clientes, causas (y sus movimientos) a las tablas SQL.
 * Es seguro correrlo múltiples veces (usa ON DUPLICATE KEY UPDATE).
 * ========================================================================== */

error_reporting(E_ALL);
ini_set('display_errors', '1');
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/respond.php';
require __DIR__ . '/lib/auth.php';

start_secure_session();
$u = require_profesional();
$eid = (int)$u['estudio_id'];
$pdo = db();

// Agregar columna uuid si no existe
function agregar_uuid($pdo, $tabla) {
    $r = $pdo->query("SHOW COLUMNS FROM `{$tabla}` LIKE 'uuid'");
    if ($r->rowCount() === 0) $pdo->exec("ALTER TABLE `{$tabla}` ADD COLUMN `uuid` VARCHAR(80) NULL");
    $r2 = $pdo->query("SHOW INDEX FROM `{$tabla}` WHERE Key_name = 'uq_{$tabla}_uuid'");
    if ($r2->rowCount() === 0) $pdo->exec("ALTER TABLE `{$tabla}` ADD UNIQUE KEY `uq_{$tabla}_uuid` (`uuid`)");
}

$inTransaction = false;
try {
    // Esquema: fuera de la transacción (DDL hace auto-commit)
    agregar_uuid($pdo, 'clientes');
    agregar_uuid($pdo, 'causas');

    $pdo->beginTransaction();
    $inTransaction = true;

    $res = ['clientes' => 0, 'causas' => 0, 'movimientos' => 0];

    // ── CLIENTES ─────────────────────────────────────────────────────────────
    $raw = $pdo->prepare('SELECT valor FROM estado_app WHERE estudio_id=? AND clave="gestor_cli_v1"');
    $raw->execute([$eid]);
    $cliJson = $raw->fetchColumn();

    if ($cliJson) {
        $lista = json_decode($cliJson, true);
        // El frontend guarda clientes como objeto: {"Nombre": {datos...}}
        // Si viene como array (estructura vieja), también lo manejamos
        if (is_array($lista)) {
            $ins = $pdo->prepare('
                INSERT INTO clientes (estudio_id, uuid, nombre, tipo, dni_cuit, email, telefono, domicilio, notas)
                VALUES (?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                  tipo=VALUES(tipo), dni_cuit=VALUES(dni_cuit),
                  email=VALUES(email), telefono=VALUES(telefono), domicilio=VALUES(domicilio)
            ');

            // Detectar si es diccionario {nombre: datos} o array [{nombre, ...}]
            $esDiccionario = !isset($lista[0]);

            foreach ($lista as $key => $c) {
                if ($esDiccionario) {
                    // key = nombre, c = datos
                    $nombre = $key;
                    $uuid = 'cli_' . md5($nombre . '_' . $eid);
                    $tipo = ($c['tipo'] ?? 'fisica') === 'juridica' ? 'juridica' : 'fisica';
                    $ins->execute([
                        $eid, $uuid, $nombre, $tipo,
                        $c['cuit'] ?: ($c['dni'] ?? null),
                        $c['email']    ?? null,
                        $c['telefono'] ?: ($c['whatsapp'] ?? null),
                        $c['domicilio'] ?? null, null,
                    ]);
                } else {
                    // array con objetos {nombre, tipo, ...}
                    if (empty($c['nombre'])) continue;
                    $nombre = $c['nombre'];
                    $uuid = $c['id'] ?? ('cli_' . md5($nombre . '_' . $eid));
                    $tipo = ($c['tipo'] ?? 'fisica') === 'juridica' ? 'juridica' : 'fisica';
                    $ins->execute([
                        $eid, $uuid, $nombre, $tipo,
                        $c['cuit'] ?: ($c['dni'] ?? null),
                        $c['email']    ?? null,
                        $c['telefono'] ?: ($c['whatsapp'] ?? null),
                        $c['domicilio'] ?? null, null,
                    ]);
                }
                if ($ins->rowCount() > 0) $res['clientes']++;
            }
        }
    }


    // ── CAUSAS ───────────────────────────────────────────────────────────────
    $raw2 = $pdo->prepare('SELECT valor FROM estado_app WHERE estudio_id=? AND clave="gestor_causas_v6"');
    $raw2->execute([$eid]);
    $cauJson = $raw2->fetchColumn();

    if ($cauJson) {
        $lista2 = json_decode($cauJson, true);
        if (is_array($lista2)) {
            // Asegurar columnas JSON
            foreach (['honorarios','documentos','pendientes','alertas'] as $col) {
                $r = $pdo->query("SHOW COLUMNS FROM causas LIKE '{$col}'");
                if ($r->rowCount()===0) $pdo->exec("ALTER TABLE causas ADD COLUMN `{$col}` JSON NULL");
            }

            $ins2 = $pdo->prepare('
                INSERT INTO causas
                  (estudio_id, owner_id, uuid, estado, procesal, caratula,
                   cliente_nombre, expediente, cuij, objeto, fuero, juzgado, juez,
                   secretaria, letrada, cliente_es, actor_rol, actor, demandado_rol,
                   demandado, cliente_calidad, posicion, materias,
                   honorarios, documentos, pendientes, alertas)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                  estado=VALUES(estado), caratula=VALUES(caratula),
                  procesal=VALUES(procesal), expediente=VALUES(expediente),
                  cuij=VALUES(cuij), juez=VALUES(juez),
                  secretaria=VALUES(secretaria), materias=VALUES(materias),
                  honorarios=VALUES(honorarios), documentos=VALUES(documentos),
                  pendientes=VALUES(pendientes), alertas=VALUES(alertas)
            ');
            $insMov = $pdo->prepare('INSERT INTO movimientos (causa_id, fecha_txt, texto, inicio) VALUES (?,?,?,?)');
            $getId  = $pdo->prepare('SELECT id FROM causas WHERE uuid=?');

            $j = fn($v) => isset($v) && $v !== null ? json_encode($v, JSON_UNESCAPED_UNICODE) : null;

            foreach ($lista2 as $c) {
                if (!isset($c['id'])) continue;

                $ins2->execute([
                    $eid, $u['id'], $c['id'],
                    $c['estado']         ?? 'preparacion',
                    $c['procesal']       ?? null,
                    $c['caratula']       ?? '',
                    $c['cliente']        ?? null,
                    $c['expediente']     ?? null,
                    $c['cuij']           ?? null,
                    $c['objeto']         ?? null,
                    $c['fuero']          ?? null,
                    $c['juzgado']        ?? null,
                    $c['juez']           ?? null,
                    $c['secretaria']     ?? null,
                    $c['letrada']        ?? null,
                    $c['clienteEs']      ?? 'activa',
                    $c['actorRol']       ?? null,
                    $c['actor']          ?? null,
                    $c['demandadoRol']   ?? null,
                    $c['demandado']      ?? null,
                    $c['clienteCalidad'] ?? null,
                    $c['posicion']       ?? null,
                    $j($c['materia']   ?? null),
                    $j($c['honorarios']?? null),
                    $j($c['documentos']?? null),
                    $j($c['pendientes']?? null),
                    $j($c['alertas']   ?? null),
                ]);

                $getId->execute([$c['id']]);
                $causa_id = $getId->fetchColumn();

                if ($causa_id && isset($c['bitacora']) && is_array($c['bitacora'])) {
                    $pdo->prepare('DELETE FROM movimientos WHERE causa_id=?')->execute([$causa_id]);
                    foreach ($c['bitacora'] as $mov) {
                        $insMov->execute([
                            $causa_id,
                            $mov['fecha'] ?? '',
                            $mov['texto'] ?? '',
                            empty($mov['inicio']) ? 0 : 1,
                        ]);
                        $res['movimientos']++;
                    }
                }
                if ($ins2->rowCount() > 0) $res['causas']++;
            }
        }
    }

    $pdo->commit();
    json_ok(['migracion' => 'exitosa', 'resultados' => $res]);

} catch (Exception $e) {
    if ($inTransaction && $pdo->inTransaction()) $pdo->rollBack();
    json_error('Error: ' . $e->getMessage());
}
