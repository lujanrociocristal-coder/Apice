-- ============================================================================
--  ÁPICE — DATOS INICIALES (parte 2: Guía Judicial de Catamarca)
--
--  Cargá este archivo DESPUÉS de crear el primer usuario (que crea el estudio
--  nº 1). Precarga los juzgados, mediación, equipo técnico, mesas y asesorías
--  de la Primera Circunscripción de Catamarca, tal como estaban en tu app.
--
--  Si tu primer estudio NO es el id 1 (raro), cambiá el número en la línea
--  de @eid de abajo por el id correcto (lo ves en la tabla "estudios").
--
--  Datos OFICIALES informados por la usuaria; actualizados a 2026-06. Revisá
--  y editá desde la app si algo cambió en el Poder Judicial.
-- ============================================================================

SET @eid := 1;

INSERT INTO guia_judicial (estudio_id, ref, categoria, nombre, rol, integrantes, direccion, tel, email, notas, oficial, actualizado) VALUES
(@eid,'fam1','juzgado','Juzgado de Familia de Primera Instancia y de Primera Nominación','Jueza Dra. Erica Wanda Saccher Maione',
 JSON_ARRAY('Secretaría — Dra. Marina Belén Teme','Secretaría — Dra. María Laura Gramajo Frasinelli','Secretaría a/c Violencia Familiar — Dra. Silvana Prevedello','Secretaría — Dra. Sofía Inés López Abel'),
 'Av. Juan de Almonacid 1439','383-6000472 / 74','familia1@juscatamarca.gob.ar','',1,'2026-06'),

(@eid,'fam2','juzgado','Juzgado de Familia de Primera Instancia y de Segunda Nominación','Jueza Dra. Olga Amigot Solohaga',
 JSON_ARRAY('Secretaría — Dra. Verónica Liliana Figueroa Vicario','Secretaría — Dr. Santiago Sofía','Secretaría a/c Violencia Familiar — Dra. Ana Verónica Rodríguez','Secretaría (en comisión) — Dra. Paula Andrada'),
 'Av. Juan de Almonacid 1439','383-6000472 / 74','familia2@juscatamarca.gob.ar','',1,'2026-06'),

(@eid,'fam3','juzgado','Juzgado de Familia de Primera Instancia y de Tercera Nominación','Juez vacante · a cargo (en comisión) Dra. Celeste del Huerto Varela',
 JSON_ARRAY('Secretaría — Dra. Elsa Carolina González','Secretaría (en comisión) — Dra. Martha Lourdes Musella Velazco','Secretaría a/c Violencia Familiar — Dra. Patricia del Valle Ramos','Secretaría (en comisión) — Dr. Manuel Exequiel Olivera'),
 'Av. Juan de Almonacid 1439','383-6000472 / 74','familia3@juscatamarca.gob.ar','',1,'2026-06'),

(@eid,'fam4','juzgado','Juzgado de Familia de Primera Instancia y de Cuarta Nominación, y de Violencia Familiar y de Género','Jueza Dra. María Elisabet Salas',
 JSON_ARRAY('Secretaría — Dr. Luis Federico Pizarro','Secretaría — Dra. Aldana Barrionuevo Acosta','Secretaría — Dra. María Cecilia Navarro Santa Ana'),
 'Av. Juan de Almonacid 1445','','','',1,'2026-06'),

(@eid,'civ1','juzgado','Juzgado de Primera Instancia en lo Civil, Primera Nominación','Juez Dr. Pablo Fernando Sosa Guzmán',
 JSON_ARRAY('Secretaría — Dr. Emmanuel Arturo Ibáñez','Secretaría — Dra. Romina del Valle Garriga','Secretaría — Dra. Lorena Paola Gutiérrez'),
 'República 436','4437667 al 676','JuzCivil1@juscatamarca.gob.ar','',1,'2026-06'),

(@eid,'civ2','juzgado','Juzgado de Primera Instancia en lo Civil, Segunda Nominación','Juez Dr. Osvaldo Alejandro Romero',
 JSON_ARRAY('Secretaría — Dra. Daniela González Martínez','Secretaría de Actuación — Dra. María Guadalupe Cabrera','Secretaría — Dr. Roque Mauricio Tapia','Secretaría — Dra. Cintia Marina Aranda'),
 'República 436','4437667 al 676','JuzCivil2@juscatamarca.gob.ar','',1,'2026-06'),

(@eid,'civ3','juzgado','Juzgado de Primera Instancia en lo Civil, Tercera Nominación','Juez Dr. José Mauricio Quispe',
 JSON_ARRAY('Secretaría — Dra. María Eugenia Marcolli','Secretaría — Dr. Franco David Vega','Secretaría (en comisión) — Dra. Nadia Agustina Díaz'),
 'República 436','4437667 al 676','juzcivil3@juscatamarca.gob.ar','',1,'2026-06'),

