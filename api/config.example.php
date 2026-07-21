<?php
/* ============================================================================
 *  ÁPICE — ARCHIVO DE CONFIGURACIÓN (EJEMPLO)
 *
 *  QUÉ HACER CON ESTE ARCHIVO:
 *    1) Copialo y renombralo a  "config.php"  (sin el ".example").
 *    2) Completá los 4 datos de la base de datos de abajo.
 *    3) Subí SOLO "config.php" al servidor (config.example.php puede quedar,
 *       no molesta, pero el que se usa es config.php).
 *
 *  ¿DE DÓNDE SACO ESTOS DATOS? (Hostinger → hPanel)
 *    Los obtenés cuando creás la base de datos MySQL en el panel.
 *    Está explicado paso a paso en GUIA-HOSTINGER.md, sección
 *    "Crear la base de datos". Anotá los 4 valores ahí y pegalos acá.
 * ========================================================================== */

return [

  /* ---- BASE DE DATOS MySQL ---- */
  // El "host" casi siempre es localhost en Hostinger. Dejalo así salvo que
  // el panel te diga otra cosa.
  'db_host' => 'localhost',

  // Nombre de la base. En Hostinger suele tener un prefijo, por ejemplo:
  //   u123456789_apice
  'db_name' => 'COMPLETAR_nombre_de_la_base',

  // Usuario de la base (también con prefijo), por ejemplo:
  //   u123456789_apice
  'db_user' => 'COMPLETAR_usuario_de_la_base',

  // Contraseña que pusiste al crear el usuario de la base.
  'db_pass' => 'COMPLETAR_contraseña_de_la_base',

  /* ---- SEGURIDAD ---- */
  // Texto secreto largo y al azar (mínimo 32 caracteres). Sirve para firmar
  // sesiones. Inventá uno tecleando letras y números al azar y no lo compartas.
  // Ejemplo (¡cambialo!): 'kf83Hd0aL2pQz9...'
  'app_secret' => 'COMPLETAR_texto_secreto_largo_y_al_azar',

  // Dominio donde va a vivir ÁPICE (sin https://). Sirve para las cookies.
  // Tu dominio es:
  'app_domain' => 'abogadoscatamarca.com',

  // ¿Forzar HTTPS en las cookies? Dejalo en true (tu plan tiene SSL gratis).
  'cookie_secure' => true,

  /* ---- ENVÍO DE CORREO (para "olvidé mi contraseña") ----
   Se necesita una casilla del dominio creada en hPanel → Emails.
   El servidor y el puerto te los muestra Hostinger al crearla.
   Si estos datos quedan vacíos, la recuperación por correo NO funciona. */
'smtp_host' => 'smtp.hostinger.com',
'smtp_port' => 465,
'smtp_user' => 'no-responder@abogadoscatamarca.com',
'smtp_pass' => 'COMPLETAR_contraseña_de_esa_casilla',

/* ---- RESPALDO AUTOMÁTICO DE LA BASE ---- */
// Palabra secreta para poder disparar el respaldo desde el navegador.
// El respaldo automático (cron) NO la necesita: corre por línea de comandos.
// Esto sirve solo para probarlo o hacer una copia manual entrando a:
//     https://abogadoscatamarca.com/api/cron-backup.php?token=LO_QUE_PONGAS_ACA
// Poné un texto largo al azar. Si lo dejás vacío, esa vía queda cerrada
// (el cron sigue funcionando igual).
'backup_token' => '',

/* ---- ETAPA 2 (dejar vacío por ahora) ---- */
  // "Ingresar con Google": se completa más adelante. Dejalo vacío.
  'google_client_id'     => '',
  'google_client_secret' => '',
];
