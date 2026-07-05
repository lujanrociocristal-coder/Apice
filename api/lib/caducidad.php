<?php
/* ============================================================================
 * CADUCIDAD DE INSTANCIA - calculo compartido (servidor)
 *
 * Replica en PHP el mismo criterio que ya usa el frontend en la pantalla
 * "Caducidad de instancia" (ver calcCaducidad() en index.html), para poder
 * avisar por push aunque la app este cerrada.
 *
 * Base legal: CPCyC de Catamarca (Ley 2339, arts. 310-318).
 * Calculo ORIENTATIVO: no descuenta feria judicial (enero/julio) ni
 * reemplaza la verificacion del plazo en el expediente real.
 *
 * IMPORTANTE: igual que en el frontend, solo cuenta como "ultimo impulso"
 * el movimiento de la bitacora que la abogada marco explicitamente con el
 * boton de impulso (columna movimientos.imp = 1). No es la fecha mas
 * reciente cargada, sino la marcada.
 * ========================================================================== */

function cad_tipos_meses() {
    return [
          'ordinario'  => 6,
          'sumarisimo' => 3,
          'ejecutivo'  => 3,
          'incidente'  => 3,
          'segunda'    => 3,
          'nocaduca'   => 0,
        ];
}

/* Replica parseFechaCad() del frontend: entiende dd/mm/aaaa, mm/aaaa o
   * un anio suelto (toma la ultima coincidencia si hay varias). */
function parse_fecha_cad($s) {
    if (!$s) return null;
    $s = str_replace(['-', '-'], '-', (string)$s);

  if (preg_match_all('/(\d{1,2})\/(\d{1,2})\/(\d{4})/', $s, $m)) {
        $n = count($m[0]) - 1;
        $d = DateTime::createFromFormat('!Y-n-j', $m[3][$n] . '-' . $m[2][$n] . '-' . $m[1][$n]);
        if ($d) return $d;
  }
    if (preg_match_all('/(\d{1,2})\/(\d{4})/', $s, $m)) {
          $n = count($m[0]) - 1;
          $d = DateTime::createFromFormat('!Y-n-j', $m[2][$n] . '-' . $m[1][$n] . '-1');
          if ($d) return $d;
    }
    if (preg_match_all('/(19|20)\d{2}/', $s, $m)) {
          $n = count($m[0]) - 1;
          $d = DateTime::createFromFormat('!Y-n-j', $m[0][$n] . '-1-1');
          if ($d) return $d;
    }
    return null;
}

/* Causas de un estudio con plazo de caducidad vencido o por vencer dentro de
   * $diasLimite dias. Mismo criterio que calcCaducidad() del frontend:
 * - tipo = cad_tipo de la causa, o 'ordinario' si no se cargo.
   * - 'nocaduca' (o sin meses configurados) => no corre plazo, se excluye.
   * - causa "En pausa" (procesal = 'A DESPACHO') => el plazo no corre (art.
                                                                         *   313 inc. 3), se excluye.
   * - toma la fecha del movimiento marcado como impulso (imp = 1) mas
   *   reciente. Si no hay ninguno marcado, no se puede calcular: se excluye
   *   (igual que "sinImpulso" en el frontend).
   * - vencimiento = fecha del ultimo impulso + meses del tipo de proceso.
   */
function causas_por_vencer($estudioId, $diasLimite = 15) {
    $pdo = db();
    $meses = cad_tipos_meses();

  $st = $pdo->prepare("SELECT id, caratula, cad_tipo, procesal FROM causas
                          WHERE estudio_id = ? AND estado <> 'finalizada'");
    $st->execute([(int)$estudioId]);
    $causas = $st->fetchAll();

  $out = [];
    foreach ($causas as $c) {
          $tipo = $c['cad_tipo'] ?: 'ordinario';
          $m = $meses[$tipo] ?? 0;
          if ($tipo === 'nocaduca' || !$m) continue;
          if ($c['procesal'] === 'A DESPACHO') continue;

      $sm = $pdo->prepare("SELECT fecha_txt FROM movimientos WHERE causa_id = ? AND imp = 1");
          $sm->execute([(int)$c['id']]);
          $lastFecha = null;
          foreach ($sm->fetchAll(PDO::FETCH_COLUMN) as $ftxt) {
                  $d = parse_fecha_cad($ftxt);
                  if ($d && (!$lastFecha || $d > $lastFecha)) $lastFecha = $d;
          }
          if (!$lastFecha) continue;

      $venc = clone $lastFecha;
          $venc->modify('+' . (int)$m . ' months');
          $hoy = new DateTime('today');
          $diasRest = (int)$hoy->diff($venc)->format('%R%a');

      if ($diasRest <= $diasLimite) {
              $out[] = [
                        'id'       => (int)$c['id'],
                        'caratula' => $c['caratula'],
                        'venc'     => $venc->format('d/m/Y'),
                        'dias'     => $diasRest,
                      ];
      }
    }
    usort($out, function ($a, $b) { return $a['dias'] <=> $b['dias']; }); // mas urgente primero
  return $out;
}

/* Arma el texto (cuerpo de aviso) para 1+ causas por vencer. */
  function caducidad_texto($venc) {
      if (!$venc) return '';
      if (count($venc) === 1) {
            $c = $venc[0];
            $tit = mb_strimwidth($c['caratula'], 0, 60, '...');
            if ($c['dias'] < 0) {
                    return 'El plazo de "' . $tit . '" vencio hace ' . abs($c['dias']) . ' dia(s). Verificalo antes de que se acuse la caducidad.';
            }
            if ($c['dias'] === 0) {
                    return 'El plazo de "' . $tit . '" vence HOY. Revisa el expediente.';
            }
            return 'El plazo de "' . $tit . '" vence en ' . $c['dias'] . ' dia(s) (' . $c['venc'] . ').';
      }
      $vencidas = count(array_filter($venc, function ($v) { return $v['dias'] < 0; }));
      if ($vencidas > 0) {
            return 'Tenes ' . count($venc) . ' causas con plazo de caducidad vencido o por vencer (' . $vencidas . ' ya vencidas). Revisalas en "Caducidad de instancia".';
