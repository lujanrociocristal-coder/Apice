<?php
/* ============================================================================
 * CADUCIDAD DE INSTANCIA — cálculo compartido (servidor)
 *
 * Replica en PHP el mismo criterio que ya usa el frontend en la pantalla
 * "Caducidad de instancia" (ver calcCaducidad() en index.html), para poder
 * avisar por push aunque la app esté cerrada.
 *
 * Base legal: CPCyC de Catamarca (Ley 2339, arts. 310-318).
 * Cálculo ORIENTATIVO: no descuenta feria judicial (enero/julio) ni
 * reemplaza la verificación del plazo en el expediente real.
 * ========================================================================== */

function cad_tipos_meses() {
  return [
    'ordinario' => 6,
    'sumarisimo' => 3,
    'ejecutivo' => 3,
    'incidente' => 3,
    'segunda' => 3,
    'nocaduca' => 0,
    ];
}

function causas_por_vencer($estudioId, $diasLimite = 15) {
  $pdo = db();
  $meses = cad_tipos_meses();

$st = $pdo->prepare("SELECT id, caratula, cad_tipo, procesal FROM causas WHERE estudio_id = ? AND estado <> 'finalizada'");
  $st->execute([(int)$estudioId]);
  $causas = $st->fetchAll();

$out = [];
  foreach ($causas as $c) {
    $tipo = $c['cad_tipo'] ?: 'ordinario';
    $m = $meses[$tipo] ?? 0;
    if ($tipo === 'nocaduca' || !$m) continue;
    if ($c['procesal'] === 'A DESPACHO') continue;

  $sm = $pdo->prepare('SELECT MAX(fecha_iso) FROM movimientos WHERE causa_id = ? AND fecha_iso IS NOT NULL');
    $sm->execute([(int)$c['id']]);
    $lastIso = $sm->fetchColumn();
    if (!$lastIso) continue;

  $venc = new DateTime($lastIso);
    $venc->modify('+' . (int)$m . ' months');
    $hoy = new DateTime('today');
    $diasRest = (int)$hoy->diff($venc)->format('%R%a');

  if ($diasRest <= $diasLimite) {
    $out[] = [
      'id' => (int)$c['id'],
      'caratula' => $c['caratula'],
      'venc' => $venc->format('d/m/Y'),
      'dias' => $diasRest,
      ];
  }
  }
  usort($out, function ($a, $b) { return $a['dias'] <=> $b['dias']; });
  return $out;
}

function caducidad_texto($venc) {
  if (!$venc) return '';
  if (count($venc) === 1) {
    $c = $venc[0];
    $tit = mb_strimwidth($c['caratula'], 0, 60, '…');
    if ($c['dias'] < 0) {
      return 'El plazo de "' . $tit . '" venció hace ' . abs($c['dias']) . ' día(s). Verificalo antes de que se acuse la caducidad.';
    }
    if ($c['dias'] === 0) {
      return 'El plazo de "' . $tit . '" vence HOY. Revisá el expediente.';
    }
    return 'El plazo de "' . $tit . '" vence en ' . $c['dias'] . ' día(s) (' . $c['venc'] . ').';
  }
  $vencidas = count(array_filter($venc, function ($v) { return $v['dias'] < 0; }));
  if ($vencidas > 0) {
    return 'Tenés ' . count($venc) . ' causas con plazo de caducidad vencido o por vencer (' . $vencidas . ' ya vencidas). Revisalas en "Caducidad de instancia".';
  }
  return 'Tenés ' . count($venc) . ' causas con plazo de caducidad por vencer. Revisalas en "Caducidad de instancia".';
}
