<?php
/* ============================================================================
 *  ASISTENTE / IA  (/api/ia/...)
 *
 *  /api/ia/asistente  (POST)  -> Chat de AYUDA de uso de la app (CIMA).
 *     Responde SOLO sobre cómo usar ÁPICE, en español simple. No da
 *     asesoramiento legal. Usa la API de Anthropic (Claude Haiku).
 *
 *  La clave de la API se lee de config.php (cfg()['anthropic_key']), que vive
 *  FUERA de public_html y NUNCA se publica. Límite: 20 preguntas por día por
 *  usuario profesional. Las demás funciones quedan "preparado" como antes.
 * ========================================================================== */

/* Manual de uso de ÁPICE que el asistente usa como conocimiento. */
function ia_manual() {
  return <<<TXT
Sos "Cima", el asistente de AYUDA de ÁPICE (Gestión Jurídica Inteligente), un sistema web para estudios jurídicos argentinos. Respondés ÚNICAMENTE sobre CÓMO USAR la app, en español rioplatense, claro y breve (2 a 6 oraciones o pasos numerados cortos). No dabas asesoramiento legal ni opinás sobre casos: si te preguntan algo legal, aclarás amablemente que solo ayudás con el uso de la app. Si no sabés algo puntual de la app, lo decís y sugerís revisar el menú o escribir al soporte. Nunca inventás funciones que no estén acá.

CÓMO SE USA ÁPICE:

MI DÍA: es la pantalla de inicio. Muestra lo de hoy: audiencias y citas, tareas/pendientes (de hoy, atrasadas, próximas y sin fecha) y vencimientos de plazos. Arriba hay botones: Buscar, + Nueva causa, + Audiencia, + Tarea y ⟳ Actualizar (trae los últimos cambios; útil en el celular). Una tarea se marca hecha con el tilde ✓ o se elimina con la ×.

EXPEDIENTES (causas): lista de causas por estado (en trámite, preparación, suspenso, finalizada). Con "+ Nueva causa" se carga una: carátula, materia (se puede escribir una materia nueva con "➕ Otra materia"), cliente, y demás datos. Dentro de una causa hay pestañas: Datos, Avance progresivo (movimientos), Documentación, Pendientes y Honorarios. Botones de la causa: Editar datos, Cambiar estado, Vista de cliente, Acceso del cliente, Compartir con externo y Eliminar (estos dos últimos solo en causas propias).

MOVIMIENTOS (Avance progresivo): se escribe el movimiento y su fecha. La bitácora se ordena sola por fecha, el más reciente arriba.

DOCUMENTOS: con "+ Agregar documento" se sube PDF, Word o imágenes (hasta 20 MB) o se saca una foto; se elige la carpeta (Prueba, Escritos, Actuaciones, Resoluciones, Sentencias, Cliente) y si el cliente puede verlo. Cada documento tiene acciones: cambiar visibilidad, Fecha, Carpeta, Renombrar y Eliminar (en el celular están detrás del botón ⋯).

PENDIENTES / TAREAS: se escriben pendientes con fecha opcional. Aparecen en Mi día. Se tildan al terminar o se borran.

AGENDA (Audiencias): se cargan audiencias en juzgado, mediaciones y citas con clientes. La hora se elige con un selector (no hace falta escribir los dos puntos). Si la cita es presencial, trae por defecto el domicilio del estudio y se puede editar. A una cita se le puede elegir la causa para que también la vea el colega con quien esté compartida. Las audiencias/citas aparecen en el Calendario y en Mi día.

COMPARTIR UNA CAUSA CON OTRO ABOGADO: desde la causa, "Compartir con externo", con el email del colega (que debe tener cuenta activa) y permiso de lectura o edición. Lo que carga cada uno (movimientos, documentos, pendientes, agenda) se sincroniza en los dos lados; para traer lo último se toca ⟳ Actualizar. Si uno borra un ítem, se borra para ambos. La causa compartida no se puede eliminar ni volver a compartir por quien la recibió.

AVISOS: reúne lo que otro colega o un cliente modificó. Arriba, "Novedades de colegas": cada movimiento, documento, pendiente o audiencia que cargó un colega en una causa compartida, con su color; se toca y lleva directo, y se limpia al verlo. Más abajo, comprimidos: pagos informados por clientes, clientes que ingresaron por primera vez, y causas que otro colega te compartió.

HONORARIOS: calculadora según la Ley 5724 de Catamarca. Modo "Materia (monto fijo)" muestra el mínimo en JUS y el artículo de la ley; modo "Cálculo económico" calcula por monto. Hay valor del IUS configurable, recibos numerados y libro de recibos. En Reportes está la morosidad (quién debe).

ACCESO DEL CLIENTE (portal): desde la causa, "Acceso del cliente", se le da acceso al cliente por email con una clave temporal. El cliente ve solo lo suyo, en lenguaje simple, y solo los documentos marcados como visibles. Si un cliente tiene varias causas, se usa el mismo usuario (no se genera clave nueva) y ve todas sus causas al entrar.

REPORTES: estado de las causas (críticas, seguimiento, controladas), clientes, morosidad y exportaciones. BUSCADOR: arriba en Mi día, busca causas o clientes por palabra. GUÍA JUDICIAL: directorio de juzgados. CONFIGURACIÓN: perfil del estudio, valor del IUS, profesionales, y (solo la administradora) usuarios, claves y respaldo.

CELULAR: el menú se abre con ☰; los secundarios están en "Más herramientas". Si no ves un cambio reciente, recargá la app una vez. El botón ⟳ Actualizar en Mi día trae lo último sin cerrar sesión.
TXT;
}

