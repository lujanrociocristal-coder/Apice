<?php
/* ============================================================================
 *  GESTIÓN DE USUARIOS Y CLAVES  (/api/usuarios/...)
 *
 *  DENTRO DE UN ESTUDIO (cualquier abogada profesional de ese estudio):
 *    GET    /api/usuarios                 -> personas de MI estudio
 *    POST   /api/usuarios                 -> agregar una abogada a MI estudio (clave temporal)
 *    POST   /api/usuarios/{id}/blanquear  -> blanquear clave de alguien de MI estudio
 *    PUT    /api/usuarios/{id}            -> activar/desactivar / renombrar
 *
 *  SOLO LA SUPER-ADMINISTRADORA (dueña de la plataforma):
 *    GET    /api/usuarios/estudios        -> lista de TODOS los estudios (firmas)
 *    POST   /api/usuarios/estudio         -> crear un estudio nuevo + su abogada admin
 * ========================================================================== */

function handle_usuarios($method, $resto) {
  $primero = $resto[0] ?? '';

  // --- Acciones de la super-administradora ---
  if ($primero === 'estudios' && $method === 'GET')    return estudios_listar();
  if ($primero === 'estudio'  && $method === 'POST')   return estudio_crear();
  if ($primero === 'estudio'  && $method === 'PUT')    return estudio_editar((int)($resto[1] ?? 0));
  if ($primero === 'estudio'  && $method === 'DELETE') return estudio_eliminar((int)($resto[1] ?? 0));

  // --- Acciones dentro del estudio ---
  $id  = (int)$primero;
  $sub = $resto[1] ?? '';
  if ($id && $sub === 'blanquear' && $method === 'POST') return usuario_blanquear($id);
  if ($id && $method === 'PUT')    return usuario_editar($id);
  if ($id && $method === 'DELETE') return usuario_eliminar($id);
  if ($method === 'GET')  return usuarios_listar();
  if ($method === 'POST') return usuario_crear();
  json_error('Método no permitido.', 405);
}

/* ---------- Dentro del estudio (cualquier profesional) ---------- */

