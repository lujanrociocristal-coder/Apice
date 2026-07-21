/*  versionar.js  —  Evita que los usuarios vean versiones viejas.
 *
 *  Problema que resuelve: index.html pide styles.css / app.js / anim.js con
 *  "?v=algo". Si ese numero no cambia, el navegador sigue usando la copia
 *  vieja que tiene guardada, aunque el servidor ya tenga la nueva.
 *
 *  Este script pone una version nueva (fecha + hora) en cada archivo.
 *  Hay que ejecutarlo ANTES de publicar, cada vez que se toque el CSS o el JS:
 *      node versionar.js
 */
const fs = require('fs');
const path = require('path');

const DIR = __dirname;
const INDEX = path.join(DIR, 'index.html');
const ARCHIVOS = ['styles.css', 'app.js', 'anim.js', 'api.js'];

const d = new Date();
const p = n => String(n).padStart(2, '0');
const VERSION = `${d.getFullYear()}${p(d.getMonth() + 1)}${p(d.getDate())}${p(d.getHours())}${p(d.getMinutes())}`;

let html = fs.readFileSync(INDEX, 'utf8');
let cambios = 0;

ARCHIVOS.forEach(f => {
  if (!fs.existsSync(path.join(DIR, f))) return;
  const re = new RegExp('(' + f.replace('.', '\\.') + ')(\\?v=[^"\'\\s>]*)?', 'g');
  html = html.replace(re, (m, nombre) => { cambios++; return nombre + '?v=' + VERSION; });
});

fs.writeFileSync(INDEX, html, 'utf8');
console.log('Version aplicada: ' + VERSION + '  (' + cambios + ' referencias actualizadas)');
ARCHIVOS.forEach(f => {
  if (fs.existsSync(path.join(DIR, f))) {
    console.log('  - ' + f + ': ' + Math.round(fs.statSync(path.join(DIR, f)).size / 1024) + ' KB');
  }
});
