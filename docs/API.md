# API de ÁPICE — lista de endpoints

Esta es la "lista de pedidos" que el frontend le hace al backend. No necesitás
usarla a mano: el frontend ya la usa solo. Está acá por si algún día querés
entender o ampliar el sistema.

**Base:** todas las direcciones empiezan con `/api/`
**Formato:** los datos van y vuelven en JSON.
**Respuesta:** siempre `{ "ok": true, "data": ... }` o `{ "ok": false, "error": "..." }`
**Sesión:** después de hacer login, la cookie segura mantiene la sesión.

---

## Autenticación — `/api/auth`

| Método | Camino | Qué hace |
|---|---|---|
| POST | `/api/auth/login` | Entrar con `email` y `password` |
| POST | `/api/auth/logout` | Salir |
| GET  | `/api/auth/me` | ¿Quién soy? (datos de la sesión) |
| POST | `/api/auth/register` | Crear cuenta de profesional (abre un estudio) |
| POST | `/api/auth/aceptar` | Registrar aceptación de términos/privacidad |

## Causas — `/api/causas`

| Método | Camino | Qué hace |
|---|---|---|
| GET | `/api/causas` | Lista de causas visibles para mí |
| GET | `/api/causas/{id}` | Una causa completa (bitácora, docs, tareas, honorarios, alertas) |
| POST | `/api/causas` | Crear causa |
| PUT | `/api/causas/{id}` | Editar causa |
| DELETE | `/api/causas/{id}` | Borrar causa (solo la dueña) |
| POST | `/api/causas/{id}/movimientos` | Agregar movimiento a la bitácora |
| DELETE | `/api/causas/{id}/movimientos/{mid}` | Borrar movimiento |
| POST | `/api/causas/{id}/compartir` | Compartir con un colega (`usuario_id`, `permiso`) |
| DELETE | `/api/causas/{id}/compartir/{uid}` | Dejar de compartir |

## Clientes — `/api/clientes`

| GET | `/api/clientes` | Lista de clientes |
| GET | `/api/clientes/{id}` | Un cliente con sus causas |
| POST | `/api/clientes` | Crear |
| PUT | `/api/clientes/{id}` | Editar |
| DELETE | `/api/clientes/{id}` | Borrar |
| POST | `/api/clientes/{id}/acceso` | Darle acceso al portal (crea usuario rol cliente) |

## Documentos — `/api/documentos`

| GET | `/api/documentos?causa=ID` | Documentos de una causa |
| POST | `/api/documentos` | Agregar (metadatos/enlace) |
| PUT | `/api/documentos/{id}` | Editar |
| DELETE | `/api/documentos/{id}` | Borrar |

## Tareas — `/api/tareas`

| GET | `/api/tareas` o `?causa=ID` | Listar |
| POST | `/api/tareas` | Crear |
| PUT | `/api/tareas/{id}` | Editar / marcar hecha |
| DELETE | `/api/tareas/{id}` | Borrar |

## Audiencias y citas — `/api/audiencias`

| GET | `/api/audiencias` | Agenda (del estudio o del cliente) |
| POST | `/api/audiencias` | Crear audiencia o cita (`tipo`: juzgado/mediacion/cita) |
| PUT | `/api/audiencias/{id}` | Editar |
| DELETE | `/api/audiencias/{id}` | Borrar |

## Honorarios — `/api/honorarios`

| GET | `/api/honorarios?causa=ID` | Gastos + pagos de la causa |
| POST | `/api/honorarios/gasto` | Agregar gasto |
| PUT | `/api/honorarios/gasto/{id}` | Editar / marcar pagado |
| DELETE | `/api/honorarios/gasto/{id}` | Borrar gasto |
| POST | `/api/honorarios/pago` | Registrar pago |
| DELETE | `/api/honorarios/pago/{id}` | Borrar pago |

## Recibos — `/api/recibos`

| GET | `/api/recibos` | Lista de recibos del estudio |
| GET | `/api/recibos/{id}` | Un recibo |
| POST | `/api/recibos` | Emitir recibo (asigna número correlativo automático) |

## Convenios — `/api/convenios`

| GET | `/api/convenios?causa=ID` | Ver convenio de la causa |
| POST | `/api/convenios` | Crear o actualizar (texto y datos los definís vos) |

## Guía Judicial — `/api/guia`

| GET | `/api/guia` | Organismos del estudio |
| POST | `/api/guia` | Agregar |
| PUT | `/api/guia/{id}` | Editar |
| DELETE | `/api/guia/{id}` | Borrar |

## Configuración — `/api/config`

| GET | `/api/config` | Datos del estudio + valor IUS |
| PUT | `/api/config` | Editar datos del estudio / valor IUS |
| GET | `/api/config/feriados` | Feriados |
| POST | `/api/config/feriados` | Agregar feriado |
| DELETE | `/api/config/feriados/{id}` | Borrar feriado |

## EstrategIA / IA — `/api/ia` (ETAPA 2)

| GET | `/api/ia/{funcion}` | Responde "preparado / no disponible". Se activa en la 2ª etapa. |

---

### Reglas de acceso (resumen)

- Cada usuario pertenece a **un estudio** y solo ve datos de ese estudio.
- **Profesional:** acceso completo a su estudio. Ve sus causas y las que le
  compartieron.
- **Cliente:** solo **lectura**, y solo de **sus** causas, documentos visibles
  y su agenda.
- Una causa tiene **dueña** (`owner`); puede compartirse con **colaboradoras**
  del mismo estudio, en modo lectura o edición.
