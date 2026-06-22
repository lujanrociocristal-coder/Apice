<?php
/* ============================================================================
 *  ESTADO DE LA APP  (/api/estado/...)
 *
 *  FASE 2: Las causas se leen/escriben en la tabla SQL relacional.
 *  El resto de claves (agenda, clientes, config, guia) siguen en estado_app.
 *
 *    GET    /api/estado/{clave}   -> devuelve { value: "...json..." } o null
 *    PUT    /api/estado/{clave}   -> guarda el bloque (cuerpo: { value: ... })
 *    DELETE /api/estado/{clave}   -> borra el bloque
 * ========================================================================== */

function handle_estado($method, $resto) {
  $u = require_login();
  $clave = $resto[0] ?? '';
  if ($clave === '') json_error('Falta la clave del bloque.');
  if (!preg_match('/^[A-Za-z0-9_.\-]{1,80}$/', $clave)) json_error('Clave no válida.');

  $eid = (int)$u['estudio_id'];

  // ══════════════════════════════════════════════════════════════════════════
  //  INTERCEPCIÓN FASE 2: CAUSAS  ->  tabla SQL relacional
  // ══════════════════════════════════════════════════════════════════════════
  if ($clave === 'gestor_causas_v6') {

    if ($method === 'GET') {
      $pdo = db();
      $st = $pdo->prepare('SELECT * FROM causas WHERE estudio_id = ? ORDER BY id DESC');
      $st->execute([$eid]);
      $rows = $st->fetchAll();

      $stMov = $pdo->prepare('SELECT fecha_txt, texto, inicio FROM movimientos WHERE causa_id = ? ORDER BY id ASC');

      $arr = [];
      foreach ($rows as $c) {
        $stMov->execute([$c['id']]);
        $movs = $stMov->fetchAll();
        $bitacora = array_map(fn($m) => [
          'fecha'  => $m['fecha_txt'],
          'texto'  => $m['texto'],
          'inicio' => (bool)$m['inicio'],
        ], $movs);

        $arr[] = [
          'id'            => $c['uuid'] ?: 'c_' . $c['id'],
          'estado'        => $c['estado'],
          'caratula'      => $c['caratula'],
          'cliente'       => $c['cliente_nombre'],
          'expediente'    => $c['expediente'],
          'cuij'          => $c['cuij'] ?? null,
          'objeto'        => $c['objeto'],
          'fuero'         => $c['fuero'],
          'juzgado'       => $c['juzgado'],
          'juez'          => $c['juez'],
          'secretaria'    => $c['secretaria'],
          'letrada'       => $c['letrada'],
          'clienteEs'     => $c['cliente_es'] ?? 'activa',
          'posicion'      => $c['posicion'],
          'actorRol'      => $c['actor_rol'],
          'actor'         => $c['actor'],
          'actorPat'      => $c['actor_pat'] ?? null,
          'demandadoRol'  => $c['demandado_rol'],
          'demandado'     => $c['demandado'],
          'demandadoPat'  => $c['demandado_pat'] ?? null,
          'clienteCalidad'=> $c['cliente_calidad'] ?? null,
          'dirJuzId'      => $c['dir_juz_id'] ?? null,
          'procesal'      => $c['procesal'] ?? null,
          'materia'       => $c['materias'] ? json_decode($c['materias'], true) : [],
          'bitacora'      => $bitacora,
          'ultimoMov'     => !empty($bitacora) ? end($bitacora) : null,
          'documentos'    => [],
          'pendientes'    => [],
          'alertas'       => [],
          'ficha'         => $c['ficha_id'] ?? '',
          'folder'        => $c['folder_id'] ?? '',
        ];
      }

      json_ok(['value' => json_encode($arr, JSON_UNESCAPED_UNICODE)]);
    }

    if ($method === 'PUT') {
      if ($u['rol'] !== 'profesional') json_error('Los clientes no pueden modificar datos.', 403);
      $valor = field('value');
      if (is_string($valor)) $valor = json_decode($valor, true);
      if (!is_array($valor)) json_error('Formato inválido.');

      $pdo = db();
      $upsert = $pdo->prepare(
        'INSERT INTO causas
           (estudio_id, owner_id, uuid, estado, caratula, cliente_nombre,
            expediente, objeto, fuero, juzgado, juez, secretaria,
            letrada, posicion, actor_rol, actor, demandado_rol, demandado,
            materias)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
           estado=VALUES(estado), caratula=VALUES(caratula),
           cliente_nombre=VALUES(cliente_nombre), expediente=VALUES(expediente),
           objeto=VALUES(objeto), fuero=VALUES(fuero), juzgado=VALUES(juzgado),
           juez=VALUES(juez), secretaria=VALUES(secretaria),
           letrada=VALUES(letrada), posicion=VALUES(posicion),
           actor_rol=VALUES(actor_rol), actor=VALUES(actor),
           demandado_rol=VALUES(demandado_rol), demandado=VALUES(demandado),
           materias=VALUES(materias)'
      );
      $insMov = $pdo->prepare(
        'INSERT INTO movimientos (causa_id, fecha_txt, texto, inicio) VALUES (?,?,?,?)'
      );
      $getId = $pdo->prepare('SELECT id FROM causas WHERE uuid = ?');

      foreach ($valor as $c) {
        if (!isset($c['id'])) continue;
        $mat  = isset($c['materia']) ? json_encode($c['materia'], JSON_UNESCAPED_UNICODE) : null;

        $upsert->execute([
          $eid, $u['id'], $c['id'],
          $c['estado']      ?? 'preparacion', $c['caratula']   ?? '',
          $c['cliente']     ?? null,           $c['expediente'] ?? null,
          $c['objeto']      ?? null,           $c['fuero']      ?? null,
          $c['juzgado']     ?? null,           $c['juez']       ?? null,
          $c['secretaria']  ?? null,           $c['letrada']    ?? null,
          $c['posicion']    ?? null,           $c['actorRol']   ?? null,
          $c['actor']       ?? null,           $c['demandadoRol'] ?? null,
          $c['demandado']   ?? null,           $mat,
        ]);

        $getId->execute([$c['id']]);
        $causa_id = $getId->fetchColumn();
        if ($causa_id && isset($c['bitacora']) && is_array($c['bitacora'])) {
          $pdo->prepare('DELETE FROM movimientos WHERE causa_id = ?')->execute([$causa_id]);
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

      json_ok(['guardado' => true]);
    }

    if ($method === 'DELETE') {
      if ($u['rol'] !== 'profesional') json_error('Los clientes no pueden borrar datos.', 403);
      // Borrar todo el estado no aplica aquí; ignoramos silenciosamente.
      json_ok(['borrado' => true]);
    }

    json_error('Método no permitido.', 405);
  }

  // ══════════════════════════════════════════════════════════════════════════
  //  FLUJO ORIGINAL para agenda, clientes, config, guía, etc.
  // ══════════════════════════════════════════════════════════════════════════
  if ($method === 'GET') {
    $st = db()->prepare('SELECT valor FROM estado_app WHERE estudio_id = ? AND clave = ?');
    $st->execute([$eid, $clave]);
    $v = $st->fetchColumn();
    json_ok($v === false ? null : ['value' => $v]);
  }

  if ($method === 'PUT') {
    if ($u['rol'] !== 'profesional') json_error('Los clientes no pueden modificar datos.', 403);
    $valor = field('value');
    if (!is_string($valor)) $valor = json_encode($valor, JSON_UNESCAPED_UNICODE);
    db()->prepare('INSERT INTO estado_app (estudio_id, clave, valor) VALUES (?,?,?)
                   ON DUPLICATE KEY UPDATE valor = VALUES(valor)')
        ->execute([$eid, $clave, $valor]);
    json_ok(['guardado' => true]);
  }

  if ($method === 'DELETE') {
    if ($u['rol'] !== 'profesional') json_error('Los clientes no pueden borrar datos.', 403);
    db()->prepare('DELETE FROM estado_app WHERE estudio_id = ? AND clave = ?')->execute([$eid, $clave]);
    json_ok(['borrado' => true]);
  }

  json_error('Método no permitido.', 405);
}
