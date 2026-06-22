# ÁPICE · Gestión Jurídica Inteligente — Prototipo B

Esta carpeta contiene la versión "real" de ÁPICE: una aplicación web con
**servidor, base de datos y login**, lista para subir a tu hosting de
Hostinger. Está explicada para que la entiendas aunque no programes.

> **Tu archivo original NO se tocó.** Sigue intacto en
> `Documents/Apice/Apicee.html`. Todo lo nuevo vive acá adentro, en
> `apice-prototipo-b/`.

---

## ¿Qué cambia respecto del Prototipo A?

- **Antes (Prototipo A):** un solo archivo HTML. Los datos se guardaban en el
  navegador de cada compu. Vos y Breppe no veían lo mismo.
- **Ahora (Prototipo B):** los datos viven en un **servidor** (en Hostinger),
  en una **base de datos** compartida. Cada persona entra con su **email y
  contraseña**. Lo que carga una, lo ve la otra (si comparten la causa).

Pensalo como pasar de un cuaderno personal a un archivo de estudio compartido,
con llave para entrar y permisos por persona.

---

## Las tres partes del proyecto (las tres carpetas)

```
apice-prototipo-b/
├── frontend/     ← LA APP (las pantallas que ves y usás)
├── backend/      ← EL CEREBRO en el servidor (login + guardar/leer datos)
├── database/     ← LA BASE DE DATOS (instrucciones para crear las tablas)
├── README.md            ← este archivo
├── GUIA-HOSTINGER.md    ← cómo subir todo a Hostinger, paso a paso
├── MIGRACION.md         ← cómo traer (si querés) tus datos del Prototipo A
└── backend/api/config.example.php  ← datos a completar (usuario y clave de la base)
```

### 1) `frontend/` — la app
Son las pantallas de ÁPICE (las mismas de tu `Apicee.html`), adaptadas para
**pedirle los datos al servidor** en vez de guardarlos en el navegador. El
diseño y las pantallas quedan iguales.

### 2) `backend/` — el cerebro (en PHP)
Es el programa que corre en el servidor. Hace tres cosas:
- **Cuida la puerta:** verifica email y contraseña, y decide quién puede ver qué.
- **Guarda y entrega datos:** cuando cargás una causa, el frontend se la pasa
  al backend y el backend la guarda en la base. Cuando abrís ÁPICE, se la pide.
- **Mantiene separados los estudios:** cada estudio ve solo lo suyo.

Adentro hay una carpeta `api/` con un archivo por tema (causas, clientes,
audiencias, honorarios, recibos, etc.). Eso son los "endpoints".

### 3) `database/` — la base de datos
- `schema.sql` → crea **todas las tablas** (el casillero de cada dato).
- `seed.sql` → carga **datos iniciales** de ejemplo (un estudio, feriados de
  Argentina y la Guía Judicial de Catamarca ya cargada).

---

## ¿Qué es un "endpoint" y una "API"? (en simple)

- Una **API** es la lista de "pedidos" que el frontend puede hacerle al backend.
- Un **endpoint** es cada pedido concreto. Por ejemplo:
  - "Dame todas mis causas" → `GET /api/causas`
  - "Guardá esta causa nueva" → `POST /api/causas`
  - "Entrar" → `POST /api/auth/login`

No necesitás memorizar esto: está documentado en `backend/API.md`, y el
frontend ya lo usa solo.

---

## Seguridad y cumplimiento (Ley 25.326 + secreto profesional)

- **Contraseñas cifradas** (bcrypt): nadie puede leerlas, ni siquiera vos o yo.
- **HTTPS** (el candado del navegador): tu plan de Hostinger trae SSL gratis.
- **Aislamiento por estudio:** un estudio jamás ve datos de otro.
- **Permisos por causa:** cada causa tiene dueña y se comparte a voluntad.
- **Rol cliente:** el cliente solo ve, de lectura, SUS causas y SU agenda.
- **Aceptación de términos y privacidad** al primer ingreso (una versión para
  profesionales, otra para clientes). Queda registrada con fecha.

---

## Lo que queda para la 2ª etapa (preparado para conectar)

Estas funciones están dejadas "enchufables" para más adelante, sin frenar el
arranque:
- **EstrategIA / IA:** Diagnóstico Estratégico, Radar Probatorio, Informe del
  Juez, Informe del Demandado, Sala de Guerra, Preparación de Audiencias.
- **Ingresar con Google.**
- **Sincronización con Google Calendar.**
- **Avisos al cliente por email / WhatsApp.**
- **Actualización automática de la Guía Judicial.**

---

## ¿Por dónde sigo?

1. Leé `GUIA-HOSTINGER.md` (paso a paso, sin comandos raros).
2. Completá `config.example.php` con los datos de tu base (la guía te dice de
   dónde sacar cada uno).
3. Subí los archivos, cargá la base, y creá tu primer usuario (Breppe).

Cualquier cosa, volvé a preguntarme y seguimos. 🙂
