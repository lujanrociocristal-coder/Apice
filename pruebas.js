/*  pruebas.js  —  Verificacion automatica de APICE
 *
 *  PARA QUE SIRVE: revisar de una sola vez que no se haya roto nada, ANTES
 *  de publicar. Antes esto se hacia a ojo y varias fallas se colaron.
 *
 *  COMO SE USA:
 *      node pruebas.js            (revisa los archivos del proyecto)
 *      node pruebas.js --vivo     (ademas revisa el sitio publicado)
 *
 *  Si algo falla, lo muestra en rojo y termina con error.
 */
const fs = require('fs');
const path = require('path');
const DIR = __dirname;

let ok = 0, fallas = [];
const chequeo = (nombre, condicion, detalle) => {
  if (condicion) { ok++; console.log('  OK   ' + nombre); }
  else { fallas.push(nombre + (detalle ? ' -> ' + detalle : '')); console.log('  FALLA ' + nombre + (detalle ? ' -> ' + detalle : '')); }
};
const leer = f => fs.existsSync(path.join(DIR, f)) ? fs.readFileSync(path.join(DIR, f), 'utf8') : null;

console.log('\n=== 1) Archivos del frontend ===');
['index.html', 'styles.css', 'app.js', 'anim.js', 'api.js'].forEach(f => {
  const t = leer(f);
  chequeo('existe ' + f, t !== null);
  if (t !== null) chequeo(f + ' no esta vacio', t.length > 100, t.length + ' bytes');
});

console.log('\n=== 2) Sintaxis del JavaScript ===');
['app.js', 'anim.js', 'api.js'].forEach(f => {
  const t = leer(f);
  if (t === null) return;
  try { new Function(t); chequeo('sintaxis ' + f, true); }
  catch (e) { chequeo('sintaxis ' + f, false, e.message); }
});

console.log('\n=== 3) index.html bien enlazado y versionado ===');
const html = leer('index.html') || '';
['styles.css', 'app.js', 'anim.js', 'api.js'].forEach(f => {
  chequeo('index.html enlaza ' + f, html.indexOf(f) >= 0);
});
['styles.css', 'app.js', 'anim.js'].forEach(f => {
  const re = new RegExp(f.replace('.', '\\.') + '\\?v=(\\d{12})');
  chequeo(f + ' tiene version de cache', re.test(html), 'correr: node versionar.js');
});
chequeo('api.js se carga ANTES que app.js',
  html.indexOf('api.js') >= 0 && html.indexOf('api.js') < html.indexOf('app.js'));
chequeo('index.html quedo chico (esta dividido)', html.length < 20000, Math.round(html.length / 1024) + ' KB');

console.log('\n=== 4) Funciones clave presentes ===');
const app = leer('app.js') || '';
[
  ['guardado con reintento', 'function syncFallo'],
  ['proteccion de sesion vencida', 'Tu sesión venció'],
  ['buscador de juzgados', 'function dirPickFilter'],
  ['reparacion de categorias de la Guia', 'function dirMigrateCats'],
  ['fecha en documentos', 'id="docFecha"'],
  ['documentos impactan en bitacora', 'function reconciliarMovsDocs'],
  ['exportacion completa', 'documentosServidor'],
  ['aviso de version nueva', 'function chequearVersionNueva'],
].forEach(([nombre, marca]) => chequeo(nombre, app.indexOf(marca) >= 0));

