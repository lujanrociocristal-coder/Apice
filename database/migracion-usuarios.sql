-- ============================================================================
--  ÁPICE — Migración: gestión de usuarios y claves
--
--  Usá este archivo SOLO si tu base ya estaba creada ANTES de esta función y
--  preferís actualizarla a mano por phpMyAdmin (si usás el instalador
--  install.php, NO hace falta: él agrega estas columnas solo).
--
--  Agrega dos columnas a la tabla "usuarios":
--    es_admin           -> 1 si la persona es la administradora del estudio
--    debe_cambiar_clave -> 1 si tiene clave temporal y debe cambiarla al entrar
-- ============================================================================

ALTER TABLE usuarios
  ADD COLUMN es_admin TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE usuarios
  ADD COLUMN debe_cambiar_clave TINYINT(1) NOT NULL DEFAULT 0;

-- Marcar como administradora a la primera usuaria de cada estudio
-- (la que lo creó). Ajustá si querés otra.
UPDATE usuarios u
JOIN (SELECT estudio_id, MIN(id) AS primer_id FROM usuarios GROUP BY estudio_id) p
  ON u.id = p.primer_id
SET u.es_admin = 1;