function usuarios_listar() {
  $u = require_profesional();
  $st = db()->prepare('SELECT id, nombre, email, rol, es_admin, es_superadmin, activo,
                              debe_cambiar_clave, ultimo_acceso, creado_en
                       FROM usuarios WHERE estudio_id = ? ORDER BY rol, nombre');
  $st->execute([$u['estudio_id']]);
  json_ok($st->fetchAll());
}

function usuario_crear() {
  $u = require_profesional();
  // Las cuentas INDIVIDUALES son de una sola abogada: no pueden sumar colegas.
  if (($u['estudio_tipo'] ?? 'estudio') === 'individual') {
    json_error('Tu cuenta es individual (una sola abogada). Para sumar colegas, pedí a la administradora de ÁPICE que la convierta en cuenta de estudio.', 403);
  }
  $nombre = trim((string)field('nombre'));
  $email  = strtolower(trim((string)field('email')));
  if (!$nombre || !$email) json_error('Indicá nombre y email.');
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('El email no es válido.');

  $chk = db()->prepare('SELECT 1 FROM usuarios WHERE email = ?');
  $chk->execute([$email]);
  if ($chk->fetch()) json_error('Ya existe una cuenta con ese email.');

  $temp = generar_clave_temporal();
  db()->prepare('INSERT INTO usuarios (estudio_id, nombre, email, password_hash, rol, es_admin, debe_cambiar_clave)
                 VALUES (?,?,?,?,?,0,1)')
      ->execute([$u['estudio_id'], $nombre, $email, hash_password($temp), 'profesional']);
  json_ok(['id' => (int)db()->lastInsertId(), 'clave_temporal' => $temp], 201);
}

function usuario_blanquear($id) {
  $u = require_profesional();
  $st = db()->prepare('SELECT id, es_superadmin FROM usuarios WHERE id = ? AND estudio_id = ?');
  $st->execute([$id, $u['estudio_id']]);
  $obj = $st->fetch();
  if (!$obj) json_error('Usuario no encontrado en tu estudio.', 404);
  // Nadie puede blanquear a la super-administradora, salvo ella misma.
  if ((int)$obj['es_superadmin'] === 1 && (int)$u['id'] !== $id) {
    json_error('No podés blanquear la clave de la super-administradora.', 403);
  }
  $temp = generar_clave_temporal();
  db()->prepare('UPDATE usuarios SET password_hash = ?, debe_cambiar_clave = 1 WHERE id = ?')
      ->execute([hash_password($temp), $id]);
  json_ok(['clave_temporal' => $temp]);
}

function usuario_editar($id) {
  $u = require_profesional();
  $st = db()->prepare('SELECT id, es_admin, es_superadmin, activo FROM usuarios WHERE id = ? AND estudio_id = ?');
  $st->execute([$id, $u['estudio_id']]);
  $obj = $st->fetch();
  if (!$obj) json_error('Usuario no encontrado en tu estudio.', 404);

  $sets = []; $vals = [];

  if (field('nombre', '__NO__') !== '__NO__') {
    $n = trim((string)field('nombre'));
    if ($n !== '') { $sets[] = 'nombre = ?'; $vals[] = $n; }
  }

  if (field('activo', '__NO__') !== '__NO__') {
    $activo = field('activo') ? 1 : 0;
    if ($id === (int)$u['id'] && $activo === 0) json_error('No podés desactivar tu propio acceso.');
    if ((int)$obj['es_superadmin'] === 1 && $activo === 0) json_error('No se puede desactivar a la super-administradora.');
    $sets[] = 'activo = ?'; $vals[] = $activo;
  }

  if (!$sets) json_error('No hay nada para actualizar.');
  $vals[] = $id;
  db()->prepare('UPDATE usuarios SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
  json_ok(['actualizado' => true]);
}

function usuario_eliminar($id) {
  $u = require_profesional();
  $st = db()->prepare('SELECT id, es_superadmin FROM usuarios WHERE id = ? AND estudio_id = ?');
  $st->execute([$id, $u['estudio_id']]);
  $obj = $st->fetch();
  if (!$obj) json_error('Usuario no encontrado en tu estudio.', 404);
  if ((int)$id === (int)$u['id']) json_error('No podés eliminar tu propia cuenta.');
  if ((int)$obj['es_superadmin'] === 1) json_error('No se puede eliminar a la super-administradora.', 403);
  db()->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
  json_ok(['eliminado' => true]);
}

/* ---------- Super-administradora (dueña de la plataforma) ---------- */

function estudios_listar() {
  require_superadmin();
  asegurar_cols_jurisdiccion(db());
  $extra = '';
  try {
    // ¿existe la columna? si no, se omite para no romper la lista.
    $c = db()->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='estudios' AND COLUMN_NAME='prueba_hasta'")->fetchColumn();
    if ((int)$c > 0) $extra = ', e.jurisdiccion, e.prueba_hasta';
  } catch (Throwable $e) {}
  $sql = 'SELECT e.id, e.nombre, e.tipo, e.creado_en' . $extra . ',
                 (SELECT u.email FROM usuarios u WHERE u.estudio_id = e.id AND u.es_admin = 1 ORDER BY u.id ASC LIMIT 1) AS email_admin,
                 (SELECT COUNT(*) FROM usuarios u WHERE u.estudio_id = e.id AND u.rol = "profesional") AS abogadas,
                 (SELECT COUNT(*) FROM usuarios u WHERE u.estudio_id = e.id AND u.rol = "cliente")     AS clientes
          FROM estudios e ORDER BY e.creado_en DESC';
  json_ok(db()->query($sql)->fetchAll());
}

/* Editar un estudio (nombre y/o tipo). Solo super-admin. */
function estudio_editar($id) {
  $admin = require_superadmin();
  if (!$id) json_error('Falta el estudio.');
  $st = db()->prepare('SELECT id FROM estudios WHERE id = ?');
  $st->execute([$id]);
  if (!$st->fetch()) json_error('Estudio no encontrado.', 404);

  $sets = []; $vals = [];
  if (field('nombre', '__NO__') !== '__NO__') {
    $n = trim((string)field('nombre'));
    if ($n !== '') { $sets[] = 'nombre = ?'; $vals[] = $n; }
  }
  if (field('tipo', '__NO__') !== '__NO__') {
    $sets[] = 'tipo = ?'; $vals[] = (field('tipo') === 'individual' ? 'individual' : 'estudio');
  }
  /* Prueba (v48): 'prueba' = 'activar' quita la prueba (cuenta definitiva);
     'prueba_dias' 7/14/30 renueva el plazo desde hoy. Los datos NO se tocan. */
  if (field('prueba', '__NO__') !== '__NO__' && field('prueba') === 'activar') {
    asegurar_cols_jurisdiccion(db());
    $sets[] = 'prueba_hasta = NULL';
  } elseif (field('prueba_dias', '__NO__') !== '__NO__') {
    $d = (int)field('prueba_dias');
    if (in_array($d, [7, 14, 30], true)) {
      asegurar_cols_jurisdiccion(db());
      $sets[] = 'prueba_hasta = ?'; $vals[] = date('Y-m-d', strtotime("+$d days"));
    }
  }
  if (!$sets) json_error('No hay nada para actualizar.');
  $vals[] = $id;
  db()->prepare('UPDATE estudios SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
  json_ok(['actualizado' => true]);
}

/* Eliminar un estudio COMPLETO (con todas sus abogadas/clientes/datos).
   Solo super-admin. No permite borrar el propio estudio. */
function estudio_eliminar($id) {
  $admin = require_superadmin();
  if (!$id) json_error('Falta el estudio.');
  if ((int)$id === (int)$admin['estudio_id']) json_error('No podés eliminar tu propio estudio.', 403);
  $st = db()->prepare('SELECT id FROM estudios WHERE id = ?');
  $st->execute([$id]);
  if (!$st->fetch()) json_error('Estudio no encontrado.', 404);
  // La base borra en cascada usuarios, causas, etc. de ese estudio.
  db()->prepare('DELETE FROM estudios WHERE id = ?')->execute([$id]);
  json_ok(['eliminado' => true]);
}

/* Crea un estudio NUEVO y su abogada administradora (con clave temporal). */
function estudio_crear() {
  require_superadmin();
  $estudio = trim((string)field('estudio'));
  $nombre  = trim((string)field('nombre'));
  $email   = strtolower(trim((string)field('email')));
  if (!$estudio || !$nombre || !$email) json_error('Indicá nombre del estudio, nombre de la abogada y email.');
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('El email no es válido.');

  $chk = db()->prepare('SELECT 1 FROM usuarios WHERE email = ?');
  $chk->execute([$email]);
  if ($chk->fetch()) json_error('Ya existe una cuenta con ese email.');

  $tipo = field('tipo') === 'individual' ? 'individual' : 'estudio';
  /* Jurisdicción (v47): la elige la super-administradora al dar el alta.
     'catamarca' = versión completa; 'generica' = versión adaptada. */
  $juris = field('jurisdiccion') === 'generica' ? 'generica' : 'catamarca';
  $unidad = trim((string)field('unidad_hon'));
  if ($unidad === '') $unidad = ($juris === 'generica') ? 'Jus' : 'IUS';
  /* Período de prueba (v48): 0 = sin prueba (cuenta activa). 7/14/30 = días. */
  $dias = (int)field('prueba_dias');
  $prueba = in_array($dias, [7, 14, 30], true) ? date('Y-m-d', strtotime("+$dias days")) : null;
  $temp = generar_clave_temporal();
  $pdo = db();
  asegurar_cols_jurisdiccion($pdo);
  $pdo->beginTransaction();
  $pdo->prepare('INSERT INTO estudios (nombre, tipo, jurisdiccion, unidad_hon, prueba_hasta) VALUES (?,?,?,?,?)')
      ->execute([$estudio, $tipo, $juris, $unidad, $prueba]);
  $eid = (int)$pdo->lastInsertId();
  // La abogada invitada es la ADMINISTRADORA de su propio estudio (es_admin=1),
  // pero NO super-administradora.
  $pdo->prepare('INSERT INTO usuarios (estudio_id, nombre, email, password_hash, rol, es_admin, debe_cambiar_clave)
                 VALUES (?,?,?,?,?,1,1)')
      ->execute([$eid, $nombre, $email, hash_password($temp), 'profesional']);
  // Copiar feriados globales a su estudio. En genérica, SOLO los nacionales
  // (los provinciales de Catamarca no aplican en otra provincia).
  $sqlFer = 'INSERT INTO feriados (estudio_id, fecha, anual, nombre, tipo)
             SELECT ?, fecha, anual, nombre, tipo FROM feriados WHERE estudio_id IS NULL';
  if ($juris === 'generica') $sqlFer .= " AND tipo = 'nacional'";
  $pdo->prepare($sqlFer)->execute([$eid]);
  $pdo->commit();

  json_ok(['estudio_id' => $eid, 'clave_temporal' => $temp], 201);
}