function ia_limite_diario() { return 20; }

function ia_asistente() {
  $u = require_profesional();
  $uid = (int)$u['id'];

  // --- Cupo diario por usuario (tabla ia_uso; se crea sola) ---
  try {
    db()->exec("CREATE TABLE IF NOT EXISTS ia_uso (usuario_id INT NOT NULL, dia DATE NOT NULL, n INT NOT NULL DEFAULT 0, PRIMARY KEY (usuario_id, dia))");
  } catch (Throwable $e) { /* si falla, seguimos sin cupo estricto */ }

  $hoy = date('Y-m-d');
  $usados = 0;
  try {
    $st = db()->prepare("SELECT n FROM ia_uso WHERE usuario_id=? AND dia=?");
    $st->execute([$uid, $hoy]);
    $usados = (int)($st->fetchColumn() ?: 0);
  } catch (Throwable $e) {}
  $limite = ia_limite_diario();
  if ($usados >= $limite) {
    json_error('Llegaste al límite de '.$limite.' preguntas por hoy. Seguimos mañana. 😊', 429);
  }

  // --- Entrada ---
  $pregunta = trim((string)field('pregunta'));
  if ($pregunta === '') json_error('Escribí una pregunta.');
  if (mb_strlen($pregunta) > 1000) $pregunta = mb_substr($pregunta, 0, 1000);

  // Historial breve opcional (para dar contexto). Máximo 6 mensajes.
  $hist = field('historial');
  if (is_string($hist)) $hist = json_decode($hist, true);
  $mensajes = [];
  if (is_array($hist)) {
    foreach (array_slice($hist, -6) as $m) {
      $rol = (($m['rol'] ?? '') === 'assistant') ? 'assistant' : 'user';
      $txt = trim((string)($m['texto'] ?? ''));
      if ($txt !== '') $mensajes[] = ['role' => $rol, 'content' => mb_substr($txt, 0, 1500)];
    }
  }
  $mensajes[] = ['role' => 'user', 'content' => $pregunta];

  // --- Clave y modelo desde config ---
  $cfg = cfg();
  $key = $cfg['anthropic_key'] ?? ($cfg['ia_key'] ?? '');
  if (!$key) {
    json_error('El asistente todavía no está configurado. Falta cargar la clave de la IA en el servidor.', 503);
  }
  $modelo = $cfg['ia_model'] ?? 'claude-haiku-4-5';

  // --- Llamada a la API de Anthropic ---
  $payload = json_encode([
    'model'      => $modelo,
    'max_tokens' => 700,
    'system'     => ia_manual(),
    'messages'   => $mensajes,
  ], JSON_UNESCAPED_UNICODE);

  $ch = curl_init('https://api.anthropic.com/v1/messages');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 40,
    CURLOPT_HTTPHEADER     => [
      'x-api-key: ' . $key,
      'anthropic-version: 2023-06-01',
      'content-type: application/json',
    ],
  ]);
  $resp = curl_exec($ch);
  $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $cerr = curl_error($ch);
  curl_close($ch);

  if ($resp === false) {
    json_error('No pude conectarme al asistente. Probá de nuevo en un momento.', 502);
  }
  $data = json_decode($resp, true);
  if ($http !== 200 || !is_array($data)) {
    $msg = is_array($data) && isset($data['error']['message']) ? $data['error']['message'] : ('HTTP ' . $http);
    error_log('[APICE-IA] ' . $msg . ' | ' . substr($resp, 0, 300));
    json_error('El asistente no pudo responder ahora. Intentá más tarde.', 502);
  }

  // Extraer el texto de la respuesta.
  $texto = '';
  if (isset($data['content']) && is_array($data['content'])) {
    foreach ($data['content'] as $b) {
      if (($b['type'] ?? '') === 'text') $texto .= $b['text'];
    }
  }
  $texto = trim($texto);
  if ($texto === '') $texto = 'No tengo una respuesta para eso. Probá reformular la pregunta sobre cómo usar la app.';

  // --- Sumar 1 al cupo del día ---
  try {
    db()->prepare("INSERT INTO ia_uso (usuario_id, dia, n) VALUES (?,?,1)
                   ON DUPLICATE KEY UPDATE n = n + 1")->execute([$uid, $hoy]);
    $usados++;
  } catch (Throwable $e) {}

  json_ok([
    'respuesta'  => $texto,
    'restantes'  => max(0, $limite - $usados),
  ]);
}

function handle_ia($method, $resto) {
  $funcion = $resto[0] ?? 'general';

  // Chat de ayuda (el único activo por ahora).
  if ($funcion === 'asistente') {
    if ($method !== 'POST') json_error('Método no permitido.', 405);
    return ia_asistente();
  }

  // Resto de funciones: preparadas, pendientes de activación (como antes).
  require_login();
  $disponibles = ['diagnostico','radar','informe-juez','informe-demandado','sala-guerra','audiencias','general'];
  if (!in_array($funcion, $disponibles, true)) json_error('Función de IA desconocida.', 404);
  json_ok([
    'funcion'    => $funcion,
    'estado'     => 'preparado',
    'disponible' => false,
    'mensaje'    => 'Esta función inteligente está preparada y se activará en la segunda etapa.',
  ]);
}
