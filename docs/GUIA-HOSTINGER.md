# Guía de despliegue en Hostinger — paso a paso (sin tecnicismos)

Esta guía te lleva de la mano para poner ÁPICE online en tu dominio
**abogadoscatamarca.com**, usando tu plan **Single Web Hosting** (PHP + MySQL).

> **Lo que hago yo y lo que hacés vos.** Yo te preparé todos los archivos.
> Las acciones dentro del panel de Hostinger (crear la base, subir archivos,
> apuntar el dominio, activar el candado de seguridad) **las hacés vos**: son
> cosas de tu cuenta que yo no puedo tocar. Te las explico una por una.

Calculá unos 30–45 minutos la primera vez. Si algo no coincide exactamente con
lo que ves en pantalla, sacá una captura y mandámela y te oriento.

---

## Resumen de los pasos

1. Entrar al panel de Hostinger (hPanel).
2. Crear la base de datos MySQL y anotar 4 datos.
3. Completar el archivo `config.php` con esos 4 datos.
4. Subir los archivos a tu sitio (`public_html`).
5. Cargar la base de datos con phpMyAdmin (el `schema.sql`).
6. Cargar los datos iniciales (`seed.sql`).
7. Apuntar el dominio y activar HTTPS (el candado).
8. Crear tu primer usuario (Breppe) y borrar la página de creación.
9. (Opcional) Cargar la Guía Judicial de Catamarca.

---

## Paso 1 · Entrar al panel (hPanel)

1. Entrá a **https://hpanel.hostinger.com** con tu correo y contraseña de Hostinger.
2. Si te muestra una lista, elegí tu plan **Single Web Hosting** (el de
   abogadoscatamarca.com). Vas a ver un menú con secciones como *Sitios web*,
   *Bases de datos*, *Archivos*, *Dominios*, etc.

---

## Paso 2 · Crear la base de datos MySQL

La base de datos es donde van a vivir todos los datos de ÁPICE.

1. En el buscador del panel, escribí **MySQL** y entrá a
   **Bases de datos → Bases de datos MySQL**.
2. En *Crear una base de datos MySQL nueva*, completá:
   - **Nombre de la base de datos:** escribí `apice` (Hostinger le agrega solo un
     prefijo, así que va a quedar algo como `u123456789_apice`).
   - **Nombre de usuario:** escribí `apice` (también queda con prefijo, ej:
     `u123456789_apice`).
   - **Contraseña:** poné una contraseña fuerte y **anotala** (te va a hacer
     falta enseguida). Podés usar el botón de generar.
3. Tocá **Crear**.
4. Cuando aparezca en la lista, **anotá estos 4 datos** (los vas a usar en el
   Paso 3):

   | Dato | Dónde lo ves | Ejemplo |
   |---|---|---|
   | Host | Casi siempre es `localhost` | `localhost` |
   | Nombre de la base | El nombre completo con prefijo | `u123456789_apice` |
   | Usuario | El usuario completo con prefijo | `u123456789_apice` |
   | Contraseña | La que pusiste recién | (la tuya) |

> Si en algún lado dice que el host es otra cosa (no `localhost`), usá ese valor.

---

## Paso 3 · Completar `config.php` con esos datos

1. En tu computadora, entrá a la carpeta
   `apice-prototipo-b/backend/api/`.
2. Hacé una copia del archivo **`config.example.php`** y renombrá la copia a
   **`config.php`** (sin el `.example`).
3. Abrí `config.php` con el Bloc de notas (o cualquier editor de texto) y
   completá los 4 datos del Paso 2 donde dice `COMPLETAR_...`:
   - `db_name` → el nombre completo de la base.
   - `db_user` → el usuario completo.
   - `db_pass` → la contraseña.
   - `app_secret` → tecleá un montón de letras y números al azar (largo).
4. Guardá el archivo. (El dominio ya está puesto: `abogadoscatamarca.com`.)

---

## Paso 4 · Subir los archivos al sitio

Tu sitio vive en una carpeta llamada **`public_html`**. Vamos a poner ahí la app
y la API.

**Cómo te tiene que quedar dentro de `public_html`:**

```
public_html/
├── index.html        ← (viene de frontend/index.html)
├── api.js            ← (viene de frontend/api.js)
└── api/              ← (viene de backend/api/  COMPLETA)
    ├── index.php
    ├── .htaccess
    ├── config.php
    ├── crear-usuario.php
    ├── lib/
    └── routes/
```

**Forma fácil (recomendada): subir un ZIP y descomprimir.**

1. En tu compu, comprimí en un ZIP el contenido de `frontend/` (los archivos
   `index.html` y `api.js`) y, por otro lado, la carpeta `backend/api/`.
   *(Tip: para no enredarte, podés armar una carpeta con la estructura de
   arriba ya lista y comprimir todo junto.)*
2. En hPanel, buscá **Administrador de archivos** y entrá.
3. Abrí la carpeta **`public_html`**.
4. Arriba, usá **Subir** (icono de la nube/flecha) para subir tu ZIP.
5. Hacé clic derecho sobre el ZIP → **Extraer / Descomprimir**.
6. Acomodá los archivos para que queden como el esquema de arriba:
   `index.html` y `api.js` directamente en `public_html`, y la carpeta `api`
   adentro de `public_html`.
7. Cuando termine, **borrá el ZIP** del servidor (clic derecho → Eliminar).

