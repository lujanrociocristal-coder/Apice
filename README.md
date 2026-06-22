# ÁPICE — Repositorio del sitio (auto-despliegue a Hostinger)

Este repositorio contiene el sitio ÁPICE y se **publica solo** en Hostinger:
cada vez que actualizás la rama `main`, GitHub sube los archivos a tu hosting.

## Cómo está organizado

```
public/      ← lo que se publica en Hostinger (public_html): la app + la API
database/    ← los .sql para crear la base (NO se publican; se cargan a mano una vez)
docs/        ← guías y documentación (no se publican)
.github/     ← la "receta" del auto-despliegue (no la borres)
```

## Puesta en marcha (una sola vez)

Seguí **`docs/GUIA-GITHUB.md`**. En resumen:

1. Subir estos archivos al repositorio de GitHub.
2. Cargar 3 secretos en GitHub (datos FTP de Hostinger).
3. Crear la base de datos y subir el `config.php` una vez (ver `docs/GUIA-HOSTINGER.md`).
4. ¡Listo! A partir de ahí, cada cambio que subas a `main` se publica solo.

> El archivo `config.php` (con la clave de tu base) **no** está en el repo a
> propósito: vive solo en el servidor y el despliegue no lo toca.
