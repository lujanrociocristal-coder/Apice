-- ============================================================================
--  ÁPICE · Gestión Jurídica Inteligente — Prototipo B
--  ESQUEMA DE BASE DE DATOS (MySQL / MariaDB)
--
--  Qué hace este archivo:
--    Crea TODAS las tablas que ÁPICE necesita para funcionar con usuarios
--    reales y datos en el servidor. Lo vas a cargar UNA sola vez desde
--    phpMyAdmin (ver GUIA-HOSTINGER.md, paso "Cargar la base de datos").
--
--  Importante:
--    - No borra nada tuyo. Solo crea tablas nuevas.
--    - Cada tabla tiene un comentario en español explicando para qué sirve.
--    - "estudio_id" aparece en casi todas las tablas: es lo que mantiene
--      separados los datos de cada estudio jurídico (aislamiento).
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1) ESTUDIOS (las firmas / estudios jurídicos)
--    Cada fila es un estudio. Todo lo demás "pertenece" a un estudio.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS estudios (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre        VARCHAR(160) NOT NULL,                 -- "Estudio Luján & Breppe"
  tipo          ENUM('individual','estudio') NOT NULL DEFAULT 'estudio', -- individual = 1 abogada; estudio = 2 o más
  domicilio     VARCHAR(255) NULL,
  telefono      VARCHAR(60)  NULL,
  email         VARCHAR(160) NULL,
  cuit          VARCHAR(20)  NULL,
  valor_ius     DECIMAL(12,2) NOT NULL DEFAULT 35000,  -- valor del IUS que usa el estudio
  recibo_seq    INT UNSIGNED NOT NULL DEFAULT 1,        -- próximo número de recibo (correlativo POR estudio)
  logo_url      VARCHAR(255) NULL,
  creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Estudios jurídicos. Raíz del aislamiento de datos.';

-- ----------------------------------------------------------------------------
-- 2) USUARIOS (personas que inician sesión)
--    rol = 'profesional' (abogada/o, acceso completo al estudio)
--    rol = 'cliente'     (solo lee SUS causas y SU agenda)
--    La contraseña se guarda HASHEADA (cifrada) con bcrypt. Nunca en texto.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  estudio_id      INT UNSIGNED NOT NULL,
  nombre          VARCHAR(160) NOT NULL,
  email           VARCHAR(190) NOT NULL,
  password_hash   VARCHAR(255) NULL,                    -- NULL si entra solo con Google (etapa 2)
  rol             ENUM('profesional','cliente') NOT NULL DEFAULT 'profesional',
  matricula       VARCHAR(60)  NULL,                     -- "M.P. 2805"
  telefono        VARCHAR(60)  NULL,
  avatar_url      VARCHAR(255) NULL,
  google_id       VARCHAR(120) NULL,                     -- preparado para "Ingresar con Google" (etapa 2)
  es_admin           TINYINT(1) NOT NULL DEFAULT 0,       -- 1 = administradora del estudio (lead de la firma)
  es_superadmin      TINYINT(1) NOT NULL DEFAULT 0,       -- 1 = dueña de la plataforma (decide qué estudios acceden)
  debe_cambiar_clave TINYINT(1) NOT NULL DEFAULT 0,       -- 1 = clave temporal: debe cambiarla al ingresar
  activo          TINYINT(1) NOT NULL DEFAULT 1,
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_acceso   DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (email),
  KEY idx_usuarios_estudio (estudio_id),
  CONSTRAINT fk_usuarios_estudio FOREIGN KEY (estudio_id) REFERENCES estudios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Usuarios que inician sesión. Profesionales y clientes.';