> Si Hostinger ya creó un `index.html` o `index.php` de muestra dentro de
> `public_html`, podés reemplazarlo/borrarlo: tu `index.html` es el que manda.

---

## Paso 5 · Cargar la base de datos (schema.sql)

Ahora le decimos a la base de datos qué "casilleros" (tablas) tiene que tener.

1. En hPanel, buscá **phpMyAdmin** (en *Bases de datos → phpMyAdmin*) y entrá.
   Te abre una página con tus bases a la izquierda.
2. Hacé clic en tu base (la que creaste, ej: `u123456789_apice`).
3. Arriba, entrá a la pestaña **Importar**.
4. En *Archivo a importar*, tocá **Elegir archivo** y seleccioná
   **`database/schema.sql`** (de la carpeta del proyecto, en tu compu).
5. Dejá todo lo demás como está y tocá **Continuar / Importar** (abajo).
6. Tiene que aparecer un cartel verde de éxito. Si mirás a la izquierda, vas a
   ver aparecer muchas tablas (estudios, usuarios, causas, etc.).

---

## Paso 6 · Cargar los datos iniciales (seed.sql)

Esto carga los feriados de Argentina y Catamarca.

1. En phpMyAdmin, con tu base seleccionada, volvé a **Importar**.
2. Elegí el archivo **`database/seed.sql`** y tocá **Continuar**.
3. Listo: ya tenés los feriados cargados.

*(La Guía Judicial de Catamarca se carga en el Paso 9, después de crear el
primer usuario.)*

---

## Paso 7 · Dominio y HTTPS (el candado de seguridad)

Queremos que al entrar a **abogadoscatamarca.com** se abra ÁPICE, y que se vea
con el candadito (conexión segura).

1. **Dominio:** en hPanel, andá a **Dominios**. Si `abogadoscatamarca.com` ya
   figura apuntando a este plan (a `public_html`), no tenés que hacer nada.
   - Si el dominio está en otro proveedor, usá la opción de **apuntar dominio**
     y seguí las instrucciones de Hostinger (suele ser cambiar los "nameservers"
     a los de Hostinger). Esto puede tardar unas horas en activarse.
2. **HTTPS (SSL):** en hPanel buscá **SSL** (en *Seguridad → SSL*). Tu plan
   incluye SSL gratis.
   - Si dice *Instalar/Activar*, tocálo y esperá unos minutos.
   - Activá también **Forzar HTTPS** si aparece la opción (así siempre entra por
     la versión segura).
3. Probá entrando a **https://abogadoscatamarca.com**: tenés que ver la pantalla
   de bienvenida de ÁPICE.

---

## Paso 8 · Crear tu primer usuario (Breppe) y cerrar la puerta

1. En el navegador, entrá a:
   **https://abogadoscatamarca.com/api/crear-usuario.php**
2. Completá el formulario:
   - Nombre del estudio (ej: *Estudio Luján & Breppe*).
   - Tu nombre (ej: *Valeria Daiana Breppe*).
   - Email y contraseña (mínimo 8 caracteres). **Anotalos.**
3. Tocá **Crear usuario**. Te va a confirmar que se creó el estudio y tu usuaria.
4. **MUY IMPORTANTE (seguridad):** volvé al **Administrador de archivos** de
   Hostinger, entrá a `public_html/api/` y **borrá el archivo
   `crear-usuario.php`**. Ya no lo necesitás, y así nadie más puede usarlo.
5. Entrá a **https://abogadoscatamarca.com**, elegí *Soy profesional*, poné tu
   email y contraseña y... ¡estás adentro! 🎉

---

## Paso 9 · (Opcional) Cargar la Guía Judicial de Catamarca

Si querés tener precargados los juzgados, mediación, equipo técnico, etc.:

1. Entrá a **phpMyAdmin** → tu base → **Importar**.
2. Elegí **`database/seed-guia-catamarca.sql`** y tocá **Continuar**.
3. Listo: ya aparecen en la sección *Guía Judicial* de ÁPICE.

> Este archivo asume que tu estudio es el número 1 (lo normal si es el primero
> que creaste). Si no, abrí el archivo y cambiá el `SET @eid := 1;` por el
> número correcto (lo ves en la tabla `estudios`).

---

## Si algo sale mal (problemas comunes)

- **"No se pudo conectar a la base de datos":** revisá los 4 datos en
  `config.php` (Paso 3). El error más común es un nombre o contraseña mal
  copiados, o haber olvidado el prefijo `u123456789_`.
- **La página se ve en blanco o da error 500:** verificá que la carpeta `api`
  esté completa dentro de `public_html` y que exista `config.php`.
- **Entro pero no guarda nada:** asegurate de haber importado `schema.sql`
  (Paso 5). Sin las tablas, no hay dónde guardar.
- **No carga el diseño / botones raros:** confirmá que `api.js` esté en
  `public_html` (al lado de `index.html`).
- **Dice "Email o contraseña incorrectos":** ¿creaste el usuario en el Paso 8?
  ¿Estás usando el mismo email/contraseña que anotaste?

Cuando todo funcione, podés invitar a la otra profesional del estudio: por ahora
se crea una cuenta nueva desde *"Creá una"* en la pantalla de ingreso. En la
etapa relacional sumamos la invitación de colegas dentro de la app.
