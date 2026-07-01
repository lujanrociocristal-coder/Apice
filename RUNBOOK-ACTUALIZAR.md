# RUNBOOK CORTO — Subir una actualización de ÁPICE

> **Para Ro:** usá esto cada vez que Claude haga cambios nuevos en la carpeta
> local y haya que publicarlos. Pegáselo a Antigravity y pedile:
> *"Ejecutá este runbook de actualización."*

- Repo: **https://github.com/lujanrociocristal-coder/Apice** (rama `main`)
- Carpeta local (fuente de verdad): **`C:\Users\Rocio\Documents\Apice\apice-repo`**
- Regla de oro: **NO** subir `api/config.php` (ya está en `.gitignore`).

## Pasos

```powershell
# 1. Clonar limpio (o reutilizar C:\temp\apice-clone borrándolo antes)
rmdir /s /q C:\temp\apice-clone 2>nul
git clone https://github.com/lujanrociocristal-coder/Apice.git C:\temp\apice-clone

# 2. Copiar los archivos nuevos de la carpeta local sobre el clon (sin .git)
robocopy "C:\Users\Rocio\Documents\Apice\apice-repo" "C:\temp\apice-clone" /E /XD .git
```

```bash
# 3. Limpiar bytes NUL (mismo script del runbook original, guardado como limpiar-nul.js)
cd C:/temp/apice-clone && node limpiar-nul.js && del limpiar-nul.js

# 4. Validar
node --check api.js
node --check sw.js

# 5. Confirmar que config.php NO se sube
git ls-files | findstr config.php   # NO debe listar api/config.php

# 6. Commit + push
git add -A
git commit -m "ÁPICE: actualización (auto-actualización sin caché + acceso a Usuarios y claves)"
git push origin main
```

## Después
- Verificar en GitHub → Actions que "Desplegar a Hostinger" quede en **verde**.
- NO hace falta tocar la base ni install.php (solo son archivos de la app).
- Gracias al nuevo `.htaccess`, los usuarios verán la última versión sin hacer
  Ctrl+Shift+R (a lo sumo, una única vez más para tomar esta versión).

## Script limpiar-nul.js (por si no lo tenés)
```js
const fs=require('fs'),path=require('path');
const exts=['.html','.js','.php','.css','.json','.md','.sql','.yml','.yaml','.svg','.txt','.htaccess'];
function walk(d){for(const f of fs.readdirSync(d)){const p=path.join(d,f);
  if(f==='.git')continue;const s=fs.statSync(p);
  if(s.isDirectory())walk(p);
  else{let b=fs.readFileSync(p); if(b.includes(0)){fs.writeFileSync(p,Buffer.from(b.filter(x=>x!==0)));console.log('NUL limpiado:',p);}}}}
walk(process.cwd());console.log('Listo.');
```
