# Conectar GitHub con Hostinger (auto-despliegue) — paso a paso

Objetivo: que cada vez que actualices tu repositorio de GitHub, el sitio se
actualice **solo** en Hostinger (abogadoscatamarca.com). Sin subir archivos a
mano nunca más.

> **Por qué este método.** Tu plan **Single** no trae la función "Git" del panel
> de Hostinger (esa es de los planes Premium/Business). En cambio, usamos
> **GitHub Actions por FTP**, que funciona en cualquier plan: GitHub se encarga
> de subir los archivos por FTP cada vez que hay un cambio.

Tu repositorio es: **https://github.com/lujanrociocristal-coder/Apice**

La primera vez son unos 20–30 minutos. Después, publicar un cambio toma segundos.

---

## Mapa de lo que vas a hacer

1. Subir estos archivos a tu repositorio de GitHub (una vez).
2. Conseguir tus datos de FTP en Hostinger (una vez).
3. Guardar esos datos como "secretos" en GitHub (una vez).
4. Ver cómo se publica solo y comprobar que funcionó.
5. Completar la puesta a punto del servidor (base de datos + config + usuario).
6. Tu día a día: editar → subir → se publica solo.

---

## Paso 1 · Subir los archivos a GitHub

Tu repositorio hoy está vacío. Hay que ponerle adentro la carpeta `apice-repo`
que te preparé. La forma más fácil sin programar es con **GitHub Desktop**.

### Opción A (recomendada): GitHub Desktop

1. Descargá e instalá **GitHub Desktop** desde https://desktop.github.com
2. Abrilo e iniciá sesión con tu cuenta de GitHub.
3. Arriba: **File → Clone repository** → pestaña **GitHub.com** → elegí
   `lujanrociocristal-coder/Apice` → **Clone**. Anotá en qué carpeta de tu compu
   lo guarda (te lo muestra).
4. Abrí esa carpeta en el explorador de archivos. Va a estar casi vacía
   (solo una carpeta oculta `.git`).
5. Copiá **todo el contenido** de la carpeta `apice-repo` que te preparé
   (las carpetas `public`, `database`, `docs`, `.github` y los archivos
   `.gitignore` y `README.md`) y pegalo dentro de esa carpeta clonada.
6. Volvé a GitHub Desktop: vas a ver la lista de archivos nuevos. Abajo a la
   izquierda, en *Summary*, escribí algo como `Primera versión de ÁPICE` y tocá
   **Commit to main**.
7. Arriba, tocá **Push origin**. ¡Listo, los archivos ya están en GitHub!

> Si GitHub Desktop te pregunta por la rama y dice `master` en vez de `main`,
> no importa por ahora; más abajo te digo cómo asegurarte de que sea `main`.

### Opción B: arrastrar en la web de GitHub

Sirve para archivos sueltos, pero es engorroso con carpetas y con la carpeta
`.github`. Si podés, usá la Opción A.

---

## Paso 2 · Conseguir tus datos de FTP en Hostinger

El FTP es la "puerta de servicio" por donde GitHub va a dejar los archivos.

