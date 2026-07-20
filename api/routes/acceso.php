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
  if ($method === 'POST' && $a === 'pago')      return acceso_informar_pago();
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

/* El cliente INFORMA un pago (monto + comprobante). Queda sin confirmar hasta
   que el abogado lo verifique. */
function acceso_informar_pago() {
  $u = require_login();
  if ($u['rol'] !== 'cliente') json_error('Solo disponible para clientes.', 403);
  $uuid = trim((string)($_POST['causa_uuid'] ?? ''));
  $ius  = (float)($_POST['ius'] ?? 0);
  if ($uuid === '') json_error('Falta la causa.');
  if ($ius <= 0) json_error('Ingresá el monto del pago (en IUS).');

  $st = db()->prepare('SELECT estudio_id FROM acceso_cliente WHERE cliente_usuario_id = ? AND causa_uuid = ? LIMIT 1');
  $st->execute([(int)$u['id'], $uuid]);
  $eid = $st->fetchColumn();
  if (!$eid) json_error('No tenés acceso a esta causa.', 403);
  $eid = (int)$eid;

  // Guardar el comprobante (si vino un archivo) y registrarlo como archivo visible.
  $compArch = null;
  if (!empty($_FILES['file']) && isset($_FILES['file']['error']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $orig = (string)$_FILES['file']['name'];
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $size = (int)$_FILES['file']['size'];
    require_once __DIR__ . '/../lib/subidas.php';
    $maloComp = subidas_validar($_FILES['file']['tmp_name'], $ext);
    if ($maloComp !== '') json_error($maloComp);
    if (in_array($ext, ['pdf','jpg','jpeg','png','doc','docx'], true) && $size > 0 && $size <= 20*1024*1024) {
      $base = dirname(__DIR__, 3) . '/apice_archivos';
      $causaSafe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $uuid);
      $dir = $base . '/' . $eid . '/' . $causaSafe;
      if (!is_dir($dir)) @mkdir($dir, 0700, true);
      $stored = bin2hex(random_bytes(10)) . '.' . $ext;
      if (is_dir($dir) && move_uploaded_file($_FILES['file']['tmp_name'], $dir . '/' . $stored)) {
        acceso_asegurar_archivos();
        db()->prepare('INSERT INTO archivos (estudio_id, causa_id, carpeta, nombre, archivo, tipo, tamano, visible_cliente, subido_por)
                       VALUES (?,?,?,?,?,?,?,1,?)')
            ->execute([$eid, $uuid, 'prueba', 'Comprobante de pago (cliente)', $stored, $ext, $size, (int)$u['id']]);
        $compArch = (int)db()->lastInsertId();
      }
    }
  }

  // Agregar el pago (sin confirmar) al JSON de honorarios de la causa.
  $stc = db()->prepare('SELECT id, honorarios FROM causas WHERE uuid = ? AND estudio_id = ?');
  $stc->execute([$uuid, $eid]);
  $row = $stc->fetch();
  if (!$row) json_error('La causa no está disponible.', 404);
  $hon = $row['honorarios'] ? json_decode($row['honorarios'], true) : ['ius'=>0,'gastos'=>[],'pagos'=>[]];
  if (empty($hon['pagos']) || !is_array($hon['pagos'])) $hon['pagos'] = [];
  array_unshift($hon['pagos'], [
    'fecha'      => date('d/m/Y'),
    'ius'        => $ius,
    'nota'       => 'Informado por el cliente',
    'confirmado' => false,
    'compArch'   => $compArch,
  ]);
  db()->prepare('UPDATE causas SET honorarios = ? WHERE id = ?')
      ->execute([json_encode($hon, JSON_UNESCAPED_UNICODE), (int)$row['id']]);
  json_ok(['informado' => true]);
}

/* Crea la tabla de archivos si no existe (para el comprobante del cliente). */
function acceso_asegurar_archivos() {
  db()->exec("CREATE TABLE IF NOT EXISTS archivos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT, estudio_id INT UNSIGNED NOT NULL,
    causa_id VARCHAR(64) NOT NULL, carpeta VARCHAR(24) NOT NULL DEFAULT 'actuaciones',
    nombre VARCHAR(255) NOT NULL, archivo VARCHAR(120) NOT NULL, tipo VARCHAR(12) NOT NULL,
    tamano INT UNSIGNED NOT NULL DEFAULT 0, visible_cliente TINYINT(1) NOT NULL DEFAULT 0,
    subido_por INT UNSIGNED NULL, creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY idx_archivos_causa (causa_id), KEY idx_archivos_estudio (estudio_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
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
    $sta = db()->prepare('SELECT id, nombre, tipo, tamano, fecha_doc, creado_en FROM archivos
                          WHERE causa_id IN (?, ?) AND estudio_id = ? AND visible_cliente = 1
                          ORDER BY COALESCE(fecha_doc, DATE(creado_en)) ASC, id ASC');
    $sta->execute([$uuid, (string)$c['id'], $estudioId]);
    foreach ($sta->fetchAll() as $f) {
      $arch[] = ['id' => (int)$f['id'], 'nombre' => $f['nombre'], 'tipo' => $f['tipo'], 'tamano' => (int)$f['tamano'], 'creado_en' => $f['creado_en'], 'fecha_doc' => $f['fecha_doc']];
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
