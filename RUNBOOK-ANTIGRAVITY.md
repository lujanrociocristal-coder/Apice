# RUNBOOK para Antigravity — Publicar ÁPICE

> **Para Ro:** pegale TODO este archivo a Antigravity y pedile: *"Ejecutá este
> runbook paso a paso. Detenete y avisame si algún paso falla."* Cuando termine,
> volvé conmigo (Claude) y decime "ya lo ejecutó" para que verifique.

---

## CONTEXTO (qué es esto)

- Proyecto: **ÁPICE**, app web de gestión jurídica (frontend HTML + backend PHP + MySQL).
- Repositorio GitHub: **https://github.com/lujanrociocristal-coder/Apice** (rama `main`).
- Carpeta local con la versión MÁS NUEVA (la fuente de verdad):
  **`C:\Users\Rocio\Documents\Apice\apice-repo`**
- El repo en GitHub tiene una versión anterior. Esta carpeta local tiene esa
  versión **más** muchos cambios nuevos que hay que subir.
- Despliegue: al hacer push a `main`, un GitHub Action sube los archivos por FTP
  a Hostinger (`public_html`). Eso ya está configurado en `.github/workflows/deploy.yml`.

### Reglas que NO se pueden romper
1. **Nunca** commitear `api/config.php` (lleva la clave de la base). Ya está en `.gitignore`.
2. Algunos archivos de texto pueden tener **bytes NUL ()** por un error previo.
   Hay que **eliminarlos** antes de commitear, o rompen la app.
3. No borrar ni modificar la lógica; solo publicar lo que ya está en la carpeta local.

---

## OBJETIVO
Subir (commit + push) todo el contenido de la carpeta local a la rama `main`,
limpio y validado, y además generar los íconos PNG de la app.

---

## PASOS

### Paso 1 — Clonar el repo en una carpeta temporal
```bash
git clone https://github.com/lujanrociocristal-coder/Apice.git C:/temp/apice-clone
```
(Usá la cuenta/token de GitHub ya configurada en este entorno.)

### Paso 2 — Copiar la versión nueva sobre el clon (sin tocar la carpeta .git)
En PowerShell:
```powershell
robocopy "C:\Users\Rocio\Documents\Apice\apice-repo" "C:\temp\apice-clone" /E /XD .git
```
Esto deja en `C:\temp\apice-clone` todos los archivos nuevos, conservando el
historial git del clon.

### Paso 3 — Eliminar bytes NUL de todos los archivos de texto
Creá y corré este script Node (`limpiar-nul.js`) dentro de `C:\temp\apice-clone`:
```js
const fs=require('fs'),path=require('path');
const exts=['.html','.js','.php','.css','.json','.md','.sql','.yml','.yaml','.svg','.txt','.htaccess'];
function walk(d){for(const f of fs.readdirSync(d)){const p=path.join(d,f);
  if(f==='.git')continue;
  const s=fs.statSync(p);
  if(s.isDirectory())walk(p);
  else if(exts.includes(path.extname(f))||f==='.htaccess'){
    let b=fs.readFileSync(p);
    if(b.includes(0)){fs.writeFileSync(p,Buffer.from(b.filter(x=>x!==0)));console.log('NUL limpiado:',p);}
  }}}
walk(process.cwd());
console.log('Listo.');
```
```bash
cd C:/temp/apice-clone && node limpiar-nul.js && del limpiar-nul.js
```

### Paso 4 — Validar sintaxis
```bash
node --check api.js
node --check sw.js
```
Ambos deben pasar sin error. Para el archivo grande `index.html`, extraé el
contenido entre `<script>` y `</script>` y validalo con `new vm.Script(...)`;
si querés, omití este sub-chequeo, pero `api.js` y `sw.js` SÍ deben validar.
Si `api.js` falla, REVISAR antes de seguir (probablemente quedó un byte NUL o un
recorte; reportar el número de línea).

### Paso 5 — Generar los íconos PNG desde icon.svg (para iPhone)
```bash
npm install sharp
```
Script `iconos.js`:
```js
const sharp=require('sharp');
(async()=>{
  await sharp('icon.svg',{density:300}).resize(192,192).png().toFile('icon-192.png');
  await sharp('icon.svg',{density:300}).resize(512,512).png().toFile('icon-512.png');
  console.log('Íconos PNG generados.');
})();
```
```bash
node iconos.js && del iconos.js
```
(Si `sharp` no instala, probá `npm install @resvg/resvg-js` o dejá este paso para
después: en Android la app ya se instala con el SVG.)

### Paso 6 — Confirmar que config.php NO se va a subir
```bash
git status
git ls-files | findstr config.php
```
El segundo comando NO debe listar `api/config.php`. Si lo lista, ejecutá
`git rm --cached api/config.php` y verificá que `api/config.php` esté en `.gitignore`.

### Paso 7 — Commit y push
```bash
git add -A
git commit -m "ÁPICE: gestión de claves, control de acceso, cuentas individual/estudio, 6 mejoras de la app, PWA (instalable) y tutorial de un solo uso"
git push origin main
```
Si `git push` se rechaza por historial divergente, hacé:
```bash
git pull --rebase origin main
git push origin main
```

### Paso 8 — Verificar el despliegue
- En https://github.com/lujanrociocristal-coder/Apice → pestaña **Actions**: la
  ejecución "Desplegar a Hostinger" debe quedar en **verde**.
- Si queda en rojo por FTP, revisar los secretos `FTP_SERVER` (212.85.6.157),
  `FTP_USERNAME` (u434165369), `FTP_PASSWORD` en Settings → Secrets → Actions.

### Paso 9 — Migración de base (una sola vez, en el navegador)
Abrir **https://abogadoscatamarca.com/install.php** → botón **Instalar ahora**.
Esto agrega las columnas nuevas (`es_admin`, `es_superadmin`, `debe_cambiar_clave`,
`estudios.tipo`) sin borrar datos. Luego **borrar** `install.php` y
`crear-usuario.php` del servidor (Administrador de archivos de Hostinger).

---

## QUÉ INCLUYEN LOS CAMBIOS (para referencia)
- Backend: gestión de usuarios y claves; super-administradora; registro público
  cerrado; cuentas **individual** (1 abogada) vs **estudio** (2+); cambio de clave
  obligatorio con clave temporal.
- Página nueva `gestion-usuarios.html` (administración).
- App `index.html`: crear causa eligiendo estado (preparación/trámite); eliminar
  causa; tareas que quedan tachadas y se borran con la ×; aceptación de términos
  obligatoria al inicio; avisos de causa/cliente duplicados; elegir cliente
  existente o nuevo al crear causa; tutorial que aparece una sola vez.
- PWA: `manifest.json`, `icon.svg`, `sw.js`, etiquetas en el `<head>` → app
  instalable en celular con ícono de ÁPICE.

## CUANDO TERMINE
Avisale a Claude: *"Antigravity ya ejecutó el runbook"*, e indicá el color del
Action (verde/rojo) y cualquier error, así Claude controla que quedó bien.
