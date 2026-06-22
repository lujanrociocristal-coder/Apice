# Migración de datos del Prototipo A (opcional)

Tu Prototipo A (el archivo `Apicee.html`) guardaba los datos en el navegador.
Cuando entres por primera vez al nuevo ÁPICE (Prototipo B), tenés tres caminos.
Elegí el que más te sirva. **Ninguno es obligatorio.**

---

## Opción 1 · Empezar de cero (lo más prolijo)

Recomendado si lo que tenías en el Prototipo A eran casos de *ejemplo* o pruebas.

- Al entrar por primera vez, ÁPICE muestra los casos de ejemplo que ya conocés
  (Acosta, Ahumada, Ance, etc.). Son una plantilla para explorar.
- Para dejar el sistema **vacío** y cargar tus causas reales desde cero:
  1. Entrá a ÁPICE y logueate.
  2. Apretá la tecla **F12** (se abre el panel de desarrollador del navegador).
  3. Andá a la pestaña **Consola** ("Console").
  4. Escribí esto y apretá Enter:

     ```
     apiceLimpiarTodo()
     ```
  5. Confirmá. La app se recarga vacía y lista para tus causas reales.

---

## Opción 2 · Quedarte con los casos de ejemplo

Si te sirven como modelo, no hagas nada: ya aparecen al entrar. Podés ir
editándolos o borrándolos uno por uno desde la app, como siempre.

---

## Opción 3 · Traer los datos reales que cargaste en el Prototipo A

Solo tiene sentido si en el archivo viejo (`Apicee.html`) **cargaste causas
reales** en tu navegador y querés conservarlas. Se hace una vez, con copiar y
pegar. No necesitás programar.

### Parte A · Copiar los datos del Prototipo A

1. Abrí tu archivo viejo `Apicee.html` en el **mismo navegador** donde lo venías
   usando (para que estén tus datos).
2. Apretá **F12** → pestaña **Consola**.
3. Pegá este texto y apretá Enter. Te va a mostrar un bloque de datos:

   ```js
   copy(JSON.stringify({
     causas:  localStorage.getItem('gestor_causas_v6'),
     config:  localStorage.getItem('gestor_cfg_v9'),
     agenda:  localStorage.getItem('gestor_aud_v1'),
     clientes:localStorage.getItem('gestor_cli_v1'),
     guia:    localStorage.getItem('gestor_dir_v1')
   }))
   ```

   El comando `copy(...)` deja todo copiado en el portapapeles automáticamente.
   *(Si tu navegador no soporta `copy`, sacá el `copy(` del principio y el `)`
   del final, apretá Enter, y copiá a mano el texto que aparece.)*

### Parte B · Pegar los datos en el ÁPICE nuevo

1. Entrá a **https://abogadoscatamarca.com** y logueate como profesional.
2. Apretá **F12** → pestaña **Consola**.
3. Pegá esto, pero **entre los backticks reemplazá `PEGAR_ACA`** por el texto
   que copiaste en la Parte A:

   ```js
   (async () => {
     const d = JSON.parse(`PEGAR_ACA`);
     const map = {
       causas:  'gestor_causas_v6',
       config:  'gestor_cfg_v9',
       agenda:  'gestor_aud_v1',
       clientes:'gestor_cli_v1',
       guia:    'gestor_dir_v1'
     };
     for (const k in map) {
       if (d[k]) await window.storage.set(map[k], d[k]);
     }
     alert('Datos migrados. La app se va a recargar.');
     location.reload();
   })();
   ```
4. Apretá Enter. Cuando termine, ÁPICE se recarga con tus causas reales, ahora
   guardadas en el servidor y compartidas con tu estudio.

> **Consejo:** antes de migrar, podés probar primero con la Opción 1 para
> familiarizarte. Y si algo no sale, no se pierde nada: tu `Apicee.html`
> original y sus datos en el navegador viejo quedan intactos.

---

## ¿Y los datos de la otra profesional?

Si Breppe (o vos) cargó causas en otra computadora/navegador, repetí la Opción 3
desde esa computadora. Como ahora todo se guarda en el servidor del estudio,
una vez migrado lo ven las dos.
