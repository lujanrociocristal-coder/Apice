<?php
/* ============================================================================
 *  COMPARTIR CAUSAS CON ABOGADOS EXTERNOS  (/api/compartir/...)
 *
 *  Permite que un estudio comparta UNA causa puntual con un/a profesional de
 *  OTRO estudio, sin exponer el resto de sus causas. El colega ve solo esa
 *  causa y, según el permiso, puede editarla.
 *
 *  Aislamiento: la causa vive en el estudio de origen. Al colega se le da
 *  acceso por un registro de "permiso de causa compartida". Sus ediciones se
 *  guardan directo en esa causa (nunca en su propio estudio).
 *
 *    POST   /api/compartir                 -> compartir (causa_uuid, email, permiso)
 *    GET    /api/compartir?causa=UUID      -> con quién está compartida (origen)
 *    GET    /api/compartir/conmigo         -> causas que OTROS me compartieron
 *    PUT    /api/compartir/causa/{uuid}    -> guardar cambios de una causa compartida
 *    DELETE /api/compartir/{id}            -> revocar un acceso (origen)
 * ========================================================================== */

function asegurar_tabla_compartida() {
  db()->exec("CREATE TABLE IF NOT EXISTS causa_compartida (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    causa_uuid         VARCHAR(80)  NOT NULL,
    estudio_origen_id  INT UNSIGNED NOT NULL,
    colaborador_id     INT UNSIGNED NOT NULL,
    permiso            VARCHAR(10)  NOT NULL DEFAULT 'lectura',
    creado_por         INT UNSIGNED NULL,
    creado_en          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_share (causa_uuid, colaborador_id),
    KEY idx_colab (colaborador_id),
    KEY idx_origen (estudio_origen_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/* Asegura la columna datos_json en causas: guarda la causa COMPLETA (JSON), la
   única fuente de verdad de una causa compartida. Evita la pérdida de datos que
   tendría el mapeo por columnas. Idempotente y defensivo. */
function asegurar_datos_json() {
  static $ok = false; if ($ok) return; $ok = true;
  try {
    $c = db()->query("SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='causas' AND COLUMN_NAME='datos_json'")->fetchColumn();
    if ((int)$c === 0) db()->exec("ALTER TABLE causas ADD COLUMN datos_json LONGTEXT NULL");
  } catch (Throwable $e) { /* silencioso */ }
}

function handle_compartir($method, $resto) {
  asegurar_tabla_compartida();
  asegurar_datos_json();
  $a = $resto[0] ?? '';

  if ($method === 'POST' && $a === '')          return compartir_causa();
  if ($method === 'GET'  && $a === 'conmigo')   return compartidas_conmigo();
  if ($method === 'GET'  && $a === 'mias')      return compartidas_por_mi();
  if ($method === 'GET'  && $a === 'actualizar')return compartidas_actualizar();
  if ($method === 'GET'  && $a === '')          return compartida_quien();
  if ($method === 'PUT'  && $a === 'causa')     return compartida_guardar($resto[1] ?? '');
  if ($method === 'DELETE' && $a !== '')        return compartir_revocar((int)$a);
  json_error('Acción no válida.', 404);
}

/* Última versión de MIS causas compartidas (para que la dueña vea lo que editó
   el colega). Devuelve la causa COMPLETA desde datos_json. */
function compartidas_actualizar() {
  $u = require_profesional();
  $st = db()->prepare('SELECT DISTINCT causa_uuid FROM causa_compartida WHERE estudio_origen_id = ?');
  $st->execute([(int)$u['estudio_id']]);
  $out = [];
  foreach ($st->fetchAll() as $r) {
    $c = causa_por_uuid($r['causa_uuid'], (int)$u['estudio_id']);
    if ($c) $out[] = $c;
  }
  json_ok($out);
}

/* La causa (por uuid) debe pertenecer al estudio de la persona logueada. */
function causa_propia_o_error($uuid, $u) {
  $st = db()->prepare('SELECT * FROM causas WHERE uuid = ? AND estudio_id = ?');
  $st->execute([$uuid, (int)$u['estudio_id']]);
  $c = $st->fetch();
  if (!$c) json_error('La causa no existe o no es de tu estudio.', 404);
  return $c;
}

/* Compartir una causa con un colega (por email). */
function compartir_causa() {
  $u = require_profesional();
  $uuid   = trim((string)field('causa_uuid'));
  $email  = strtolower(trim((string)field('email')));
  $permiso = field('permiso') === 'edicion' ? 'edicion' : 'lectura';
  if ($uuid === '' || $email === '') json_error('Faltan datos (causa o correo).');

  causa_propia_o_error($uuid, $u);

  $st = db()->prepare("SELECT id, nombre, estudio_id FROM usuarios WHERE email = ? AND activo = 1 AND rol = 'profesional'");
  $st->execute([$email]);
  $colab = $st->fetch();
  if (!$colab) json_error('Ese abogado todavía no tiene un usuario activo en ÁPICE. Pedí que se cree su cuenta primero.', 404);
  if ((int)$colab['id'] === (int)$u['id']) json_error('No podés compartir una causa con vos misma.');
  if ((int)$colab['estudio_id'] === (int)$u['estudio_id']) {
    json_error('Ese profesional ya es de tu estudio: ya ve esta causa. Compartir es solo para colegas de otros estudios.');
  }

  db()->prepare('INSERT INTO causa_compartida (causa_uuid, estudio_origen_id, colaborador_id, permiso, creado_por)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE permiso = VALUES(permiso)')
      ->execute([$uuid, (int)$u['estudio_id'], (int)$colab['id'], $permiso, (int)$u['id']]);

  json_ok(['compartida' => true, 'colaborador' => $colab['nombre'], 'permiso' => $permiso], 201);
}

/* Con quién está compartida una causa (vista del estudio de origen). */
function compartida_quien() {
  $u = require_profesional();
  $uuid = trim((string)($_GET['causa'] ?? ''));
  if ($uuid === '') json_error('Falta la causa.');
  causa_propia_o_error($uuid, $u);
  $st = db()->prepare('SELECT cc.id, cc.permiso, cc.creado_en, us.nombre, us.email
                       FROM causa_compartida cc JOIN usuarios us ON us.id = cc.colaborador_id
                       WHERE cc.causa_uuid = ? AND cc.estudio_origen_id = ?
                       ORDER BY cc.creado_en DESC');
  $st->execute([$uuid, (int)$u['estudio_id']]);
  json_ok($st->fetchAll());
}

/* Revocar un acceso (solo el estudio de origen). */
function compartir_revocar($id) {
  $u = require_profesional();
  $st = db()->prepare('SELECT * FROM causa_compartida WHERE id = ?');
  $st->execute([$id]);
  $sh = $st->fetch();
  if (!$sh) json_error('Ese acceso no existe.', 404);
  if ((int)$sh['estudio_origen_id'] !== (int)$u['estudio_id']) json_error('No podés revocar este acceso.', 403);
  db()->prepare('DELETE FROM causa_compartida WHERE id = ?')->execute([$id]);
  json_ok(['revocado' => true]);
}

/* Causas que YO (mi estudio) compartí con externos: uuid -> cantidad. */
function compartidas_por_mi() {
  $u = require_profesional();
  $st = db()->prepare('SELECT causa_uuid, COUNT(*) AS n FROM causa_compartida
                       WHERE estudio_origen_id = ? GROUP BY causa_uuid');
  $st->execute([(int)$u['estudio_id']]);
  json_ok($st->fetchAll());
}

/* Causas que OTROS estudios me compartieron a MÍ. */
function compartidas_conmigo() {
  $u = require_login();
  $st = db()->prepare('SELECT cc.causa_uuid, cc.permiso, cc.estudio_origen_id, e.nombre AS estudio_origen
                       FROM causa_compartida cc JOIN estudios e ON e.id = cc.estudio_origen_id
                       WHERE cc.colaborador_id = ?');
  $st->execute([(int)$u['id']]);
  $shares = $st->fetchAll();
  $out = [];
  foreach ($shares as $sh) {
    $c = causa_por_uuid($sh['causa_uuid'], (int)$sh['estudio_origen_id']);
    if (!$c) continue;
    $c['_compartida'] = true;
    $c['_permiso'] = $sh['permiso'];
    $c['_origen'] = $sh['estudio_origen'];
    $out[] = $c;
  }
  json_ok($out);
}

/* Une la causa entrante con la ya guardada, para que dos colegas que editan la
   MISMA causa no se pisen: las listas (pendientes, movimientos, documentos) se
   UNEN (no se reemplazan). Los datos sueltos (estado, carátula, etc.) los toma
   la última que guarda. Las bajas de ítems no se propagan (se prioriza no
   perder información). */
function compartida_merge($prev, $inc) {
  if (!is_array($prev)) return $inc;
  $out = $inc;
  /* Identidad por CONTENIDO (igual que el frontend), para que no se dupliquen. */
  $claves = [
    'pendientes' => function ($it) { return 'p'.(isset($it['t']) ? mb_strtolower(trim((string)$it['t'])) : json_encode($it)); },
    'bitacora'   => function ($it) { return 'm'.(isset($it['fecha']) ? $it['fecha'] : '') . '|' . (isset($it['texto']) ? mb_strtolower(trim((string)$it['texto'])) : ''); },
    'documentos' => function ($it) { return 'd'.(isset($it['n']) ? mb_strtolower(trim((string)$it['n'])) : (isset($it['nombre']) ? mb_strtolower(trim((string)$it['nombre'])) : json_encode($it))); },
  ];
  /* Bajas propagadas: si un ítem fue borrado (su uid/clave está en _del), no se
     vuelve a incorporar aunque siga en la copia de la otra parte. */
  $delPrev = (isset($prev['_del']) && is_array($prev['_del'])) ? $prev['_del'] : [];
  $delInc  = (isset($inc['_del'])  && is_array($inc['_del']))  ? $inc['_del']  : [];
  $out['_del'] = [];
  foreach ($claves as $campo => $key) {
    $del = array_values(array_unique(array_merge(
      (isset($delPrev[$campo]) && is_array($delPrev[$campo])) ? $delPrev[$campo] : [],
      (isset($delInc[$campo])  && is_array($delInc[$campo]))  ? $delInc[$campo]  : []
    )));
    $out['_del'][$campo] = $del;
    $borrados = array_flip($del);
    $p = (isset($prev[$campo]) && is_array($prev[$campo])) ? $prev[$campo] : [];
    $i = (isset($inc[$campo])  && is_array($inc[$campo]))  ? $inc[$campo]  : [];
    if (!$p && !$i) continue;
    $map = [];
    foreach ($p as $it) { if (is_array($it)) { $k=$key($it); if(!isset($borrados[$k])) $map[$k] = $it; } }
    foreach ($i as $it) { if (is_array($it)) { $k=$key($it); if(!isset($borrados[$k])){ if($campo==='pendientes' && isset($map[$k]) && !empty($map[$k]['done'])) $it['done']=true; $map[$k]=$it; } } }
    $out[$campo] = array_values($map);
  }
  /* Agenda (audiencias/citas de la causa compartida): identidad por 'id', con bajas
     propagadas vía _agDel para que borrar en un lado desaparezca en el otro. */
  $pa = (isset($prev['agenda']) && is_array($prev['agenda'])) ? $prev['agenda'] : [];
  $ia = (isset($inc['agenda'])  && is_array($inc['agenda']))  ? $inc['agenda']  : [];
  if ($pa || $ia) {
    $mapA = [];
    foreach ($pa as $it) { if (is_array($it) && isset($it['id'])) $mapA[$it['id']] = $it; }
    foreach ($ia as $it) { if (is_array($it) && isset($it['id'])) $mapA[$it['id']] = $it; }
    $del = array_values(array_unique(array_merge(
      (isset($prev['_agDel']) && is_array($prev['_agDel'])) ? $prev['_agDel'] : [],
      (isset($inc['_agDel'])  && is_array($inc['_agDel']))  ? $inc['_agDel']  : []
    )));
    foreach ($del as $id) { unset($mapA[$id]); }
    $out['agenda']  = array_values($mapA);
    $out['_agDel']  = $del;
  }
  return $out;
}

/* Guardar cambios en una causa compartida (solo si tengo permiso de edición). */
function compartida_guardar($uuid) {
  $u = require_login();
  $uuid = trim((string)$uuid);
  if ($uuid === '') json_error('Falta la causa.');
  /* Puede guardar: (a) el/la colega con permiso de EDICIÓN, o (b) la DUEÑA
     (profesional del estudio de origen), para sincronizar sus movimientos
     hacia la vista del colega. */
  $st = db()->prepare("SELECT * FROM causa_compartida WHERE causa_uuid = ? AND colaborador_id = ?");
  $st->execute([$uuid, (int)$u['id']]);
  $sh = $st->fetch();
  $origenId = 0;
  if ($sh) {
    if ($sh['permiso'] !== 'edicion') json_error('Tenés esta causa en modo solo lectura.', 403);
    $origenId = (int)$sh['estudio_origen_id'];
  } else {
    if (($u['rol'] ?? '') !== 'profesional') json_error('No tenés acceso a esta causa.', 403);
    $stO = db()->prepare('SELECT 1 FROM causa_compartida WHERE causa_uuid = ? AND estudio_origen_id = ? LIMIT 1');
    $stO->execute([$uuid, (int)$u['estudio_id']]);
    if (!$stO->fetch()) json_error('No tenés acceso a esta causa.', 403);
    $origenId = (int)$u['estudio_id'];
  }

  // Traer la fila real (del estudio de origen).
  $stc = db()->prepare('SELECT id, estudio_id FROM causas WHERE uuid = ? AND estudio_id = ?');
  $stc->execute([$uuid, $origenId]);
  $row = $stc->fetch();
  if (!$row) json_error('La causa ya no está disponible.', 404);

  $c = field('causa');
  if (is_string($c)) $c = json_decode($c, true);
  if (!is_array($c)) json_error('Formato inválido.');

  /* Unir con lo ya guardado (datos_json) para no perder lo que cargó la otra
     parte. Si no hay previo o falla, se guarda lo entrante tal cual. */
  try {
    $stPrev = db()->prepare('SELECT datos_json FROM causas WHERE id = ?');
    $stPrev->execute([(int)$row['id']]);
    $prevJson = $stPrev->fetchColumn();
    if ($prevJson) {
      $prev = json_decode($prevJson, true);
      if (is_array($prev)) $c = compartida_merge($prev, $c);
    }
  } catch (Throwable $e) { /* se guarda lo entrante */ }

  $j = function ($v) { return isset($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : null; };
  db()->prepare('UPDATE causas SET
      estado=?, procesal=?, caratula=?, cliente_nombre=?, expediente=?, cuij=?, objeto=?,
      fuero=?, juzgado=?, juez=?, secretaria=?, letrada=?, cliente_es=?, actor_rol=?, actor=?,
      demandado_rol=?, demandado=?, cliente_calidad=?, posicion=?, materias=?, honorarios=?,
      documentos=?, pendientes=?, alertas=?, datos_json=? WHERE id=?')
    ->execute([
      $c['estado'] ?? 'preparacion', $c['procesal'] ?? null, $c['caratula'] ?? '',
      $c['cliente'] ?? null, $c['expediente'] ?? null, $c['cuij'] ?? null, $c['objeto'] ?? null,
      $c['fuero'] ?? null, $c['juzgado'] ?? null, $c['juez'] ?? null, $c['secretaria'] ?? null,
      $c['letrada'] ?? null, $c['clienteEs'] ?? 'activa', $c['actorRol'] ?? null, $c['actor'] ?? null,
      $c['demandadoRol'] ?? null, $c['demandado'] ?? null, $c['clienteCalidad'] ?? null,
      $c['posicion'] ?? null, $j($c['materia'] ?? null), $j($c['honorarios'] ?? null),
      $j($c['documentos'] ?? null), $j($c['pendientes'] ?? null), $j($c['alertas'] ?? null),
      json_encode($c, JSON_UNESCAPED_UNICODE),
      (int)$row['id'],
    ]);

  // Bitácora / movimientos.
  if (isset($c['bitacora']) && is_array($c['bitacora'])) {
    db()->prepare('DELETE FROM movimientos WHERE causa_id=?')->execute([(int)$row['id']]);
    $insMov = db()->prepare('INSERT INTO movimientos (causa_id,fecha_txt,texto,inicio) VALUES (?,?,?,?)');
    foreach ($c['bitacora'] as $m)
      $insMov->execute([(int)$row['id'], $m['fecha'] ?? '', $m['texto'] ?? '', empty($m['inicio']) ? 0 : 1]);
  }
  json_ok(['guardado' => true]);
}

/* Arma el objeto de causa (igual que estado.php) a partir del uuid + estudio. */
function causa_por_uuid($uuid, $estudioId) {
  $st = db()->prepare('SELECT * FROM causas WHERE uuid = ? AND estudio_id = ?');
  $st->execute([$uuid, $estudioId]);
  $c = $st->fetch();
  if (!$c) return null;
  /* Fuente de verdad de una causa compartida: la causa COMPLETA guardada en
     datos_json (sin pérdida). Si existe, la devolvemos tal cual. */
  if (!empty($c['datos_json'])) {
    $full = json_decode($c['datos_json'], true);
    if (is_array($full) && !empty($full['id'])) return $full;
  }
  $stMov = db()->prepare('SELECT fecha_txt,texto,inicio FROM movimientos WHERE causa_id=? ORDER BY id ASC');
  $stMov->execute([(int)$c['id']]);
  $bit = array_map(function ($m) {
    return ['fecha' => $m['fecha_txt'], 'texto' => $m['texto'], 'inicio' => (bool)$m['inicio']];
  }, $stMov->fetchAll());
  return [
    'id'            => $c['uuid'] ?: 'c_'.$c['id'],
    'estado'        => $c['estado'],
    'procesal'      => $c['procesal'],
    'caratula'      => $c['caratula'],
    'cliente'       => $c['cliente_nombre'],
    'expediente'    => $c['expediente'],
    'cuij'          => $c['cuij'],
    'objeto'        => $c['objeto'],
    'fuero'         => $c['fuero'],
    'juzgado'       => $c['juzgado'],
    'juez'          => $c['juez'],
    'secretaria'    => $c['secretaria'],
    'letrada'       => $c['letrada'],
    'clienteEs'     => $c['cliente_es'] ?? 'activa',
    'actorRol'      => $c['actor_rol'],
    'actor'         => $c['actor'],
    'demandadoRol'  => $c['demandado_rol'],
    'demandado'     => $c['demandado'],
    'clienteCalidad'=> $c['cliente_calidad'],
    'posicion'      => $c['posicion'],
    'materia'       => $c['materias']   ? json_decode($c['materias'],   true) : [],
    'honorarios'    => $c['honorarios'] ? json_decode($c['honorarios'], true) : ['ius'=>0,'gastos'=>[],'pagos'=>[]],
    'documentos'    => $c['documentos'] ? json_decode($c['documentos'], true) : [],
    'pendientes'    => $c['pendientes'] ? json_decode($c['pendientes'], true) : [],
    'alertas'       => $c['alertas']    ? json_decode($c['alertas'],    true) : [],
    'bitacora'      => $bit,
    'ultimoMov'     => !empty($bit) ? end($bit) : null,
    'ficha'         => $c['ficha_id']   ?? '',
    'folder'        => $c['folder_id']  ?? '',
  ];
}
