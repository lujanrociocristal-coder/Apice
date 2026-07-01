# RUNBOOK 04 — Formato de números (miles y centavos)

> **Para Ro:** este es el runbook a usar ahora (el número más alto). Pegáselo
> COMPLETO a Antigravity y decile: *"Ejecutá este runbook paso a paso. Solo
> publicar, NO modifiques el código. Detenete y avisame si algún paso falla."*

## Qué publica
Toma tu carpeta local completa y la sube. Esta vez incluye:
- El **IUS** y el **Monto base** ahora ponen los **puntos de mil** al salir del
  campo (1.138.690) y aceptan **coma o punto** para los **centavos** (46.000,50).
  Ya no redondea.

---

## PASO 1 — Clonar el repo en una carpeta temporal
```
git clone https://github.com/lujanrociocristal-coder/Apice.git C:/temp/apice-clone
```

## PASO 2 — Copiar tu carpeta local sobre el clon (sin tocar .git)
```
robocopy "C:\Users\Rocio\Documents\Apice\apice-repo" "C:\temp\apice-clone" /E /XD .git
```

## PASO 3 — Limpiar posibles bytes NUL
Creá `limpiar-nul.js` dentro de `C:\temp\apice-clone`:
```js
const fs=require('fs'),path=require('path');
function walk(d){for(const f of fs.readdirSync(d)){const p=path.join(d,f);
  if(f==='.git')continue;const s=fs.statSync(p);
  if(s.isDirectory())walk(p);
  else{let b=fs.readFileSync(p); if(b.includes(0)){fs.writeFileSync(p,Buffer.from(b.filter(x=>x!==0)));console.log('NUL limpiado:',p);}}}}
walk(process.cwd());console.log('Listo.');
```
Corré:
```
cd C:/temp/apice-clone
node limpiar-nul.js
del limpiar-nul.js
```

## PASO 4 — Validar sintaxis (IMPORTANTE: también index.html)
```
node --check api.js
node --check sw.js
```
Y validá el JavaScript de index.html con este script `check-index.js` (creado en
`C:\temp\apice-clone`):
```js
const fs=require('fs'),vm=require('vm');
const h=fs.readFileSync('index.html','utf8');
const m=h.match(/<script>([\s\S]*?)<\/script>/g)||[];
let all=m.map(s=>s.replace(/^<script>/,'').replace(/<\/script>$/,'')).join('\n;\n');
new vm.Script(all); console.log('index.html JS OK');
```
```
node check-index.js
del check-index.js
```
Si `index.html JS OK` NO aparece (da error), **PARÁ y reportá el error**, no subas.

## PASO 5 — Confirmar que config.php NO se sube
```
git ls-files | findstr config.php
```
NO debe aparecer `api/config.php`.

## PASO 6 — Subir (commit + push)
```
git add -A
git commit -m "Formato de miles y centavos en IUS y Monto base"
git push origin main
```
Si el push se rechaza:
```
git pull --rebase origin main
git push origin main
```

## PASO 7 — Verificar
En GitHub → **Actions**: la ejecución debe quedar en **verde**.

---

## CUANDO TERMINE
Volvé con Claude: *"Ya ejecuté el RUNBOOK 04, quedó VERDE"*. Después probá en la
app (Ctrl+Shift+R): escribí un número en el IUS o en Monto base y, al tocar fuera
del campo, tienen que aparecer los puntos de mil; y probá poner centavos con coma.
