<?php
/* ============================================================================
 *  RECIBOS  (/api/recibos/...)
 *    GET    /api/recibos            -> libro de recibos del estudio (correlativo)
 *    GET    /api/recibos/{id}       -> un recibo
 *    POST   /api/recibos            -> emitir recibo (asigna número correlativo)
 *
 *  La numeración es CORRELATIVA por estudio: el número sale de
 *  estudios.recibo_seq y se incrementa con bloqueo de fila (FOR UPDATE), así
 *  no hay saltos ni repetidos aunque dos personas emitan al mismo tiempo, ni
 *  importa desde qué dispositivo.
 * ========================================================================== */

function asegurar_tabla_recibos() {
  db()->exec("CREATE TABLE IF NOT EXISTS recibos (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    estudio_id       INT UNSIGNED NOT NULL,
    numero           INT UNSIGNED NOT NULL,
    causa_uuid       VARCHAR(80)  NULL,
    cliente_nombre   VARCHAR(200) NULL,
    caratula         VARCHAR(300) NULL,
    fecha            DATE NULL,
    concepto         VARCHAR(200) NULL,
    ius              DECIMAL(12,2) NOT NULL DEFAULT 0,
    monto            DECIMAL(14,2) NOT NULL DEFAULT 0,
    monto_en_letras  VARCHAR(400) NULL,
    emitido_por      INT UNSIGNED NULL,
    creado_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rec_estudio (estudio_id),
    KEY idx_rec_num (estudio_id, numero)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  // Contador correlativo por estudio.
  try {
    $col = db()->query("SHOW COLUMNS FROM estudios LIKE 'recibo_seq'")->fetch();
    if (!$col) db()->exec("ALTER TABLE estudios ADD COLUMN recibo_seq INT UNSIGNED NOT NULL DEFAULT 1");
  } catch (Throwable $e) { /* silencioso */ }
}

function handle_recibos($method, $resto) {
  asegurar_tabla_recibos();
  $id = isset($resto[0]) ? (int)$resto[0] : 0;
  if ($id && $method === 'GET') return recibo_detalle($id);
  if ($method === 'GET')  return recibos_listar();
  if ($method === 'POST') return recibo_emitir();
  json_error('Método no permitido.', 405);
}

/* Libro de recibos (todos los del estudio, del más nuevo al más viejo). */
function recibos_listar() {
  $u = require_profesional();
  $st = db()->prepare('SELECT r.*, us.nombre AS emisor
                       FROM recibos r LEFT JOIN usuarios us ON us.id = r.emitido_por
                       WHERE r.estudio_id = ? ORDER BY r.numero DESC');
  $st->execute([(int)$u['estudio_id']]);
  json_ok($st->fetchAll());
}

function recibo_detalle($id) {
  $u = require_profesional();
  $st = db()->prepare('SELECT * FROM recibos WHERE id = ? AND estudio_id = ?');
  $st->execute([$id, (int)$u['estudio_id']]);
  $r = $st->fetch();
  if (!$r) json_error('Recibo no encontrado.', 404);
  json_ok($r);
}

/* Emitir: asigna el próximo número correlativo del estudio, de forma atómica. */
function recibo_emitir() {
  $u = require_profesional();
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $st = $pdo->prepare('SELECT recibo_seq FROM estudios WHERE id = ? FOR UPDATE');
    $st->execute([(int)$u['estudio_id']]);
    $numero = (int)$st->fetchColumn();
    if ($numero < 1) $numero = 1;

    $pdo->prepare('INSERT INTO recibos
      (estudio_id, numero, causa_uuid, cliente_nombre, caratula, fecha, concepto, ius, monto, monto_en_letras, emitido_por)
      VALUES (?,?,?,?,?,?,?,?,?,?,?)')
      ->execute([
        (int)$u['estudio_id'], $numero,
        (string)field('causa_uuid') ?: null,
        field('cliente_nombre'),
        field('caratula'),
        field('fecha') ?: date('Y-m-d'),
        field('concepto') ?: 'Pago de honorarios',
        (float)field('ius', 0),
        (float)field('monto', 0),
        field('monto_en_letras'),
        (int)$u['id'],
      ]);
    $reciboId = (int)$pdo->lastInsertId();

    $pdo->prepare('UPDATE estudios SET recibo_seq = ? WHERE id = ?')->execute([$numero + 1, (int)$u['estudio_id']]);
    $pdo->commit();
    json_ok(['id' => $reciboId, 'numero' => $numero], 201);
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}
