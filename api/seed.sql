-- ============================================================================
--  ÁPICE — DATOS INICIALES (parte 1: feriados globales)
--
--  Cargá este archivo DESPUÉS de schema.sql y ANTES de crear el primer usuario.
--  Inserta los feriados nacionales y provinciales (Catamarca) como "globales"
--  (estudio_id = NULL): los ve cualquier estudio y la calculadora de plazos.
--
--  La fecha lleva el año 2024 solo como referencia; los feriados marcados como
--  "anual = 1" se repiten todos los años (se comparan por mes y día).
-- ============================================================================

INSERT INTO feriados (estudio_id, fecha, anual, nombre, tipo) VALUES
 (NULL, '2024-01-01', 1, 'Año Nuevo',                                   'nacional'),
 (NULL, '2024-03-24', 1, 'Día de la Memoria',                           'nacional'),
 (NULL, '2024-04-02', 1, 'Día del Veterano y Caídos en Malvinas',       'nacional'),
 (NULL, '2024-05-01', 1, 'Día del Trabajador',                          'nacional'),
 (NULL, '2024-05-11', 1, 'Natalicio de Fray Mamerto Esquiú',            'provincial'),
 (NULL, '2024-05-25', 1, 'Revolución de Mayo',                          'nacional'),
 (NULL, '2024-06-20', 1, 'Paso a la Inmortalidad del Gral. Belgrano',   'nacional'),
 (NULL, '2024-07-09', 1, 'Día de la Independencia',                     'nacional'),
 (NULL, '2024-08-25', 1, 'Autonomía de Catamarca',                      'provincial'),
 (NULL, '2024-09-07', 1, 'Día del Milagro',                             'provincial'),
 (NULL, '2024-12-08', 1, 'Inmaculada Concepción',                       'nacional'),
 (NULL, '2024-12-25', 1, 'Navidad',                                     'nacional');

-- Fin. Próximo: crear el primer usuario (crea el estudio nº 1) y luego,
-- si querés, cargar seed-guia-catamarca.sql para precargar los juzgados.