/* === Jurisdiccion (v47): proteger a Catamarca === */
chequeo('existe el preset de jurisdiccion', /const JUR_PRESETS\s*=/.test(app));
chequeo('jurisdiccion por defecto = catamarca', /config\.jurisdiccion\s*=\s*'catamarca'/.test(app));
chequeo('unidad de honorarios por defecto = IUS', /config\.unidadHon\s*=\s*'IUS'/.test(app));
chequeo('jurMod enciende ante la duda (protege Catamarca)', /p\[nombre\]\s*!==\s*false/.test(app));
/* El preset catamarca NO puede tener ningun modulo apagado. */
const mCat = app.match(/catamarca\s*:\s*\{([^}]*)\}/);
chequeo('preset catamarca no apaga ningun modulo', !!mCat && mCat[1].indexOf('false') < 0);
const mGen = app.match(/generica\s*:\s*\{([^}]*)\}/);
chequeo('preset generica apaga los modulos locales', !!mGen && mGen[1].indexOf('false') >= 0);
chequeo('el menu filtra por jurisdiccion', /SBNAV\.filter\(it=>!it\.mod\|\|jurMod\(it\.mod\)\)/.test(app));
chequeo('navTo protege secciones apagadas', /modDe\[n\]&&!jurMod\(modDe\[n\]\)/.test(app));
chequeo('materias arrancan vacias en generico', /jurMod\('materiasSembradas'\)\?MATERIAS_BASE:\[\]/.test(app));
chequeo('Guia no se siembra en generico', /jurMod\('guiaSembrada'\)\)?\s*(?:\{)?try?\{?seedDir|else if\(jurMod\('guiaSembrada'\)\)seedDir/.test(app));
chequeo('jurisdiccion se carga antes de sembrar', app.indexOf("await window.APICE.get('/config')")>=0);

console.log('\n=== 5) Backend PHP ===');
const phpDir = path.join(DIR, 'api');
const phps = [];
(function walk(d) {
  if (!fs.existsSync(d)) return;
  for (const f of fs.readdirSync(d)) {
    const p = path.join(d, f);
    if (fs.statSync(p).isDirectory()) walk(p);
    else if (f.endsWith('.php')) phps.push(p);
  }
})(phpDir);
chequeo('hay archivos PHP', phps.length > 0, phps.length + ' archivos');
/* Cuenta las llaves REALES del codigo, recorriendo caracter por caracter.
   Hace falta ser asi de prolijo: reemplazar con expresiones regulares daba
   falsas alarmas (por ejemplo, un "//" dentro de un texto se tomaba como
   comentario, o un \d{1,2} de una expresion regular contaba como llave). */
function phpProfundidad(t) {
  let prof = 0, i = 0;
  const n = t.length;
  while (i < n) {
    const c = t[i], d = t[i + 1];
    if (c === '/' && d === '*') { const f = t.indexOf('*/', i + 2); i = (f < 0 ? n : f + 2); continue; }
    if ((c === '/' && d === '/') || c === '#') { const f = t.indexOf('\n', i); i = (f < 0 ? n : f + 1); continue; }
    if (c === "'" || c === '"') {
      const cierre = c; i++;
      while (i < n) { if (t[i] === '\\') { i += 2; continue; } if (t[i] === cierre) { i++; break; } i++; }
      continue;
    }
    if (c === '{') prof++;
    else if (c === '}') prof--;
    i++;
  }
  return prof;
}
phps.forEach(p => {
  const rel = path.relative(DIR, p).replace(/\\/g, '/');
  const prof = phpProfundidad(fs.readFileSync(p, 'utf8'));
  chequeo('llaves balanceadas ' + rel, prof === 0, prof !== 0 ? ('quedan ' + prof + ' sin cerrar') : '');
});

console.log('\n=== 6) Seguridad: no deben existir estos archivos ===');
['api/crear-admin.php', 'api/crear-usuario.php', 'api/diag.php',
 'api/analisis-migracion.php', 'api/migrar.php', 'api/check-file.php',
 'api/db-check.php', 'api/router-test.php', 'api/test-estado.php'].forEach(f => {
  chequeo('eliminado ' + f, !fs.existsSync(path.join(DIR, f)), 'es un riesgo de seguridad');
});
chequeo('config.php NO esta en el repo', !fs.existsSync(path.join(DIR, 'api/config.php')), 'tiene la clave de la base');
const crons = ['api/cron-backup.php', 'api/cron-push.php'];
crons.forEach(f => {
  const t = leer(f);
  if (t === null) { chequeo('existe ' + f, false); return; }
  chequeo(f + ' protegido contra ejecucion por internet', /php_sapi_name\(\)\s*[!=]==\s*'cli'/.test(t));
});
const auth = leer('api/routes/auth.php') || '';
chequeo('login con limite de intentos', auth.indexOf('intentos_espera') >= 0);
const arch = leer('api/routes/archivos.php') || '';
chequeo('subidas validadas por contenido', arch.indexOf('subidas_validar') >= 0);
const est = leer('api/routes/estado.php') || '';
chequeo('proteccion contra borrado masivo', est.indexOf('LIMITE_BORRADO') >= 0);
chequeo('estado.php bloquea a los clientes', est.indexOf("rol'] === 'cliente'") >= 0);

/* ---- Chequeos contra el sitio publicado (opcional) ---- */
async function enVivo() {
  console.log('\n=== 7) Sitio publicado ===');
  const base = 'https://abogadoscatamarca.com';
  const pedir = async (ruta, metodo) => {
    try { const r = await fetch(base + ruta, { method: metodo || 'GET' }); return r.status; }
    catch (e) { return 0; }
  };
  chequeo('la app responde', (await pedir('/index.html')) === 200);
  chequeo('app.js accesible', (await pedir('/app.js')) === 200);
  chequeo('styles.css accesible', (await pedir('/styles.css')) === 200);
  for (const f of ['crear-admin.php', 'crear-usuario.php', 'diag.php', 'analisis-migracion.php', 'migrar.php']) {
    chequeo('bloqueado /api/' + f, (await pedir('/api/' + f, 'HEAD')) === 404);
  }
  chequeo('cron-backup no ejecutable por internet', (await pedir('/api/cron-backup.php', 'HEAD')) === 404);
  chequeo('cron-push no ejecutable por internet', (await pedir('/api/cron-push.php', 'HEAD')) === 404);
  chequeo('config.php no descargable', [403, 404].indexOf(await pedir('/api/config.php', 'HEAD')) >= 0);
}

(async () => {
  if (process.argv.indexOf('--vivo') >= 0) { try { await enVivo(); } catch (e) { console.log('  (no se pudo chequear en vivo: ' + e.message + ')'); } }
  console.log('\n============================================');
  console.log('  Pruebas OK: ' + ok + '   |   Fallas: ' + fallas.length);
  if (fallas.length) {
    console.log('\n  REVISAR:');
    fallas.forEach(f => console.log('   - ' + f));
    console.log('============================================\n');
    process.exit(1);
  }
  console.log('  Todo en orden.');
  console.log('============================================\n');
})();
