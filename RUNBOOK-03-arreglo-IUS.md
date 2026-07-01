# RUNBOOK 03 — Publicar el arreglo del IUS

> **Para Ro:** este es el runbook a usar AHORA. Pegáselo COMPLETO a Antigravity
> y decile exactamente: *"Ejecutá este runbook paso a paso. Solo publicar, NO
> modifiques el código. Detenete y avisame si algún paso falla."*
>
> Regla para no confundirte: **usá siempre el runbook con el número más alto.**
> Este es el **03**. Si más adelante hay un **04**, usá ese.

## Qué publica este runbook
Toma TODO lo que está en tu carpeta local y lo sube. En particular, esta vez
incluye los dos arreglos nuevos:
1. **Bug del IUS que se reseteaba:** ya no se pierde el valor al iniciar sesión.
2. **Formato de números del IUS:** al escribir, pone solos los puntos de mil
   (46.000, 1.000.000) y permite coma para decimales.

> Nota: no hace falta que verifiques "si los cambios están en el runbook".
> El runbook sube tu carpeta local completa, y esos cambios YA están guardados ahí.

---

## PASO 1 — Clonar el repositorio en una carpeta temporal
```
git clone https://github.com/lujanrociocristal-coder/Apice.git C:/temp/apice-clone
```

## PASO 2 — Copiar tu carpeta local sobre el clon (sin tocar .git)
En PowerShell:
```
robocopy "C:\Users\Rocio\Documents\Apice\apice-repo" "C:\temp\apice-clone" /E /XD .git
```

## PASO 3 — Limpiar posibles bytes NUL
Creá el archivo `limpiar-nul.js` dentro de `C:\temp\apice-clone` con este contenido:
```js
const fs=require('fs'),path=require('path');
function walk(d){for(const f of fs.readdirSync(d)){const p=path.join(d,f);
  if(f==='.git')continue;const s=fs.statSync(p);
  if(s.isDirectory())walk(p);
  else{let b=fs.readFileSync(p); if(b.includes(0)){fs.writeFileSync(p,Buffer.from(b.filter(x=>x!==0)));console.log('NUL limpiado:',p);}}}}
walk(process.cwd());console.log('Listo.');
```
Y corré:
```
cd C:/temp/apice-clone
node limpiar-nul.js
del limpiar-nul.js
```

## PASO 4 — Validar que no haya errores de sintaxis
```
node --check api.js
node --check sw.js
```
Los dos deben pasar SIN error. Si `api.js` da error, PARÁ y reportá el número de
línea (no subas nada).

## PASO 5 — Confirmar que config.php NO se sube
```
git ls-files | findstr config.php
```
NO debe aparecer `api/config.php`. (Si aparece: `git rm --cached api/config.php`.)

## PASO 6 — Subir (commit + push)
```
git add -A
git commit -m "Arreglo del IUS: no se resetea al iniciar sesion + formato de miles"
git push origin main
```
Si el push se rechaza por historial:
```
git pull --rebase origin main
git push origin main
```

## PASO 7 — Verificar el despliegue
En GitHub → pestaña **Actions**: la ejecución "Desplegar a Hostinger" debe quedar
en **verde**. NO hace falta tocar la base ni install.php (solo son archivos de la app).

---

## CUANDO TERMINE
Volvé con Claude y decile: **"Ya ejecuté el RUNBOOK 03, el Action quedó VERDE"**
(o rojo, con el error). Claude verifica en vivo que el arreglo del IUS esté
publicado.

Después: entrá a la app (Ctrl+Shift+R una vez) y **volvé a cargar tu valor de
IUS** — esta vez queda guardado y no se resetea más.
