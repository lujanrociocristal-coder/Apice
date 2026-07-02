<?php
/* ============================================================================
 *  PORTAL DE CLIENTES — ACCESO  (/api/acceso/...)
 *
 *  El abogado, desde una causa, le da acceso a su cliente (por correo). El
 *  cliente entra con su usuario y ve SOLO las causas que le habilitaron, en
 *  modo lectura, con lenguaje simple.
 *
 *  Aislamiento (secreto profesional):
 *   - El cliente NO usa /api/estado (queda bloqueado): solo ve lo que este
 *     endpoint le devuelve, y son únicamente sus causas habilitadas.
 *   - De cada causa: estado, datos básicos, movimientos, honorarios, agenda y
 *     los documentos marcados como visibles. Nunca notas internas ni alertas.
 *
 *    POST   /api/acceso                 -> activar acceso (causa_uuid, email)
 *    GET    /api/acceso?causa=UUID      -> quién tiene acceso a una causa (abogado)
 *    POST   /api/acceso/blanquear       -> blanquear clave de un cliente (abogado)
 *    DELETE /api/acceso/{id}            -> quitar el acceso de un cliente a una causa
 *    GET    /api/acceso/portal          -> datos del portal (solo el cliente logueado)
 * ========================================================================== */

function asegurar_tabla_acceso() {
  db()->exec("CREATE TABLE IF NOT EXISTS acceso_cliente (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    causa_uuid          VARCHAR(80)  NOT NULL,
    estudio_id          INT UNSIGNED NOT NULL,
    cliente_usuario_id  INT UNSIGNED NOT NULL,
    creado_por          INT UNSIGNED NULL,
    creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_acceso (causa_uuid, cliente_usuario_id),
    KEY idx_cli (cliente_usuario_id),
    KEY idx_est (estudio_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function handle_acceso($method, $resto) {
  asegurar_tabla_acceso();
  $a = $resto[0] ?? '';
  if ($method === 'POST' && $a === '')          return acceso_activar();
  if ($method === 'POST' && $a === 'blanquear') return acceso_blanquear();
  if ($method === 'GET'  && $a === 'portal')    return acceso_portal();
  if ($method === 'GET'  && $a === '')          return acceso_listar();
  if ($method === 'DELETE' && $a !== '')        return acceso_revocar((int)$a);
  json_error('Acción no válida.', 404);
}

/* La causa (uuid) debe ser del estudio de la persona logueada. Devuelve la fila. */
function acceso_causa_propia($uuid, $u) {
  $st = db()->prepare('SELECT * FROM causas WHERE uuid = ? AND estudio_id = ?');
  $st->execute([$uuid, (int)$u['estudio_id']]);
  $c = $st->fetch();
  if (!$c) json_error('La causa no existe o no es de tu estudio.', 404);
  return $c;
}

/* Activar el acceso de un cliente a una causa. */
function acceso_activar() {
  $u = require_profesional();
  $uuid  = trim((string)field('causa_uuid'));
  $email = strtolower(trim((string)field('email')));
  if ($uuid === '' || $email === '') json_error('Faltan datos (causa o correo).');
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('El correo no es válido.');

  $causa = acceso_causa_propia($uuid, $u);
  $eid = (int)$u['estudio_id'];

  // ¿Ya existe un usuario con ese correo?
  $st = db()->prepare('SELECT id, rol, estudio_id, nombre FROM usuarios WHERE email = ?');
  $st->execute([$email]);
  $existe = $st->fetch();

  $claveTemp = null;
  if ($existe) {
    if ($existe['rol'] === 'profesional') json_error('Ese correo pertenece a un abogado, no puede ser cliente.');
    if ((int)$existe['estudio_id'] !== $eid) json_error('Ese correo ya está en uso en otra cuenta.');
    $clienteId = (int)$existe['id'];
    $clienteNombre = $existe['nombre'];
  } else {
    $nombre = trim((string)($causa['cliente_nombre'] ?? '')) ?: $email;
    $claveTemp = generar_clave_temporal();
    db()->prepare('INSERT INTO usuarios (estudio_id, nombre, email, password_hash, rol, debe_cambiar_clave, activo)
                   VALUES (?,?,?,?,?,1,1)')
        ->execute([$eid, $nombre, $email, hash_password($claveTemp), 'cliente']);
    $clienteId = (int)db()->lastInsertId();
    $clienteNombre = $nombre;
  }

  // Vincular (idempotente) el cliente con esta causa.
  db()->prepare('INSERT INTO acceso_cliente (causa_uuid, estudio_id, cliente_usuario_id, creado_por)
                 VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE creado_en = creado_en')
      ->execute([$uuid, $eid, $clienteId, (int)$u['id']]);

  json_ok([
    'ya_existia'     => (bool)$existe,
    'clave_temporal' => $claveTemp,               // solo cuando se crea la cuenta
    'cliente'        => ['id' => $clienteId, 'nombre' => $clienteNombre, 'email' => $email],
  ], 201);
}

/* Quién tiene acceso a una causa (vista del abogado). */
function acceso_listar() {
  $u = require_profesional();
  $uuid = trim((string)($_GET['causa'] ?? ''));
  if ($uuid === '') json_error('Falta la causa.');
  acceso_causa_propia($uuid, $u);
  $st = db()->prepare('SELECT ac.id, us.id AS usuario_id, us.nombre, us.email,
                              us.debe_cambiar_clave, us.activo
                       FROM acceso_cliente ac JOIN usuarios us ON us.id = ac.cliente_usuario_id
                       WHERE ac.causa_uuid = ? AND ac.estudio_id = ?
                       ORDER BY us.nombre ASC');
  $st->execute([$uuid, (int)$u['estudio_id']]);
  json_ok($st->fetchAll());
}

/* Quitar el acceso de un cliente a una causa. */
function acceso_revocar($id) {
  $u = require_profesional();
  $st = db()->prepare('SELECT * FROM acceso_cliente WHERE id = ?');
  $st->execute([$id]);
  $ac = $st->fetch();
  if (!$ac) json_error('Ese acceso no existe.', 404);
  if ((int)$ac['estudio_id'] !== (int)$u['estudio_id']) json_error('No podés quitar este acceso.', 403);
  db()->prepare('DELETE FROM acceso_cliente WHERE id = ?')->execute([$id]);
  json_ok(['revocado' => true]);
}

/* Blanquear la clave de un cliente (solo un abogado del mismo estudio). */
function acceso_blanquear() {
  $u = require_profesional();
  $uid = (int)field('usuario_id');
  if (!$uid) json_error('Falta el cliente.');
  // El cliente debe estar vinculado a alguna causa de MI estudio.
  $st = db()->prepare('SELECT 1 FROM acceso_cliente WHERE cliente_usuario_id = ? AND estudio_id = ? LIMIT 1');
  $st->execute([$uid, (int)$u['estudio_id']]);
  if (!$st->fetch()) json_error('Ese cliente no pertenece a tu estudio.', 403);

  $temp = generar_clave_temporal();
  db()->prepare("UPDATE usuarios SET password_hash = ?, debe_cambiar_clave = 1 WHERE id = ? AND rol = 'cliente'")
      ->execute([hash_password($temp), $uid]);
  json_ok(['clave_temporal' => $temp]);
}

/* ---------- DATOS DEL PORTAL (solo el cliente logueado) ---------- */
function acceso_portal() {
  $u = require_login();
  if ($u['rol'] !== 'cliente') json_error('Solo disponible para clientes.', 403);

  $st = db()->prepare('SELECT causa_uuid, estudio_id FROM acceso_cliente WHERE cliente_usuario_id = ?');
  $st->execute([(int)$u['id']]);
  $links = $st->fetchAll();

  $causas = [];
  $nombresCliente = [];
  $estudios = [];
  foreach ($links as $l) {
    $c = acceso_causa_cliente($l['causa_uuid'], (int)$l['estudio_id']);
    if ($c) {
      $causas[] = $c;
      if (!empty($c['cliente'])) $nombresCliente[$c['cliente']] = true;
      $estudios[(int)$l['estudio_id']] = true;
    }
  }

  // Agenda: citas y audiencias del cliente (por nombre, dentro de sus estudios).
  $audiencias = [];
  if ($nombresCliente && $estudios) {
    $inEst = implode(',', array_fill(0, count($estudios), '?'));
    $inNom = implode(',', array_fill(0, count($nombresCliente), '?'));
    $sql = "SELECT tipo,fecha,hora,detalle,cliente_nombre,materia,cli_asiste,modalidad,lugar,link
            FROM audiencias WHERE estudio_id IN ($inEst) AND cliente_nombre IN ($inNom)
            ORDER BY fecha ASC, hora ASC";
    $params = array_merge(array_keys($estudios), array_keys($nombresCliente));
    $sta = db()->prepare($sql);
    $sta->execute($params);
    foreach ($sta->fetchAll() as $a) {
      $audiencias[] = [
        'tipo' => $a['tipo'], 'fecha' => $a['fecha'], 'hora' => $a['hora'],
        'detalle' => $a['detalle'], 'cliente' => $a['cliente_nombre'], 'materia' => $a['materia'],
        'cliAsiste' => (bool)$a['cli_asiste'], 'modalidad' => $a['modalidad'],
        'lugar' => $a['lugar'], 'link' => $a['link'],
      ];
    }
  }

  // valorIUS del estudio (para mostrar pesos). Toma el del primer estudio.
  $valorIUS = 0;
  if ($estudios) {
    $eid = array_key_first($estudios);
    $stc = db()->prepare("SELECT valor FROM estado_app WHERE estudio_id = ? AND clave = 'gestor_cfg_v9'");
    $stc->execute([$eid]);
    $cfg = $stc->fetchColumn();
    if ($cfg) { $o = json_decode($cfg, true); if (isset($o['valorIUS'])) $valorIUS = (float)$o['valorIUS']; }
  }

  json_ok([
    'nombre'     => $u['nombre'],
    'valorIUS'   => $valorIUS,
    'causas'     => $causas,
    'audiencias' => $audiencias,
  ]);
}

/* Arma una causa SEGURA para el cliente: sin alertas ni notas internas, con
   los documentos (fichas) visibles y los archivos reales marcados visibles. */
function acceso_causa_cliente($uuid, $estudioId) {
  $st = db()->prepare('SELECT * FROM causas WHERE uuid = ? AND estudio_id = ?');
  $st->execute([$uuid, $estudioId]);
  $c = $st->fetch();
  if (!$c) return null;

  // Movimientos (línea de tiempo).
  $stMov = db()->prepare('SELECT fecha_txt,texto,inicio FROM movimientos WHERE causa_id=? ORDER BY id ASC');
  $stMov->execute([(int)$c['id']]);
  $bit = array_map(function ($m) {
    return ['fecha' => $m['fecha_txt'], 'texto' => $m['texto'], 'inicio' => (bool)$m['inicio']];
  }, $stMov->fetchAll());
  // El cliente ve el más reciente primero (renderCliente marca el índice 0 como "último").
  $bit = array_reverse($bit);

  // Documentos-ficha: solo los visibles.
  $docs = $c['documentos'] ? json_decode($c['documentos'], true) : [];
  $docsVis = array_values(array_filter((array)$docs, function ($d) {
    return !empty($d['visible']);
  }));

  // Archivos reales subidos al servidor, marcados visibles al cliente.
  $arch = [];
  try {
    $sta = db()->prepare('SELECT id, nombre, tipo, tamano FROM archivos
                          WHERE causa_id = ? AND estudio_id = ? AND visible_cliente = 1
                          ORDER BY creado_en DESC');
    $sta->execute([$uuid, $estudioId]);
    foreach ($sta->fetchAll() as $f) {
      $arch[] = ['id' => (int)$f['id'], 'nombre' => $f['nombre'], 'tipo' => $f['tipo'], 'tamano' => (int)$f['tamano']];
    }
  } catch (Throwable $e) { /* la tabla archivos puede no existir todavía */ }

  return [
    'id'         => $c['uuid'] ?: 'c_'.$c['id'],
    'estado'     => $c['estado'],
    'procesal'   => $c['procesal'],
    'caratula'   => $c['caratula'],
    'cliente'    => $c['cliente_nombre'],
    'expediente' => $c['expediente'],
    'cuij'       => $c['cuij'],
    'objeto'     => $c['objeto'],
    'fuero'      => $c['fuero'],
    'juzgado'    => $c['juzgado'],
    'juez'       => $c['juez'],
    'secretaria' => $c['secretaria'],
    'materia'    => $c['materias'] ? json_decode($c['materias'], true) : [],
    'honorarios' => $c['honorarios'] ? json_decode($c['honorarios'], true) : ['ius'=>0,'gastos'=>[],'pagos'=>[]],
    'documentos' => $docsVis,
    'archivos'   => $arch,
    'bitacora'   => $bit,
    'folder'     => $c['folder_id'] ?? '',
  ];
}