1. Entrá a **hPanel** (https://hpanel.hostinger.com) y elegí tu plan.
2. Buscá **Cuentas FTP** (en *Archivos → Cuentas FTP* / "FTP Accounts").
3. Vas a ver (o podés crear) una cuenta FTP. Anotá estos 3 datos:
   - **Servidor / Host FTP** (ej: `ftp.abogadoscatamarca.com` o una IP).
   - **Usuario FTP** (ej: `u123456789` o `usuario@abogadoscatamarca.com`).
   - **Contraseña FTP**. Si no la recordás, usá **Cambiar contraseña** y anotá
     la nueva.

> Consejo de seguridad: si te deja crear una **cuenta FTP nueva** solo para esto,
> mejor. Así esta clave queda separada de tu cuenta principal.

---

## Paso 3 · Guardar los datos FTP como "secretos" en GitHub

Los secretos son datos privados que GitHub guarda cifrados. Nunca se ven en el
código ni los puede leer nadie más.

1. Entrá a tu repo: **https://github.com/lujanrociocristal-coder/Apice**
2. Arriba, tocá **Settings** (Configuración del repo).
3. En el menú de la izquierda: **Secrets and variables → Actions**.
4. Tocá **New repository secret** y creá estos **tres**, uno por uno
   (respetá los nombres en mayúsculas, exactos):

   | Name (nombre) | Secret (valor) |
   |---|---|
   | `FTP_SERVER` | tu servidor FTP (Paso 2) |
   | `FTP_USERNAME` | tu usuario FTP (Paso 2) |
   | `FTP_PASSWORD` | tu contraseña FTP (Paso 2) |

   Para cada uno: escribís el *Name*, pegás el *Secret* y tocás **Add secret**.

---

## Paso 4 · Ver que se publica solo

Apenas terminaste el Paso 1 (push), GitHub ya intentó publicar. Para verlo:

1. En tu repo, tocá la pestaña **Actions**.
2. Vas a ver una ejecución llamada *Desplegar a Hostinger*.
   - Si está en **verde** (✓): ¡funcionó! Tus archivos ya están en Hostinger.
   - Si está en **rojo** (✗): tocala para ver el error. Casi siempre es un dato
     FTP mal copiado (volvé al Paso 3) o que hay que cambiar `ftps` por `ftp`
     (mirá "Si algo sale mal" más abajo).
3. Si recién cargaste los secretos *después* del primer push, volvé a disparar
   el despliegue: en **Actions → Desplegar a Hostinger → Run workflow**
   (botón a la derecha), o subí cualquier cambio chiquito.

Cuando esté en verde, entrá a **https://abogadoscatamarca.com**: tenés que ver
ÁPICE.

---

## Paso 5 · Puesta a punto del servidor (una sola vez)

El auto-despliegue sube la app y la API, pero hay 3 cosas que se hacen una vez
y **no** las hace GitHub (por seguridad):

1. **Crear la base de datos** y **subir `config.php`** con sus datos.
2. **Importar** `schema.sql` y `seed.sql` con phpMyAdmin.
3. **Crear tu usuaria** (Breppe) con `crear-usuario.php` y después borrarlo.

Todo eso está explicado paso a paso en **`docs/GUIA-HOSTINGER.md`** (Pasos 2, 3,
5, 6 y 8). El `config.php` lo subís una vez por el Administrador de archivos y el
auto-despliegue **no lo toca** (está excluido a propósito).

> Importante: subí el `config.php` a `public_html/api/config.php`. Como está en
> la lista de exclusión, los despliegues futuros no lo van a pisar ni borrar.

---

## Paso 6 · Tu día a día (lo lindo)

A partir de acá, para cambiar algo del sitio:

1. Editás el archivo en tu compu (dentro de la carpeta clonada).
2. Abrís GitHub Desktop → escribís un resumen → **Commit to main** → **Push origin**.
3. En segundos, GitHub lo publica solo en Hostinger. Recargá la página y listo.

No más subir archivos a mano. 🎉

---

## Si algo sale mal (problemas comunes)

- **Actions en rojo, error de conexión FTP:** revisá los 3 secretos (Paso 3).
  El error más común es el servidor o la contraseña mal copiados.
- **Error de FTP seguro / certificado:** abrí el archivo
  `.github/workflows/deploy.yml`, cambiá `protocol: ftps` por `protocol: ftp`,
  guardá y subí el cambio. (FTPS es más seguro; si tu servidor no lo acepta bien,
  FTP simple funciona.)
- **Se publicó pero los archivos quedaron en la carpeta equivocada:** en el
  mismo `deploy.yml`, el valor `server-dir: ./public_html/` define a dónde van.
  Si tu cuenta FTP ya te abre *dentro* de `public_html`, cambialo por
  `server-dir: ./`. Guardá y volvé a subir.
- **La página carga pero no guarda datos / da error:** todavía falta la puesta a
  punto del servidor (Paso 5): la base de datos y el `config.php`.
- **Dice que la rama es `master` y no `main`:** en GitHub Desktop, menú
  **Branch → Rename** y poné `main`; o en la web del repo, *Settings → Branches*.
  El despliegue está configurado para `main`.

Cualquier paso que se trabe, sacá una captura de pantalla y mandámela.
