<?php
/* ============================================================================
 *  ESTADO DE LA APP  (/api/estado/...)
 *
 *  FASE 2 COMPLETA — TODOS los sub-datos de la causa persisten en SQL:
 *   - gestor_causas_v6  → tabla causas (incluye honorarios, documentos,
 *                          pendientes/tareas, alertas, bitacora/movimientos)
 *   - gestor_cli_v1     → tabla clientes
 *   - gestor_aud_v1     → tabla audiencias
 *   - gestor_dir_v1     → tabla guia_judicial
 *   - gestor_cfg_v9     → queda en estado_app (configuración simple)
 *   - cualquier otra    → flujo original estado_app
 * ========================================================================== */

function handle_estado($method, $resto) {
  $u   = require_login();
  $clave = $resto[0] ?? '';
  if ($clave === '') json_error('Falta la clave del bloque.');
  if (!preg_match('/^[A-Za-z0-9_.\-]{1,80}$/', $clave)) json_error('Clave no válida.');
  $eid = (int)$u['estudio_id'];
  $pdo = db();

  // Agrega columna JSON si no existe en una tabla
  function col_json($pdo, $tabla, $columna) {
    $r = $pdo->query("SHOW COLUMNS FROM `{$tabla}` LIKE '{$columna}'");
    if ($r->rowCount() === 0)
      $pdo->exec("ALTER TABLE `{$tabla}` ADD COLUMN `{$columna}` JSON NULL");
  }

  // ══════════════════════════════════════════════════════════════════════════
  //  CAUSAS  (incluye pendientes, honorarios, documentos, alertas)
  // ══════════════════════════════════════════════════════════════════════════
  if ($clave === 'gestor_causas_v6') {

    if ($method === 'GET') {
      $st = $pdo->prepare('SELECT * FROM causas WHERE estudio_id=? ORDER BY id DESC');
      $st->execute([$eid]);
      $rows = $st->fetchAll();
      $stMov = $pdo->prepare('SELECT fecha_txt,texto,inicio FROM movimientos WHERE causa_id=? ORDER BY id ASC');
      $arr = [];
      foreach ($rows as $c) {
        $stMov->execute([$c['id']]);
        $movs = $stMov->fetchAll();
        $bit  = array_map(fn($m)=>['fecha'=>$m['fecha_txt'],'texto'=>$m['texto'],'inicio'=>(bool)$m['inicio']], $movs);
        $arr[] = [
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
      json_ok(['value' => json_encode($arr, JSON_UNESCAPED_UNICODE)]);
    }

    if ($method === 'PUT') {
      if ($u['rol'] !== 'profesional') json_error('Sin permiso.',403);

      // Asegurar que existen las columnas JSON en causas
      col_json($pdo, 'causas', 'honorarios');
      col_json($pdo, 'causas', 'documentos');
      col_json($pdo, 'causas', 'pendientes');
      col_json($pdo, 'causas', 'alertas');

      $valor = field('value');
      if (is_string($valor)) $valor = json_decode($valor,true);
      if (!is_array($valor)) json_error('Formato inválido.');

      $ups = $pdo->prepare('
        INSERT INTO causas
          (estudio_id,owner_id,uuid,estado,procesal,caratula,cliente_nombre,
           expediente,cuij,objeto,fuero,juzgado,juez,secretaria,letrada,
           cliente_es,actor_rol,actor,demandado_rol,demandado,cliente_calidad,
           posicion,materias,honorarios,documentos,pendientes,alertas)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
          estado=VALUES(estado),procesal=VALUES(procesal),caratula=VALUES(caratula),
          cliente_nombre=VALUES(cliente_nombre),expediente=VALUES(expediente),cuij=VALUES(cuij),
          objeto=VALUES(objeto),fuero=VALUES(fuero),juzgado=VALUES(juzgado),juez=VALUES(juez),
          secretaria=VALUES(secretaria),letrada=VALUES(letrada),cliente_es=VALUES(cliente_es),
          actor_rol=VALUES(actor_rol),actor=VALUES(actor),demandado_rol=VALUES(demandado_rol),
          demandado=VALUES(demandado),cliente_calidad=VALUES(cliente_calidad),
          posicion=VALUES(posicion),materias=VALUES(materias),
          honorarios=VALUES(honorarios),documentos=VALUES(documentos),
          pendientes=VALUES(pendientes),alertas=VALUES(alertas)
      ');
      $insMov = $pdo->prepare('INSERT INTO movimientos (causa_id,fecha_txt,texto,inicio) VALUES (?,?,?,?)');
      $getId  = $pdo->prepare('SELECT id FROM causas WHERE uuid=?');

      foreach ($valor as $c) {
        if (!isset($c['id'])) continue;
        $j = fn($v) => isset($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : null;
        $ups->execute([
          $eid,$u['id'],$c['id'],
          $c['estado']??'preparacion',$c['procesal']??null,$c['caratula']??'',
          $c['cliente']??null,$c['expediente']??null,$c['cuij']??null,
          $c['objeto']??null,$c['fuero']??null,$c['juzgado']??null,$c['juez']??null,
          $c['secretaria']??null,$c['letrada']??null,$c['clienteEs']??'activa',
          $c['actorRol']??null,$c['actor']??null,$c['demandadoRol']??null,
          $c['demandado']??null,$c['clienteCalidad']??null,$c['posicion']??null,
          $j($c['materia']??null),
          $j($c['honorarios']??null),
          $j($c['documentos']??null),
          $j($c['pendientes']??null),
          $j($c['alertas']??null),
        ]);
        $getId->execute([$c['id']]);
        $cid = $getId->fetchColumn();
        if ($cid && isset($c['bitacora']) && is_array($c['bitacora'])) {
          $pdo->prepare('DELETE FROM movimientos WHERE causa_id=?')->execute([$cid]);
          foreach ($c['bitacora'] as $m)
            $insMov->execute([$cid,$m['fecha']??'',$m['texto']??'',empty($m['inicio'])?0:1]);
        }
      }
      json_ok(['guardado'=>true]);
    }
    if ($method === 'DELETE') json_ok(['borrado'=>true]);
    json_error('Método no permitido.',405);
  }

  // ══════════════════════════════════════════════════════════════════════════
  //  CLIENTES
  //  El frontend guarda clientes como OBJETO: { "Nombre": { datos... } }
  //  El uuid en BD es: "cli_" + md5(nombre) o simplemente el nombre codificado
  // ══════════════════════════════════════════════════════════════════════════
  if ($clave === 'gestor_cli_v1') {

    if ($method === 'GET') {
      $st = $pdo->prepare('SELECT * FROM clientes WHERE estudio_id=? ORDER BY nombre ASC');
      $st->execute([$eid]);
      $rows = $st->fetchAll();
      // Reconstruir el diccionario { nombre => datos } que espera el frontend
      $obj = [];
      foreach ($rows as $c) {
        $obj[$c['nombre']] = [
          'tipo'        => $c['tipo'],
          'cuit'        => $c['dni_cuit'] ?? '',
          'dni'         => $c['dni_cuit'] ?? '',
          'email'       => $c['email'] ?? '',
          'telefono'    => $c['telefono'] ?? '',
          'whatsapp'    => $c['telefono'] ?? '',
          'domicilio'   => $c['domicilio'] ?? '',
          'notas'       => $c['notas'] ?? '',
          '_createdAt'  => strtotime($c['creado_en']) * 1000,
        ];
      }
      json_ok(['value' => json_encode($obj, JSON_UNESCAPED_UNICODE)]);
    }

    if ($method === 'PUT') {
      if ($u['rol'] !== 'profesional') json_error('Sin permiso.',403);
      $valor = field('value');
      if (is_string($valor)) $valor = json_decode($valor, true);
      // $valor es { "Nombre" => { datos } }
      if (!is_array($valor)) json_error('Formato inválido.');

      $ups = $pdo->prepare('
        INSERT INTO clientes (estudio_id, uuid, nombre, tipo, dni_cuit, email, telefono, domicilio, notas)
        VALUES (?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
          tipo=VALUES(tipo), dni_cuit=VALUES(dni_cuit),
          email=VALUES(email), telefono=VALUES(telefono),
          domicilio=VALUES(domicilio), notas=VALUES(notas)
      ');
      foreach ($valor as $nombre => $c) {
        if (!is_string($nombre) || trim($nombre) === '') continue;
        $uuid = 'cli_' . md5($nombre . '_' . $eid);
        $tipo = ($c['tipo'] ?? 'fisica') === 'juridica' ? 'juridica' : 'fisica';
        $ups->execute([
          $eid, $uuid, $nombre, $tipo,
          $c['cuit'] ?? ($c['dni'] ?? null),
          $c['email'] ?? null,
          $c['telefono'] ?? ($c['whatsapp'] ?? null),
          $c['domicilio'] ?? null,
          $c['notas'] ?? null,
        ]);
      }
      json_ok(['guardado' => true]);
    }

    if ($method === 'DELETE') {
      if ($u['rol'] !== 'profesional') json_error('Sin permiso.',403);
      json_ok(['borrado' => true]);
    }
    json_error('Método no permitido.',405);
  }

  // ══════════════════════════════════════════════════════════════════════════
  //  AUDIENCIAS / AGENDA
  // ══════════════════════════════════════════════════════════════════════════
  if ($clave === 'gestor_aud_v1') {

    if ($method === 'GET') {
      $st = $pdo->prepare('SELECT * FROM audiencias WHERE estudio_id=? ORDER BY fecha ASC, hora ASC');
      $st->execute([$eid]);
      $rows = $st->fetchAll();
      $arr = [];
      foreach ($rows as $a) {
        $arr[] = [
          'id'            => $a['uuid'] ?? 'aud_'.$a['id'],
          'tipo'          => $a['tipo'],
          'fecha'         => $a['fecha'],
          'hora'          => $a['hora'],
          'detalle'       => $a['detalle'],
          'clienteNombre' => $a['cliente_nombre'],
          'materia'       => $a['materia'],
          'cliAsiste'     => (bool)$a['cli_asiste'],
          'modalidad'     => $a['modalidad'],
          'lugar'         => $a['lugar'],
          'link'          => $a['link'],
        ];
      }
      json_ok(['value' => json_encode($arr, JSON_UNESCAPED_UNICODE)]);
    }

    if ($method === 'PUT') {
      if ($u['rol'] !== 'profesional') json_error('Sin permiso.',403);
      // Agregar columna uuid si no existe
      $r = $pdo->query("SHOW COLUMNS FROM audiencias LIKE 'uuid'");
      if ($r->rowCount()===0) $pdo->exec("ALTER TABLE audiencias ADD COLUMN uuid VARCHAR(80) NULL");
      $r2 = $pdo->query("SHOW INDEX FROM audiencias WHERE Key_name='uq_aud_uuid'");
      if ($r2->rowCount()===0) $pdo->exec("ALTER TABLE audiencias ADD UNIQUE KEY uq_aud_uuid (uuid)");

      $valor = field('value');
      if (is_string($valor)) $valor = json_decode($valor,true);
      if (!is_array($valor)) json_error('Formato inválido.');

      $ups = $pdo->prepare('
        INSERT INTO audiencias (estudio_id,uuid,tipo,fecha,hora,detalle,cliente_nombre,materia,cli_asiste,modalidad,lugar,link)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
          tipo=VALUES(tipo),fecha=VALUES(fecha),hora=VALUES(hora),detalle=VALUES(detalle),
          cliente_nombre=VALUES(cliente_nombre),materia=VALUES(materia),
          cli_asiste=VALUES(cli_asiste),modalidad=VALUES(modalidad),lugar=VALUES(lugar),link=VALUES(link)
      ');
      foreach ($valor as $a) {
        $uuid = $a['id'] ?? null;
        if (!$uuid) continue;
        $tipo = in_array($a['tipo']??'', ['juzgado','mediacion','cita']) ? $a['tipo'] : 'cita';
        // convertir fecha dd/mm/yyyy a yyyy-mm-dd si es necesario
        $fecha = $a['fecha'] ?? date('Y-m-d');
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fecha)) {
          [$d,$m,$y] = explode('/',$fecha);
          $fecha = "$y-$m-$d";
        }
        $ups->execute([
          $eid,$uuid,$tipo,$fecha,
          $a['hora']??null,$a['detalle']??null,
          $a['clienteNombre']??null,$a['materia']??null,
          empty($a['cliAsiste'])?0:1,$a['modalidad']??null,$a['lugar']??null,$a['link']??null,
        ]);
      }
      json_ok(['guardado'=>true]);
    }

    if ($method === 'DELETE') {
      if ($u['rol'] !== 'profesional') json_error('Sin permiso.',403);
      json_ok(['borrado'=>true]);
    }
    json_error('Método no permitido.',405);
  }

  // ══════════════════════════════════════════════════════════════════════════
  //  GUÍA JUDICIAL / DIRECTORIO
  // ══════════════════════════════════════════════════════════════════════════
  if ($clave === 'gestor_dir_v1') {

    if ($method === 'GET') {
      $st = $pdo->prepare('SELECT * FROM guia_judicial WHERE estudio_id=? ORDER BY nombre ASC');
      $st->execute([$eid]);
      $rows = $st->fetchAll();
      $arr = [];
      foreach ($rows as $g) {
        $arr[] = [
          'id'          => $g['ref'] ?: 'dir_'.$g['id'],
          'categoria'   => $g['categoria'],
          'nombre'      => $g['nombre'],
          'rol'         => $g['rol'],
          'integrantes' => $g['integrantes'] ? json_decode($g['integrantes'],true) : [],
          'direccion'   => $g['direccion'],
          'tel'         => $g['tel'],
          'email'       => $g['email'],
          'notas'       => $g['notas'],
          'oficial'     => (bool)$g['oficial'],
          'actualizado' => $g['actualizado'],
        ];
      }
      json_ok(['value' => json_encode($arr, JSON_UNESCAPED_UNICODE)]);
    }

    if ($method === 'PUT') {
      if ($u['rol'] !== 'profesional') json_error('Sin permiso.',403);
      $valor = field('value');
      if (is_string($valor)) $valor = json_decode($valor,true);
      if (!is_array($valor)) json_error('Formato inválido.');

      // Asegurar índice único en ref
      $r = $pdo->query("SHOW INDEX FROM guia_judicial WHERE Key_name='uq_guia_ref'");
      if ($r->rowCount()===0)
        $pdo->exec("ALTER TABLE guia_judicial ADD UNIQUE KEY uq_guia_ref (estudio_id, ref(60))");

      $cats = ['juzgado','mediacion','equipo','mesa','asesoria','colega'];
      $ups = $pdo->prepare('
        INSERT INTO guia_judicial (estudio_id,ref,categoria,nombre,rol,integrantes,direccion,tel,email,notas,oficial,actualizado)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
          categoria=VALUES(categoria),nombre=VALUES(nombre),rol=VALUES(rol),
          integrantes=VALUES(integrantes),direccion=VALUES(direccion),tel=VALUES(tel),
          email=VALUES(email),notas=VALUES(notas),actualizado=VALUES(actualizado)
      ');
      foreach ($valor as $g) {
        $ref = $g['id'] ?? null;
        if (!$ref) continue;
        $cat = in_array($g['categoria']??'', $cats) ? $g['categoria'] : 'juzgado';
        $int = isset($g['integrantes']) ? json_encode($g['integrantes'],JSON_UNESCAPED_UNICODE) : null;
        $ups->execute([
          $eid,$ref,$cat,$g['nombre']??'Sin nombre',
          $g['rol']??null,$int,$g['direccion']??null,
          $g['tel']??null,$g['email']??null,$g['notas']??null,
          empty($g['oficial'])?0:1,$g['actualizado']??null,
        ]);
      }
      json_ok(['guardado'=>true]);
    }

    if ($method === 'DELETE') {
      if ($u['rol'] !== 'profesional') json_error('Sin permiso.',403);
      json_ok(['borrado'=>true]);
    }
    json_error('Método no permitido.',405);
  }

  // ══════════════════════════════════════════════════════════════════════════
  //  FLUJO ORIGINAL  (gestor_cfg_v9 y cualquier otra clave futura)
  // ══════════════════════════════════════════════════════════════════════════
  if ($method === 'GET') {
    $st = $pdo->prepare('SELECT valor FROM estado_app WHERE estudio_id=? AND clave=?');
    $st->execute([$eid,$clave]);
    $v = $st->fetchColumn();
    json_ok($v===false ? null : ['value'=>$v]);
  }

  if ($method === 'PUT') {
    if ($u['rol'] !== 'profesional') json_error('Sin permiso.',403);
    $valor = field('value');
    if (!is_string($valor)) $valor = json_encode($valor,JSON_UNESCAPED_UNICODE);
    $pdo->prepare('INSERT INTO estado_app (estudio_id,clave,valor) VALUES (?,?,?)
                   ON DUPLICATE KEY UPDATE valor=VALUES(valor)')
        ->execute([$eid,$clave,$valor]);
    json_ok(['guardado'=>true]);
  }

  if ($method === 'DELETE') {
    if ($u['rol'] !== 'profesional') json_error('Sin permiso.',403);
    $pdo->prepare('DELETE FROM estado_app WHERE estudio_id=? AND clave=?')->execute([$eid,$clave]);
    json_ok(['borrado'=>true]);
  }

  json_error('Método no permitido.',405);
}
