# ÁPICE — Estado pendiente (handoff)

> Este archivo registra TODO lo que está hecho en el código pero **todavía no
> subido a GitHub**, porque el entorno de shell que ejecuta git está caído.
> Sirve para no perder nada y retomar exactamente donde quedamos.
> Fecha de la nota: sesión en curso.

## 1) Lo único que falta hacer (acción técnica)

- **Subir (push) a GitHub** la rama `main` con todos los cambios de abajo.
  Requiere: shell del entorno funcionando + un **token de GitHub** (fine-grained,
  permisos Contents y Workflows en Read/Write sobre el repo `Apice`).
- **Después del deploy**, entrar UNA vez a `https://abogadoscatamarca.com/install.php`
  para aplicar las migraciones de base (agrega columnas nuevas; no borra nada).
- **PENDIENTE — generar los íconos PNG** `icon-192.png` e `icon-512.png` a partir
  de `icon.svg` (para el ícono de la app en iPhone). Hacer cuando vuelva el shell,
  por ej. con: `rsvg-convert -w 512 -h 512 icon.svg > icon-512.png` (y 192).
  En Android la instalación ya funciona con el SVG; esto es solo para iOS.

## 2) Cambios ya hechos en el código (sin subir) — por archivo

### Backend (carpeta `api/`)
- `api/lib/auth.php`: agregadas funciones `require_admin`, `require_superadmin`,
  `generar_clave_temporal`; `current_user` ahora trae `es_admin`, `es_superadmin`,
  `debe_cambiar_clave` y `estudio_tipo` (join con estudios).
- `api/routes/auth.php`: login y `me` devuelven esos flags + `estudio_tipo`;
  agregado `cambiar-clave`; **registro público DESHABILITADO**.
- `api/routes/usuarios.php` (NUEVO): gestión de usuarios por cualquier profesional
  del estudio (listar, crear abogada, blanquear clave, activar/desactivar) +
  endpoints SOLO super-admin (`/usuarios/estudios`, `/usuarios/estudio` crear y
  cambiar tipo). Las cuentas individuales no pueden sumar abogadas.
- `api/routes/clientes.php`: el acceso de cliente nace con `debe_cambiar_clave=1`.
- `api/crear-usuario.php`: la primera usuaria queda `es_superadmin=1` y `es_admin=1`.
- `api/schema.sql` y `database/schema.sql`: columnas `es_admin`, `es_superadmin`,
  `debe_cambiar_clave` en usuarios; `tipo` (individual/estudio) en estudios.
- `install.php`: migra esas columnas aunque la base ya exista y marca a la primera
  usuaria como super-admin.
- `database/migracion-usuarios.sql` (NUEVO): migración manual opcional.
- Router `api/index.php`: registrada la ruta `usuarios`.

### Frontend
- `api.js`: ventana de **cambio de clave obligatorio** en el primer ingreso;
  se ocultó "Creá una cuenta" y el botón de Google (solo email+clave);
  **aceptación de términos obligatoria** para entrar.
- `gestion-usuarios.html` (NUEVO): página de administración. Cualquier abogada
  gestiona su estudio; la super-admin tiene panel de "Estudios de la plataforma"
  (crear estudio individual o de varios, y convertir uno en otro).
- `install.php` (NUEVO) + `api/schema.sql`/`api/seed.sql` (copias para el instalador).
- `index.html` (la app): 6 cambios pedidos —
  1. Crear causa eligiendo estado inicial (preparación/trámite) + expediente.
  2. Botón "Eliminar causa" (doble confirmación). Cliente ya era borrable.
  3. Tareas: al completarlas quedan tachadas; se borran con la ×.
  4. Aceptación de términos obligatoria al inicio (abogadas y clientes).
  5. Aviso de causa duplicada (por N° de expediente) y de cliente duplicado.
  6. Al crear causa, elegir cliente existente del desplegable o cargar uno nuevo.

### Despliegue
- `.github/workflows/deploy.yml`: FTP simple a `public_html`; estructura del repo
  con archivos web en la raíz.

## 3) Pendiente de PROBAR tras subir
- Como no se pudo correr el chequeo de sintaxis sobre `index.html`, verificar que
  la app cargue bien después del deploy.

## 4) Cambios nuevos hechos (también sin subir)
- `index.html`: el tutorial se marca como visto al abrirse la primera vez
  (no reaparece en cada ingreso).
- PWA (app instalable): `manifest.json`, `icon.svg`, `sw.js` (NUEVOS) y etiquetas
  en el `<head>` de `index.html` + registro del service worker en `api.js`.
- `COMO-INSTALAR-EN-CELULAR.md` (NUEVO): guía de instalación.
- PENDIENTE menor: generar `icon-192.png` e `icon-512.png` desde `icon.svg`
  (para el ícono en iPhone) cuando vuelva el shell. En Android ya funciona con el SVG.
- Rol doble confirmado: en `crear-usuario.php` la primera usuaria queda
  `es_superadmin=1` Y `es_admin=1` (super-admin + admin de su propio estudio).