-- ----------------------------------------------------------------------------
-- 3) ACEPTACIÓN DE TÉRMINOS / PRIVACIDAD (Ley 25.326)
--    Guarda quién aceptó qué versión y cuándo (profesional o cliente).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS consentimientos (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id    INT UNSIGNED NOT NULL,
  perfil        ENUM('abogado','cliente') NOT NULL,
  documento     VARCHAR(40) NOT NULL,                   -- 'terminos' | 'privacidad' | 'cookies'
  version       VARCHAR(20) NOT NULL DEFAULT 'v1',
  metodo        VARCHAR(40) NULL,                        -- 'registro' | 'login' | ...
  aceptado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip            VARCHAR(60) NULL,
  PRIMARY KEY (id),
  KEY idx_consent_usuario (usuario_id),
  CONSTRAINT fk_consent_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro de aceptación de términos y privacidad.';

-- ----------------------------------------------------------------------------
-- 4) CLIENTES (las personas/empresas representadas)
--    usuario_id (opcional): si el cliente tiene acceso al portal, se enlaza
--    a su fila en "usuarios" (rol = cliente).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clientes (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  estudio_id    INT UNSIGNED NOT NULL,
  nombre        VARCHAR(200) NOT NULL,
  tipo          ENUM('fisica','juridica') NOT NULL DEFAULT 'fisica',
  dni_cuit      VARCHAR(40)  NULL,
  email         VARCHAR(190) NULL,
  telefono      VARCHAR(60)  NULL,
  domicilio     VARCHAR(255) NULL,
  notas         TEXT NULL,
  usuario_id    INT UNSIGNED NULL,                       -- enlace al usuario-portal del cliente (opcional)
  creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_clientes_estudio (estudio_id),
  KEY idx_clientes_usuario (usuario_id),
  CONSTRAINT fk_clientes_estudio FOREIGN KEY (estudio_id) REFERENCES estudios(id) ON DELETE CASCADE,
  CONSTRAINT fk_clientes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Clientes del estudio. Pueden tener o no acceso al portal.';

-- ----------------------------------------------------------------------------
-- 5) CAUSAS / EXPEDIENTES (el corazón de ÁPICE)
--    owner_id: la dueña de la causa. Se comparte vía causa_colaboradores.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS causas (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  estudio_id      INT UNSIGNED NOT NULL,
  owner_id        INT UNSIGNED NOT NULL,                 -- dueña de la causa
  ref             VARCHAR(80)  NULL,                     -- identificador corto/slug ("acosta")
  estado          ENUM('tramite','preparacion','suspenso','finalizada') NOT NULL DEFAULT 'preparacion',
  procesal        VARCHAR(60)  NULL,                     -- "EN LETRA", "A DESPACHO", ...
  caratula        VARCHAR(400) NOT NULL,
  cliente_id      INT UNSIGNED NULL,                     -- enlace a clientes (si existe)
  cliente_nombre  VARCHAR(200) NULL,                     -- texto libre (compatibilidad con datos actuales)
  expediente      VARCHAR(80)  NULL,
  cuij            VARCHAR(80)  NULL,
  objeto          TEXT NULL,
  fuero           VARCHAR(160) NULL,
  juzgado         VARCHAR(200) NULL,
  juez            VARCHAR(160) NULL,
  secretaria      VARCHAR(160) NULL,
  letrada         VARCHAR(200) NULL,
  posicion        VARCHAR(160) NULL,
  actor_rol       VARCHAR(80)  NULL,
  actor           TEXT NULL,
  demandado_rol   VARCHAR(80)  NULL,
  demandado       TEXT NULL,
  cliente_es      VARCHAR(20)  NULL,                     -- 'activa' | 'pasiva'
  cliente_calidad VARCHAR(255) NULL,
  materias        JSON NULL,                             -- ["Sucesión","Alimentos"]
  registral       JSON NULL,                             -- datos registrales (clave/valor)
  honorarios_ius  DECIMAL(12,2) NOT NULL DEFAULT 0,      -- pactado en IUS
  cad_tipo        VARCHAR(40)  NULL,                     -- tipo para caducidad
  ficha_id        VARCHAR(120) NULL,                     -- id de Google Doc (ficha)
  folder_id       VARCHAR(120) NULL,                     -- id de carpeta Google Drive
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_causas_estudio (estudio_id),
  KEY idx_causas_owner (owner_id),
  KEY idx_causas_cliente (cliente_id),
  KEY idx_causas_estado (estado),
  CONSTRAINT fk_causas_estudio FOREIGN KEY (estudio_id) REFERENCES estudios(id) ON DELETE CASCADE,
  CONSTRAINT fk_causas_owner   FOREIGN KEY (owner_id)   REFERENCES usuarios(id) ON DELETE RESTRICT,
  CONSTRAINT fk_causas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Causas/expedientes. Tienen dueña (owner) y colaboradoras.';

-- ----------------------------------------------------------------------------
-- 6) COLABORADORAS DE UNA CAUSA (modelo dueño + colaborador)
--    Permite compartir una causa con otras profesionales del MISMO estudio.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS causa_colaboradores (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  causa_id    INT UNSIGNED NOT NULL,
  usuario_id  INT UNSIGNED NOT NULL,
  permiso     ENUM('lectura','edicion') NOT NULL DEFAULT 'edicion',
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_colab (causa_id, usuario_id),
  CONSTRAINT fk_colab_causa   FOREIGN KEY (causa_id)   REFERENCES causas(id)   ON DELETE CASCADE,
  CONSTRAINT fk_colab_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Quién más puede ver/editar cada causa.';

-- ----------------------------------------------------------------------------
-- 7) MOVIMIENTOS / BITÁCORA (historia de cada causa)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS movimientos (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  causa_id    INT UNSIGNED NOT NULL,
  fecha_txt   VARCHAR(40) NULL,                          -- como la escribe la abogada ("09/04/2024", "10/2022")
  fecha_iso   DATE NULL,                                 -- normalizada cuando se puede (para ordenar)
  texto       TEXT NOT NULL,
  inicio      TINYINT(1) NOT NULL DEFAULT 0,             -- marca el hito de origen
  nuevo       TINYINT(1) NOT NULL DEFAULT 0,             -- "novedad sin leer"
  orden       INT NOT NULL DEFAULT 0,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mov_causa (causa_id),
  CONSTRAINT fk_mov_causa FOREIGN KEY (causa_id) REFERENCES causas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Bitácora de movimientos de cada causa.';

-- ----------------------------------------------------------------------------
-- 8) DOCUMENTOS (archivos asociados a la causa)
--    visible_cliente: 1 = el cliente lo ve en su portal; 0 = solo interno.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS documentos (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  causa_id        INT UNSIGNED NOT NULL,
  nombre          VARCHAR(255) NOT NULL,
  tipo            VARCHAR(40)  NULL,                      -- inferido ("docx", "pdf", "prueba"...)
  carpeta         VARCHAR(40)  NULL,                      -- carpeta lógica (escritos, prueba, cliente...)
  relevancia      VARCHAR(40)  NULL DEFAULT 'tramite',
  visible_cliente TINYINT(1) NOT NULL DEFAULT 0,
  etiquetas       JSON NULL,
  url             VARCHAR(500) NULL,                      -- enlace (Google Drive u otro)
  fecha_txt       VARCHAR(40)  NULL,
  usuario_nombre  VARCHAR(160) NULL,
  historial       JSON NULL,
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_doc_causa (causa_id),
  CONSTRAINT fk_doc_causa FOREIGN KEY (causa_id) REFERENCES causas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Documentos de cada causa (metadatos y enlaces).';

-- ----------------------------------------------------------------------------
-- 9) TAREAS / PENDIENTES
--    Pueden estar atadas a una causa (causa_id) o ser sueltas del estudio.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tareas (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  estudio_id  INT UNSIGNED NOT NULL,
  causa_id    INT UNSIGNED NULL,
  texto       VARCHAR(500) NOT NULL,
  hecha       TINYINT(1) NOT NULL DEFAULT 0,
  vence       DATE NULL,
  asignada_a  INT UNSIGNED NULL,                          -- usuario responsable (opcional)
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tareas_estudio (estudio_id),
  KEY idx_tareas_causa (causa_id),
  CONSTRAINT fk_tareas_estudio FOREIGN KEY (estudio_id) REFERENCES estudios(id) ON DELETE CASCADE,
  CONSTRAINT fk_tareas_causa   FOREIGN KEY (causa_id)   REFERENCES causas(id)   ON DELETE CASCADE,
  CONSTRAINT fk_tareas_asign   FOREIGN KEY (asignada_a) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pendientes/tareas del estudio o de una causa.';

-- ----------------------------------------------------------------------------
-- 10) AUDIENCIAS y CITAS (mismo cajón, distinto "tipo")
--     tipo = 'juzgado' | 'mediacion' (audiencias)
--     tipo = 'cita'    (cita con cliente, presencial o virtual)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audiencias (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  estudio_id      INT UNSIGNED NOT NULL,
  causa_id        INT UNSIGNED NULL,
  tipo            ENUM('juzgado','mediacion','cita') NOT NULL,
  fecha           DATE NOT NULL,
  hora            VARCHAR(10) NULL,
  detalle         VARCHAR(400) NULL,
  cliente_nombre  VARCHAR(200) NULL,
  cliente_id      INT UNSIGNED NULL,
  materia         VARCHAR(120) NULL,
  cli_asiste      TINYINT(1) NOT NULL DEFAULT 0,          -- el cliente debe asistir (audiencias)
  modalidad       ENUM('presencial','virtual') NULL,      -- para citas
  lugar           VARCHAR(255) NULL,
  link            VARCHAR(500) NULL,                       -- enlace de videollamada (cita virtual)
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_aud_estudio (estudio_id),
  KEY idx_aud_causa (causa_id),
  KEY idx_aud_fecha (fecha),
  CONSTRAINT fk_aud_estudio FOREIGN KEY (estudio_id) REFERENCES estudios(id) ON DELETE CASCADE,
  CONSTRAINT fk_aud_causa   FOREIGN KEY (causa_id)   REFERENCES causas(id)   ON DELETE SET NULL,
  CONSTRAINT fk_aud_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Audiencias de juzgado/mediación y citas con clientes.';

-- ----------------------------------------------------------------------------
-- 11) GASTOS DE HONORARIOS (por causa)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS honorarios_gastos (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  causa_id    INT UNSIGNED NOT NULL,
  concepto    VARCHAR(255) NOT NULL,
  moneda      ENUM('ius','ars') NOT NULL DEFAULT 'ars',
  monto       DECIMAL(12,2) NOT NULL DEFAULT 0,
  pagado      TINYINT(1) NOT NULL DEFAULT 0,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gastos_causa (causa_id),
  CONSTRAINT fk_gastos_causa FOREIGN KEY (causa_id) REFERENCES causas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Gastos asociados a los honorarios de una causa.';

-- ----------------------------------------------------------------------------
-- 12) PAGOS (cobros que hace el estudio)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pagos (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  estudio_id  INT UNSIGNED NOT NULL,
  causa_id    INT UNSIGNED NULL,
  cliente_id  INT UNSIGNED NULL,
  fecha       DATE NOT NULL,
  concepto    VARCHAR(255) NULL,
  moneda      ENUM('ius','ars') NOT NULL DEFAULT 'ars',
  monto       DECIMAL(12,2) NOT NULL DEFAULT 0,
  recibo_id   INT UNSIGNED NULL,                          -- recibo emitido por este pago (si hay)
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pagos_estudio (estudio_id),
  KEY idx_pagos_causa (causa_id),
  CONSTRAINT fk_pagos_estudio FOREIGN KEY (estudio_id) REFERENCES estudios(id) ON DELETE CASCADE,
  CONSTRAINT fk_pagos_causa   FOREIGN KEY (causa_id)   REFERENCES causas(id)   ON DELETE SET NULL,
  CONSTRAINT fk_pagos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pagos/cobros registrados.';

-- ----------------------------------------------------------------------------
-- 13) RECIBOS (numeración CORRELATIVA por estudio)
--     El número sale de estudios.recibo_seq y se incrementa al emitir.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS recibos (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  estudio_id      INT UNSIGNED NOT NULL,
  numero          INT UNSIGNED NOT NULL,                  -- correlativo dentro del estudio
  causa_id        INT UNSIGNED NULL,
  cliente_nombre  VARCHAR(200) NULL,
  fecha           DATE NOT NULL,
  concepto        VARCHAR(400) NULL,
  moneda          ENUM('ius','ars') NOT NULL DEFAULT 'ars',
  monto           DECIMAL(12,2) NOT NULL DEFAULT 0,
  monto_en_letras VARCHAR(400) NULL,
  emitido_por     INT UNSIGNED NULL,                      -- usuario que firma
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_recibo_num (estudio_id, numero),          -- no se repite el número dentro del estudio
  KEY idx_recibos_estudio (estudio_id),
  CONSTRAINT fk_recibos_estudio FOREIGN KEY (estudio_id) REFERENCES estudios(id) ON DELETE CASCADE,
  CONSTRAINT fk_recibos_causa   FOREIGN KEY (causa_id)   REFERENCES causas(id)   ON DELETE SET NULL,
  CONSTRAINT fk_recibos_emisor  FOREIGN KEY (emitido_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Recibos con numeración correlativa por estudio.';

-- ----------------------------------------------------------------------------
-- 14) CONVENIOS DE HONORARIOS (uno por causa)
--     El contenido del acuerdo se guarda como JSON (formulario editable).
--     El texto legal lo revisás vos: la app solo guarda y muestra.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS convenios (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  causa_id    INT UNSIGNED NOT NULL,
  datos       JSON NULL,                                  -- campos del convenio (montos, cuotas, etc.)
  texto       MEDIUMTEXT NULL,                            -- borrador final editable
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_convenio_causa (causa_id),
  CONSTRAINT fk_convenio_causa FOREIGN KEY (causa_id) REFERENCES causas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Convenio de honorarios por causa (borrador editable).';

-- ----------------------------------------------------------------------------
-- 15) GUÍA JUDICIAL / DIRECTORIO (organismos reutilizables)
--     categoria: juzgado | mediacion | equipo | mesa | asesoria | colega
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS guia_judicial (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  estudio_id  INT UNSIGNED NOT NULL,
  ref         VARCHAR(60) NULL,                           -- id corto original ("civ3")
  categoria   ENUM('juzgado','mediacion','equipo','mesa','asesoria','colega') NOT NULL,
  nombre      VARCHAR(300) NOT NULL,
  rol         VARCHAR(200) NULL,                           -- "Juez Dr. ..."
  integrantes JSON NULL,                                   -- secretarías / personas
  direccion   VARCHAR(255) NULL,
  tel         VARCHAR(80)  NULL,
  email       VARCHAR(190) NULL,
  notas       TEXT NULL,
  oficial     TINYINT(1) NOT NULL DEFAULT 0,               -- dato "oficial" precargado
  actualizado VARCHAR(20) NULL,                            -- "2026-06"
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_guia_estudio (estudio_id),
  KEY idx_guia_cat (categoria),
  CONSTRAINT fk_guia_estudio FOREIGN KEY (estudio_id) REFERENCES estudios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Guía Judicial: juzgados, mediación, equipo técnico, etc.';

-- ----------------------------------------------------------------------------
-- 16) FERIADOS (para calculadora de plazos y calendario)
--     anual = 1: se repite todos los años (se compara mes/día).
--     tipo: nacional | provincial
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS feriados (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  estudio_id  INT UNSIGNED NULL,                          -- NULL = feriado global (lo ven todos)
  fecha       VARCHAR(10) NOT NULL,                        -- "AAAA-MM-DD"
  anual       TINYINT(1) NOT NULL DEFAULT 1,
  nombre      VARCHAR(160) NOT NULL,
  tipo        ENUM('nacional','provincial') NOT NULL DEFAULT 'nacional',
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_feriados_estudio (estudio_id),
  CONSTRAINT fk_feriados_estudio FOREIGN KEY (estudio_id) REFERENCES estudios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Feriados nacionales/provinciales para plazos y calendario.';

-- ----------------------------------------------------------------------------
-- 17) ALERTAS (avisos/recordatorios atados a una causa)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alertas (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  causa_id    INT UNSIGNED NOT NULL,
  tipo        VARCHAR(40) NULL,                            -- 'eleccion' | 'confirmar' | ...
  texto       VARCHAR(400) NOT NULL,
  campo       VARCHAR(80) NULL,
  opciones    JSON NULL,
  resuelta    TINYINT(1) NOT NULL DEFAULT 0,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_alertas_causa (causa_id),
  CONSTRAINT fk_alertas_causa FOREIGN KEY (causa_id) REFERENCES causas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Alertas/recordatorios de cada causa.';

-- ----------------------------------------------------------------------------
-- 18) SESIONES (opcional, respaldo de login)
--     PHP maneja sesiones por cookie; esta tabla es por si querés
--     invalidar sesiones o auditar accesos más adelante.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sesiones (
  id          CHAR(64) NOT NULL,                           -- token de sesión
  usuario_id  INT UNSIGNED NOT NULL,
  creada_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_en   DATETIME NULL,
  ip          VARCHAR(60) NULL,
  user_agent  VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_sesiones_usuario (usuario_id),
  CONSTRAINT fk_sesiones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Respaldo opcional de sesiones para auditoría.';

-- ----------------------------------------------------------------------------
-- 19) ESTADO DE LA APP (almacenamiento por estudio para el frontend)
--     La app guarda aquí sus "bloques" de datos (causas, agenda, clientes,
--     guía, configuración) como JSON, COMPARTIDOS por todo el estudio.
--     Cada fila = un bloque (clave) de un estudio.
--     Esto es lo que usa la conexión "rápida y segura": login real + datos
--     en el servidor, reutilizando tu app casi sin cambios. Las tablas
--     relacionales de arriba quedan listas para migrar por etapas.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS estado_app (
  estudio_id   INT UNSIGNED NOT NULL,
  clave        VARCHAR(80) NOT NULL,            -- ej: "causas", "agenda", "clientes"
  valor        LONGTEXT NULL,                   -- el contenido en JSON
  actualizado  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (estudio_id, clave),
  CONSTRAINT fk_estado_estudio FOREIGN KEY (estudio_id) REFERENCES estudios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Estado de la app por estudio (datos compartidos del frontend).';

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
--  FIN DEL ESQUEMA
--  Próximo paso: cargar datos iniciales con seed.sql (estudio + feriados +
--  guía judicial de ejemplo). El PRIMER USUARIO (Breppe) lo crearás vos
--  con la página segura crear-usuario.php (ver GUIA-HOSTINGER.md).
-- ============================================================================
