<?php
/* ============================================================================
 *  CAUSAS / EXPEDIENTES  (/api/causas/...)
 *    GET    /api/causas              -> lista de causas visibles para mí
 *    GET    /api/causas/{id}         -> una causa completa (con todo adentro)
 *    POST   /api/causas              -> crear causa
 *    PUT    /api/causas/{id}         -> editar causa
 *    DELETE /api/causas/{id}         -> borrar causa
 *    POST   /api/causas/{id}/movimientos          -> agregar movimiento
 *    DELETE /api/causas/{id}/movimientos/{mid}    -> borrar movimiento
 *    POST   /api/causas/{id}/compartir            -> compartir con colega
 *    DELETE /api/causas/{id}/compartir/{uid}      -> dejar de compartir
 * ========================================================================== */

function handle_causas($method, $resto) {
  $id  = isset($resto[0]) ? (int)$resto[0] : 0;
  $sub = $resto[1] ?? '';
  $sid = isset($resto[2]) ? (int)$resto[2] : 0;

  // -------- SUBRECURSOS de una causa --------
  if ($id && $sub === 'movimientos') {
    if ($method === 'POST')   return causa_mov_add($id);
    if ($method === 'DELETE') return causa_mov_del($id, $sid);
  }
  if ($id && $sub === 'compartir') {
    if ($method === 'POST')   return causa_compartir($id);
    if ($method === 'DELETE') return causa_descompartir($id, $sid);
  }

  // -------- CAUSA puntual --------
  if ($id) {
    if ($method === 'GET')    return causa_detalle($id);
    if ($method === 'PUT')    return causa_editar($id);
    if ($method === 'DELETE') return causa_borrar($id);
    json_error('Método no permitido.', 405);
  }

  // -------- COLECCIÓN --------
  if ($method === 'GET')  return causas_listar();
  if ($method === 'POST') return causa_crear();
  json_error('Método no permitido.', 405);
}

/* Lista de causas que la usuaria puede ver. */
function causas_listar() {
  $u = require_login();
  $eid = (int)$u['estudio_id'];

  if ($u['rol'] === 'cliente') {
    // El cliente ve solo SUS causas.
    $sql = 'SELECT c.* FROM causas c
            JOIN clientes cl ON cl.id = c.cliente_id
            WHERE c.estudio_id = ? AND cl.usuario_id = ?
            ORDER BY c.actualizado_en DESC';
    $st = db()->prepare($sql);
    $st->execute([$eid, $u['id']]);
  } else {
    // Profesional: causas propias + compartidas.
    $sql = 'SELECT DISTINCT c.* FROM causas c
            LEFT JOIN causa_colaboradores col ON col.causa_id = c.id AND col.usuario_id = ?
            WHERE c.estudio_id = ? AND (c.owner_id = ? OR col.usuario_id IS NOT NULL)
            ORDER BY c.actualizado_en DESC';
    $st = db()->prepare($sql);
    $st->execute([$u['id'], $eid, $u['id']]);
  }
  $causas = $st->fetchAll();
  foreach ($causas as &$c) {
    decode_json_fields($c, ['materias', 'registral']);
    // último movimiento para la tarjeta
    $m = db()->prepare('SELECT fecha_txt, texto, nuevo FROM movimientos WHERE causa_id = ? ORDER BY orden ASC, id DESC LIMIT 1');
    $m->execute([$c['id']]);
    $c['ultimo_mov'] = $m->fetch() ?: null;
    // cantidad de alertas
    $a = db()->prepare('SELECT COUNT(*) FROM alertas WHERE causa_id = ? AND resuelta = 0');
    $a->execute([$c['id']]);
    $c['alertas_count'] = (int)$a->fetchColumn();
  }
  json_ok($causas);
}