(@eid,'civ4','juzgado','Juzgado de Primera Instancia en lo Civil, Cuarta Nominación','Juez vacante · a cargo (en comisión) Dra. María Gabriela Ruiz',
 JSON_ARRAY('Secretaría — Dra. María Julieta Córdoba','Secretaría (en comisión) — Dra. Patricia Estrada','Secretaría (en comisión) — Dr. Maximiliano Julián Martínez'),
 'República 436','4437667 al 676','Juzcivil4@juscatamarca.gob.ar','',1,'2026-06'),

(@eid,'civ5','juzgado','Juzgado de Primera Instancia en lo Civil, Quinta Nominación','Jueza Dra. María Lucía Cano',
 JSON_ARRAY('Secretaría — Dra. Laura Salomé Callafa','Secretaría — Dra. María Florencia Carrizo','Secretaría — Dra. Cecilia Luisa Rodríguez'),
 'República 436','4437667 al 676','JuzCivil5@juscatamarca.gob.ar','',1,'2026-06'),

(@eid,'medi','mediacion','Departamento de Mediación Judicial','Directora Dra. Carolina del Milagro Martínez Andujar',
 JSON_ARRAY('Mediadora — Dra. María Alejandra Musso','Mediador — Dr. José Luis Auat','Mediadora — Dra. Paola Inés Beltramello','Mediadora — Dra. Ana Verónica Acuña','Mediadora — Dra. María Belén López'),
 'República 446','4437986','MediacionCatam@juscatamarca.gob.ar','',1,'2026-06'),

(@eid,'equipo','equipo','Equipo Técnico Forense — Juzgados de Familia','Cuerpo interdisciplinario (psicología y trabajo social)',
 JSON_ARRAY('Profesional — Lic. María Jimena Barros Díaz','Psic. — Lic. Mónica Liliana Wainstein','Psic. — Lic. Julieta Monasterio Figueroa','Psic. — Lic. María Mercedes Manzi','Psic. — Lic. María Emilia Cáceres','Psic. — Lic. Andrea Alejandra Biancato','Psic. — Lic. Sandra Carina Castagno','Psic. — Lic. María de las Nieves Miranda','Psic. — Lic. Isis Tamara Gerónimo','Psic. — Lic. Eliana del Valle Ovejero Colla','Psic. — Lic. Nadia Desiree Berrondo','Psic. — Lic. María del Pilar Guzmán','Psic. — Lic. Marina Coral Suárez Cecenarro','T. Social — Lic. Elsa María Alurralde','T. Social — Lic. Inés Alcira Olima','T. Social — Lic. Fabiana Inés Acosta','T. Social — Lic. Noelia del Carmen Aguirre','T. Social — Lic. Andrea Gisella Oyhenart','T. Social — Lic. Roxana Elda Núñez','T. Social — Lic. Claudia Josefina Flores','T. Social — Lic. Laura Fernanda Tomassi','T. Social — Lic. Eliana Laura Robles','T. Social — Lic. Noelia Anahí Espeche Pacheco'),
 'Calle Nieva y Castilla 539, esq. Pje. Anessi','','EquipoTecnico@juscatamarca.gob.ar','Prueba clave en causas de familia.',1,'2026-06'),

(@eid,'mesa','mesa','Mesa de Entradas Única','Secretaria Dra. Viviana Guerrero',
 JSON_ARRAY(),'República 436 — Planta Baja','','MesaEntradas@juscatamarca.gob.ar','',1,'2026-06'),

(@eid,'mesafam','mesa','Anexo Mesa de Entradas — Juzgados de Familia','Secretaria Dra. Miriam Lourdes Urioste',
 JSON_ARRAY(),'Av. Juan de Almonacid 1439','','mesafamilia@juscatamarca.gob.ar','',1,'2026-06'),

(@eid,'notif','mesa','Oficina de Notificaciones y Mandatos','Secretaria Dra. Viviana Guerrero',
 JSON_ARRAY(),'República 346','','Notificaciones@juscatamarca.gob.ar','',1,'2026-06'),

(@eid,'audien','mesa','Oficina de Gestión de Audiencias','Coordinador Dr. Sebastián Andrés Lipari',
 JSON_ARRAY(),'Av. Colón 250','','','',1,'2026-06'),

(@eid,'aseso','asesoria','Asesorías de Menores e Incapaces','Tres asesorías',
 JSON_ARRAY('Asesoría 1 — Dra. Daniela Faerman Cano · asesormenor1@juscatamarca.gob.ar','Asesoría 2 — Dra. Carolina Acuña Barrionuevo · asesormenor2@juscatamarca.gob.ar','Asesoría 3 — Dra. Sandra López Gardel · asesormenor3@juscatamarca.gob.ar'),
 'Chacabuco 788','','','',1,'2026-06');

-- Fin de la Guía Judicial precargada.
