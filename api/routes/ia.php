<?php
/* ============================================================================
 *  ESTRATEGIA / IA  (/api/ia/...)   — ETAPA 2 (preparado para conectar)
 *
 *  Estas funciones (Diagnóstico Estratégico, Radar Probatorio, Informe del
 *  Juez, Informe del Demandado, Sala de Guerra, Preparación de Audiencias)
 *  se conectarán en la segunda etapa a un servicio de IA.
 *
 *  Por ahora, este endpoint existe y responde de forma ordenada, pero NO
 *  genera contenido: avisa que la función está preparada y pendiente de
 *  activación. Así el frontend ya puede tener los botones sin romperse.
 * ========================================================================== */

function handle_ia($method, $resto) {
  require_login();
  $funcion = $resto[0] ?? 'general';
  $disponibles = ['diagnostico','radar','informe-juez','informe-demandado','sala-guerra','audiencias','general'];
  if (!in_array($funcion, $disponibles, true)) json_error('Función de IA desconocida.', 404);

  json_ok([
    'funcion'    => $funcion,
    'estado'     => 'preparado',
    'disponible' => false,
    'mensaje'    => 'Esta función inteligente está preparada y se activará en la segunda etapa.',
  ]);
}