/* Una causa con todo lo que cuelga de ella. */
function causa_detalle($id) {
  $u = require_login();
  $soloLectura = ($u['rol'] === 'cliente');
  puede_acceder_causa($id, $soloLectura);

  $st = db()->prepare('SELECT * FROM causas WHERE id = ?');
  $st->execute([$id]);
  $c = $st->fetch();
  decode_json_fields($c, ['materias', 'registral']);

  $get = function ($sql) use ($id) { $s = db()->prepare($sql); $s->execute([$id]); return $s->fetchAll(); };

  $c['bitacora']  = $get('SELECT * FROM movimientos WHERE causa_id = ? ORDER BY orden ASC, id ASC');
  $docs = $get('SELECT * FROM documentos WHERE causa_id = ? ORDER BY id ASC');
  foreach ($docs as &$d) decode_json_fields($d, ['etiquetas', 'historial']);
  $c['documentos'] = $docs;
  $c['pendientes'] = $get('SELECT * FROM tareas WHERE causa_id = ? ORDER BY hecha ASC, id ASC');
  $c['gastos']     = $get('SELECT * FROM honorarios_gastos WHERE causa_id = ? ORDER BY id ASC');
  $c['pagos']      = $get('SELECT * FROM pagos WHERE causa_id = ? ORDER BY fecha ASC');
  $alertas = $get('SELECT * FROM alertas WHERE causa_id = ? AND resuelta = 0');
  foreach ($alertas as &$a) decode_json_fields($a, ['opciones']);
  $c['alertas'] = $alertas;
  // colaboradoras
  $col = db()->prepare('SELECT cc.usuario_id, cc.permiso, us.nombre, us.email
                        FROM causa_colaboradores cc JOIN usuarios us ON us.id = cc.usuario_id
                        WHERE cc.causa_id = ?');
  $col->execute([$id]);
  $c['colaboradores'] = $col->fetchAll();
  // convenio (si existe)
  $cv = db()->prepare('SELECT * FROM convenios WHERE causa_id = ?');
  $cv->execute([$id]);
  $conv = $cv->fetch();
  if ($conv) decode_json_fields($conv, ['datos']);
  $c['convenio'] = $conv ?: null;

  json_ok($c);
}

/* Campos editables de una causa (lista blanca, por seguridad). */
function _causa_campos() {
  return ['ref','estado','procesal','caratula','cliente_id','cliente_nombre','expediente','cuij',
          'objeto','fuero','juzgado','juez','secretaria','letrada','posicion','actor_rol','actor',
          'demandado_rol','demandado','cliente_es','cliente_calidad','honorarios_ius','cad_tipo',
          'ficha_id','folder_id'];
}

function causa_crear() {
  $u = require_profesional();
  $car = trim((string)field('caratula'));
  if (!$car) json_error('La carátula es obligatoria.');

  $cols = ['estudio_id','owner_id','caratula']; $vals = [$u['estudio_id'], $u['id'], $car];
  $ph = ['?','?','?'];
  foreach (_causa_campos() as $f) {
    if ($f === 'caratula') continue;
    $v = field($f, null);
    if ($v !== null) { $cols[] = $f; $vals[] = $v; $ph[] = '?'; }
  }
  // materias y registral son JSON
  if (field('materias') !== null) { $cols[]='materias'; $vals[]=json_encode(field('materias'), JSON_UNESCAPED_UNICODE); $ph[]='?'; }
  if (field('registral') !== null) { $cols[]='registral'; $vals[]=json_encode(field('registral'), JSON_UNESCAPED_UNICODE); $ph[]='?'; }

  $sql = 'INSERT INTO causas (' . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')';
  db()->prepare($sql)->execute($vals);
  json_ok(['id' => (int)db()->lastInsertId()], 201);
}

function causa_editar($id) {
  puede_acceder_causa($id, false);   // exige permiso de edición
  $sets = []; $vals = [];
  foreach (_causa_campos() as $f) {
    $v = field($f, '__NO__');
    if ($v !== '__NO__') { $sets[] = "$f = ?"; $vals[] = $v; }
  }
  if (field('materias') !== null)  { $sets[]='materias = ?';  $vals[]=json_encode(field('materias'), JSON_UNESCAPED_UNICODE); }
  if (field('registral') !== null) { $sets[]='registral = ?'; $vals[]=json_encode(field('registral'), JSON_UNESCAPED_UNICODE); }
  if (!$sets) json_error('No hay nada para actualizar.');
  $vals[] = $id;
  db()->prepare('UPDATE causas SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
  json_ok(['actualizada' => true]);
}

function causa_borrar($id) {
  $u = require_profesional();
  $c = puede_acceder_causa($id, false);
  if ((int)$c['owner_id'] !== (int)$u['id']) json_error('Solo la dueña de la causa puede borrarla.', 403);
  db()->prepare('DELETE FROM causas WHERE id = ?')->execute([$id]);
  json_ok(['borrada' => true]);
}

/* ---- Movimientos (bitácora) ---- */
function causa_mov_add($id) {
  puede_acceder_causa($id, false);
  $texto = trim((string)field('texto'));
  if (!$texto) json_error('El movimiento no puede estar vacío.');
  $st = db()->prepare('INSERT INTO movimientos (causa_id, fecha_txt, fecha_iso, texto, inicio, nuevo, orden)
                       VALUES (?,?,?,?,?,?,?)');
  $st->execute([$id, field('fecha_txt'), field('fecha_iso'), $texto,
                field('inicio') ? 1 : 0, field('nuevo') ? 1 : 0, (int)field('orden', 0)]);
  db()->prepare('UPDATE causas SET actualizado_en = NOW() WHERE id = ?')->execute([$id]);
  json_ok(['id' => (int)db()->lastInsertId()], 201);
}
function causa_mov_del($id, $mid) {
  puede_acceder_causa($id, false);
  db()->prepare('DELETE FROM movimientos WHERE id = ? AND causa_id = ?')->execute([$mid, $id]);
  json_ok(['borrado' => true]);
}

/* ---- Compartir con colegas del mismo estudio ---- */
function causa_compartir($id) {
  $u = require_profesional();
  $c = puede_acceder_causa($id, false);
  if ((int)$c['owner_id'] !== (int)$u['id']) json_error('Solo la dueña puede compartir la causa.', 403);
  $colegaId = (int)field('usuario_id');
  $permiso  = field('permiso') === 'lectura' ? 'lectura' : 'edicion';
  // El colega tiene que ser del mismo estudio.
  $chk = db()->prepare('SELECT 1 FROM usuarios WHERE id = ? AND estudio_id = ? AND rol = "profesional"');
  $chk->execute([$colegaId, $u['estudio_id']]);
  if (!$chk->fetch()) json_error('La persona no pertenece a tu estudio.');
  db()->prepare('INSERT INTO causa_colaboradores (causa_id, usuario_id, permiso) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE permiso = VALUES(permiso)')
      ->execute([$id, $colegaId, $permiso]);
  json_ok(['compartida' => true]);
}
function causa_descompartir($id, $uid) {
  $u = require_profesional();
  $c = puede_acceder_causa($id, false);
  if ((int)$c['owner_id'] !== (int)$u['id']) json_error('Solo la dueña puede modificar el compartido.', 403);
  db()->prepare('DELETE FROM causa_colaboradores WHERE causa_id = ? AND usuario_id = ?')->execute([$id, $uid]);
  json_ok(['quitada' => true]);
}
