# CR Backend — API de Historias Clínicas

API REST en Laravel para capturar, consultar y administrar las historias clínicas laborales de
Cruz Roja Mexicana, Delegación Estatal Colima (Coordinación de Servicios Médicos). Sustituye la
capa simulada (`localStorage`) del frontend `crm-colima-hc` por una base de datos real en
PostgreSQL, con autenticación por token, control de roles y bitácora de accesos.

## Stack y versiones

| Componente | Versión |
|---|---|
| PHP | 8.4 |
| Laravel Framework | 13.35 |
| PostgreSQL | 16 |
| Laravel Sanctum (autenticación) | 4.3 |
| Docker / Docker Compose | 29 / v2 |
| nginx | 1.27 |
| Laravel Pint (estilo de código) | 1.32 |
| PHPUnit | 12.5 |

Librerías de Composer relevantes (ver `composer.json` para la lista completa y resolución exacta
en `composer.lock`):

- `laravel/sanctum` — tokens Bearer de API (sesión única, expiración de 8 horas).
- `laravel/tinker` — consola interactiva para depuración.
- `laravel/pint` / `phpunit/phpunit` / `mockery/mockery` / `fakerphp/faker` — calidad y pruebas (dev).

No se usan colas, Redis, broadcasting ni Octane: el alcance del proyecto no los necesita y cada
dependencia extra es superficie de ataque/configuración adicional en un sistema con datos de salud.

## Arquitectura y decisiones de diseño

- **Migraciones con `Schema::create` + `Blueprint`** (estilo idiomático de Laravel), no DDL crudo.
  Se usa `DB::statement()` puntual solo para lo que el Schema Builder no expresa: un puñado de
  `check` multi-columna, los índices únicos parciales de `pacientes` (CURP/NSS cuando no son nulos)
  y la vista `medicos_selector` (las vistas nunca se crean con el Schema Builder).
- **`folio` es una columna *identity* de Postgres** (`generatedAs()->always()`), no una secuencia
  manual: Postgres la asigna de forma atómica dentro de la misma transacción del alta.
- **Columnas generadas (*stored*)** para `ta_conjunta`, `imc` y `estado_nutricional`
  (`hc_exploracion`): Postgres las recalcula solas a partir de peso/talla/TA; la aplicación nunca
  las escribe.
- **Autenticación por token Bearer (Sanctum), no cookies de sesión**, porque frontend y backend
  corren en orígenes distintos. **Sesión única por usuario**: cada login revoca cualquier token
  anterior de esa cuenta (si alguien no cerró sesión en otro equipo, el siguiente login la
  invalida); logout destruye solo el token con el que se hizo la petición.
- **Hash de contraseñas con Argon2id** (no bcrypt por defecto, no SHA-256 del lado del cliente).
- **Separación de permisos por middleware**, no solo por UI: `servicios_medicos` únicamente puede
  capturar/editar pacientes y expedientes, y su propio perfil/contraseña. Todo lo demás
  (`/users/*`, `/bitacora`, `/export/*`, los datos para PDF) requiere `rol = admin`. El único
  camino para que un médico recupere una contraseña olvidada es que un admin la restablezca
  (`POST /users/:id/reset-password`).
- **El backend no genera PDF ni Excel.** El frontend ya construye esos archivos en el navegador a
  partir de los datos del expediente (`@react-pdf/renderer`, `exceljs`). Los endpoints que en el
  encargo original se describían como "descarga de PDF" y "export de Excel" aquí devuelven **JSON**
  con los mismos datos y el mismo control de rol (solo admin), no un binario.
- **No existe catálogo de códigos postales en el backend.** El frontend ya resuelve
  CP → estado/municipio/colonias con su propia librería/datos estáticos. `domicilios` tampoco
  guarda el CP: es solo un asistente de captura en el cliente.
- **Bitácora append-only**: solo el servidor escribe en `auditoria` (`App\Services\AuditoriaService`),
  siempre dentro de la misma transacción del evento que la dispara (creación, edición o consulta
  de un expediente).

## Estructura del proyecto

```
app/
  Enums/            RolUsuario, EstadoUsuario, AccionAuditoria
  Exceptions/        Formato de error único de la API ({ "error": { code, message, fields } })
  Http/
    Controllers/Api/V1/   Auth, Me, User, Paciente, Expediente, Bitacora, Export
    Middleware/            EnsureRole, EnsureActiveUser, BlockIfTemporaryPassword
    Requests/              Validación por endpoint (mensajes exactos del frontend original)
    Resources/             Forma pública de usuarios y expedientes
  Models/            User, Paciente, Domicilio, HistoriaClinica, Hc* (módulos clínicos), Auditoria
  Rules/             NombrePersona, CedulaProfesional, PasswordSegura
  Services/          CalculoClinicoService, GradienteService, PasswordGeneratorService,
                     PacienteUpsertService, ExpedienteBuilder, AuditoriaService
  Support/           Catálogo de columnas por módulo y sus etiquetas oficiales (para la bitácora)
database/
  migrations/        Una tabla (o vista) por archivo, en orden de dependencias
  seeders/           AdminSeeder (cuenta inicial), MedicoFirmanteSeeder (firmante oficial)
docker/
  php/               Dockerfile (PHP-FPM 8.4 + extensiones), opcache.ini
  nginx/             Vhost de nginx
routes/api.php       Todas las rutas, versionadas bajo /api/v1
tests/
  Feature/           Auth, Users, Pacientes, Expedientes, Bitacora, Export (contra Postgres real)
```

