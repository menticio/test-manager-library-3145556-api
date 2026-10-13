# test-manager-library-3145556-api

> **Prueba técnica · Ficha ADSO 3145556**
> Entrega: **martes 13 de octubre de 2026, a las 5:00 p. m.** (17:00, hora Colombia).

Este repositorio es el **backend** de *Manager Library*, en **PHP con Laravel**. **Empieza vacío a propósito**: se construye en el fork de cada aprendiz.

## Instrucciones

Cada aprendiz debe **crear el fork** de los seis repositorios del proyecto y **resolver el proyecto
con el spec planteado**.

1. Hacer fork, a su cuenta de GitHub, de cada repositorio de la tabla del final.
2. Leer el spec en [`test-manager-library-3145556-docs`](https://github.com/code-sena/test-manager-library-3145556-docs).
   Se entrega en dos versiones: `spec-python/` y `spec-.net/`.
3. Desarrollar en los forks.

## El reto se desarrolla con React y PHP (Laravel)

El spec está escrito para Python y para .NET, pero el reto **no** se hace en esos lenguajes:

| Capa | Tecnología del reto |
|---|---|
| Frontend | React |
| Backend | PHP con Laravel |

Lo que el spec define sobre el negocio —historias, criterios de aceptación, reglas, contrato de la
API, modelo de datos— se respeta. Lo que define sobre la tecnología se traduce a React y Laravel.

## La prueba no consiste en escribir el código

El propósito principal es ver la **capacidad de desempeño con SDD** (*Spec-Driven Development*,
desarrollo guiado por especificación): cómo se lee, se interpreta y se aplica una especificación
para llevarla a un stack distinto. El código es el medio, no el fin.

## Entrega

Cuenta lo que quede **subido a los forks antes de las 5:00 p. m.** (17:00). Lo que llegue después no se revisa.
No hay prórroga: suban avances parciales con commits pequeños durante la prueba.

## Los seis repositorios

| Repositorio | Qué va ahí |
|---|---|
| [`test-manager-library-3145556-docs`](https://github.com/code-sena/test-manager-library-3145556-docs) | El spec: `spec-python/` y `spec-.net/` |
| [`test-manager-library-3145556-api`](https://github.com/code-sena/test-manager-library-3145556-api) | Backend en PHP (Laravel) |
| [`test-manager-library-3145556-app`](https://github.com/code-sena/test-manager-library-3145556-app) | Frontend en React |
| [`test-manager-library-3145556-page`](https://github.com/code-sena/test-manager-library-3145556-page) | Sitio público estático de presentación |
| [`test-manager-library-3145556-infra`](https://github.com/code-sena/test-manager-library-3145556-infra) | Contenedores, red, volúmenes y motor de base de datos vacío |
| [`test-manager-library-3145556-tool`](https://github.com/code-sena/test-manager-library-3145556-tool) | Utilidades: sembrador de datos de demostración |

## Cómo levantar la API

```bash
cp .env.example .env          # completar DB_PASSWORD, JWT_SECRET, ADMIN_USERNAME, ADMIN_PASSWORD
docker build -t library-api .
docker run --rm --env-file .env -p 8000:8000 library-api
```

Decisiones de tecnología en [`spec-laravel/`](https://github.com/menticio/test-manager-library-3145556-docs/tree/main/spec-laravel)
del repositorio `docs` ([ADR-005](https://github.com/menticio/test-manager-library-3145556-docs/blob/main/spec-laravel/adr/adr-005-traduccion-a-react-y-laravel.md)).

### Evidencia T-01

```bash
curl http://localhost:8000/api/v1/health      # {"status":"ok"}
docker run --rm --env-file .env library-api vendor/bin/pest
```

La única ruta registrada es `GET /api/v1/health`: se quitaron las rutas por defecto de Laravel que no están en
`api-contract.md` (`/`, `/up` y `/storage/{path}`), y una prueba lo vigila.

### Evidencia T-02 (migración inicial)

Con el motor de `infra` levantado (red `library-net`):

```bash
docker run --rm --network library-net --env-file .env library-api php artisan migrate --force
docker exec -i library-db psql -U library -d library < database/verification/data-model-section-10.sql
```

- [`database/verification/data-model-section-10.sql`](database/verification/data-model-section-10.sql) son las consultas de
  `data-model.md` §10 copiadas tal cual. Devuelven las 30 columnas de §3 **sin ningún `DEFAULT`**, las PK/FK/CHECK de §4 y §5 y los índices de §4.
- Esquema `library` con **seis tablas en singular**. La tabla de control de Laravel (`migrations`) vive en `public`, fuera del modelo.
- Solo el `CHECK` marcado **motor** (`ck_book_available_range`). `ck_book_total_positive` y `ck_user_role` están **pendientes (T-18)** y no se crean aquí.
- `loan_item.category_name` sin FK a propósito (ADR-004). ISBN único solo entre activos, con índice parcial (ADR-003).
- Las cinco categorías se siembran con los identificadores fijos de §9. El admin **no** se siembra (lo crea el arranque, T-17).
- La migración tiene reversa: `php artisan migrate:rollback` deja el esquema vacío.

### Evidencia T-17 (arranque con la base vacía)

`docker/start.sh` es el arranque: `migrate` (el esquema es de las migraciones, ADR-001) → `library:provision-admin`
(admin desde `ADMIN_USERNAME` / `ADMIN_PASSWORD`, D-10, idempotente) → servidor.

```bash
php artisan migrate:rollback --force                       # esquema library vacío
sh docker/start.sh                                         # migra, crea el admin y sirve
curl -X POST localhost:8000/api/v1/auth/login -H 'Content-Type: application/json' \
     -d '{"username":"<ADMIN_USERNAME>","password":"<ADMIN_PASSWORD>"}'   # → role "admin"
```

La clave del admin nunca se imprime ni aparece en los logs. Pruebas: `tests/Application/ProvisionAdminTest.php` y
`tests/Integration/StartupTest.php`.

## Pruebas

```bash
vendor/bin/pest     # Domain, Application (fakes), Integration y Contract (PostgreSQL real), Feature
```

| Nivel | Carpeta | Contra qué |
|---|---|---|
| Dominio | `tests/Domain` | nada: objetos puros |
| Aplicación | `tests/Application` | fakes de los puertos (`tests/Fakes`) |
| Integración | `tests/Integration` | PostgreSQL real de `infra` (cada prueba se revierte) |
| Contrato | `tests/Contract` | la API con todas las rutas y códigos de `api-contract.md` |