## Cómo levantar el entorno (Docker)

Requisitos: Docker y Docker Compose.

```bash
cp .env.example .env
# Genera una APP_KEY real (no uses la del repositorio en producción):
docker compose run --rm app php artisan key:generate

docker compose up -d --build
docker compose exec app php artisan migrate --seed
```

El seeder imprime en consola **una sola vez** la contraseña temporal de la cuenta `admin` inicial
(`admin@crm-colima.mx`); guárdala en ese momento, no se puede volver a mostrar.

La API queda disponible en `http://localhost:8080/api/v1` (puerto configurable con `APP_PORT` en
`.env`). `GET /up` es el healthcheck de Laravel.

### Variables de entorno relevantes

| Variable | Para qué sirve |
|---|---|
| `DB_HOST` | `db` dentro de Docker; `127.0.0.1` si corres Laravel fuera del contenedor |
| `APP_TIMEZONE` | `America/Mexico_City` — zona de negocio para fechas y la jornada del Excel |
| `HASH_DRIVER` | `argon2id` |
| `SANCTUM_TOKEN_EXPIRATION_MINUTES` | Vida del token Bearer (480 = 8 horas, una jornada) |
| `CORS_ALLOWED_ORIGINS` | Dominio(s) del frontend, separados por coma |

## Pruebas automatizadas

El esquema usa columnas generadas, *identity* e índices parciales de Postgres, así que las
pruebas corren contra **Postgres real** (no SQLite), en una base de datos separada
(`cr_backend_testing`), creada una sola vez:

```bash
docker compose exec db psql -U cr_backend -d cr_backend -c "CREATE DATABASE cr_backend_testing;"
docker compose --profile test run --rm app_test php artisan test
```

**Importante:** las pruebas se corren con el servicio `app_test`, no con `app`. `docker-compose.yml`
inyecta todo `.env` como variables de entorno reales del contenedor (`env_file`), y esas pisan
cualquier `<env>` de `phpunit.xml` — si corrieras `php artisan test` dentro de `app`, `RefreshDatabase`
terminaría borrando la base de datos de **desarrollo** en vez de `cr_backend_testing`. `app_test` es
el mismo build pero con su propio `APP_ENV`/`DB_DATABASE`, para que esto no pueda pasar por accidente.

29 pruebas de característica cubren: login (orden de validación y mensajes exactos), sesión única,
salvaguardas de usuarios (nadie se autodesactiva ni se autodegrada; siempre queda ≥1 admin activo),
alta/edición de expedientes con sus cálculos (edad, IMC, TA conjunta, estado nutricional, folio
consecutivo), bitácora, y los endpoints de datos para PDF/Excel.

## Resumen de endpoints

Base `/api/v1`. Autenticación: `Authorization: Bearer <token>` (se obtiene en `/auth/login`).

| Método y ruta | Rol | Qué hace |
|---|---|---|
| `POST /auth/login` | público | Inicia sesión; revoca el token anterior de esa cuenta |
| `POST /auth/logout` | cualquiera | Revoca el token actual |
| `GET /auth/me` | cualquiera | Usuario autenticado |
| `PUT /me` | cualquiera | Edita el perfil propio |
| `POST /me/password` | cualquiera | Cambia la contraseña propia |
| `GET /users?vista=selector` | cualquiera | Médicos activos para el selector de expedientes |
| `GET /users` | admin | Listado completo de cuentas |
| `POST /users` | admin | Alta de cuenta (contraseña temporal) |
| `GET /users/{id}` | admin | Detalle de una cuenta |
| `PUT /users/{id}` | admin | Edición de una cuenta |
| `POST /users/{id}/reset-password` | admin | Restablece la contraseña de otra cuenta |
| `GET /pacientes/buscar?q=` | cualquiera | Buscador del encabezado |
| `GET /pacientes/lookup?curp=&nss=` | cualquiera | Precarga de un paciente recurrente |
| `GET /expedientes/siguiente-folio` | cualquiera | Folio informativo |
| `GET /expedientes` | cualquiera | Navegador de expedientes (filtros y paginación) |
| `GET /expedientes/estadisticas` | cualquiera | Tarjetas de métricas |
| `POST /expedientes` | cualquiera | Crea un expediente |
| `GET /expedientes/{folio}` | cualquiera | Abre un expediente (registra "Consulta") |
| `PUT /expedientes/{folio}` | cualquiera | Edita un expediente (registra "Edición de Expediente") |
| `GET /bitacora` | admin | Consulta la bitácora |
| `GET /expedientes/{folio}/pdf?plantilla=PECO\|TX` | admin | Datos del expediente para que el frontend genere el PDF |
| `GET /export/excel?fecha=` | admin | Datos de la jornada para que el frontend genere el Excel |

Formato de error único en toda la API:

```json
{ "error": { "code": "VALIDATION_ERROR", "message": "Escribe el nombre completo.", "fields": { "nombre_completo": "Escribe el nombre completo." } } }
```

## Seguridad

- Contraseñas con Argon2id; nunca se guarda ni se expone el hash en las respuestas.
- Límite de intentos de login: 5 por minuto, contados por IP **y** por cuenta a la vez.
- CORS restringido a los orígenes declarados en `CORS_ALLOWED_ORIGINS`.
- Los errores 500 nunca exponen detalles internos (traza, consulta SQL, etc.) al cliente.
- `.env.example` no contiene secretos reales; cambia `DB_PASSWORD` y genera una `APP_KEY` propia
  antes de desplegar.
