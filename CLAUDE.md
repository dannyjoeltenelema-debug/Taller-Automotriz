# CLAUDE.md — Sistema de Taller Automotriz

> Instrucciones permanentes para Claude Code en este repositorio (vive en la raíz: Claude Code lo carga siempre).
> La referencia completa está en `docs/ARQUITECTURA.md`.
> Idioma de trabajo: **español** (código en inglés solo donde Laravel lo exige; nombres de dominio en español: `OrdenTrabajo`, `Vehiculo`, `Cliente`…).

---

## 1. Contexto en 30 segundos

Sistema Laravel para dar seguimiento a vehículos de un taller automotriz **multi-sede** en Quito, Ecuador.

- **Panel interno** con login por **cédula** (roles Spatie `superadmin` y `admin`). Los técnicos no tienen cuenta en v1: son `empleados` con `user_id = null`.
- **Sistema** (solo `superadmin`): auditoría, usuarios y roles, sesiones activas, registros eliminados, estado del sistema y logs.
- **Portal público** sin login: consulta por **placa**, **cédula/RUC** o **QR** → descripción del vehículo, órdenes de servicio e historial de mantenimiento.
- Entrega: **11 de diciembre de 2026**. Prioriza terminar y estabilizar sobre añadir funciones nuevas.

Antes de cualquier tarea no trivial, lee `docs/ARQUITECTURA.md` (capas, carpetas, modelo de datos, flujos, supuestos).

---

## 2. Stack fijado (NO actualizar)

PHP 8.3 · Laravel 13.43.0 · Livewire 4.4.8 · Volt 1.11.2 (solo auth) · Flux 2.21.0 (solo restos de auth) · spatie/laravel-permission 8.5.0 · dompdf 3.1.2 · simple-qrcode 4.2.0 · MySQL 8.4.3 · Tailwind.

- **Nunca** ejecutes `composer update`, `npm update`, `composer require` ni `npm install <paquete>` sin aprobación explícita. Usa `composer install` y `npm ci`.
- No cambies `composer.json`, `composer.lock`, `package.json` ni `package-lock.json` salvo que se pida.
- No borres `config/livewire.php` (evita un 500 en Profile con Livewire 4).

---

## 3. Entorno local (Laragon, Windows)

| | |
|---|---|
| URL | `http://taller-automotriz.test:8080` (Apache) |
| MySQL | puerto **3307** (nunca 3306). BD `taller_automotriz` (desarrollo) y `taller_automotriz_test` (tests en MySQL) |
| Servidor | **No uses `php artisan serve`** |
| TZ / locale | `America/Guayaquil` / `es` |
| Shell | PowerShell |

Si `artisan` apunta a otra BD o dice "No APP_KEY": hay variables de entorno fantasma. Revisa `Get-ChildItem Env:` y limpia con `Remove-Item Env:DB_*,Env:APP_KEY`.

Comandos destructivos (`migrate:fresh`, `migrate:reset`, `db:wipe`, borrar tablas o datos): **pide confirmación antes**, y nunca contra una BD que no sea local. En `taller_automotriz` solo `php artisan migrate` (aditivo).

### Tests
- **Por defecto: SQLite en memoria** (`phpunit.xml`): `php artisan test`. Rápido; excluye los grupos `mysql` y `medicion`.
- **Contra MySQL** (`phpunit.mysql.xml`, BD `taller_automotriz_test` en 3307; crearla si no existe):
  `php vendor/bin/phpunit --configuration=phpunit.mysql.xml`. Incluye el grupo `mysql` (concurrencia real del código OT con procesos en paralelo).
  Alternativa con variables de entorno (PowerShell): `$env:DB_CONNECTION="mysql"; $env:DB_PORT="3307"; $env:DB_DATABASE="taller_automotriz_test"; php artisan test` (no corre el grupo `mysql`; luego `Remove-Item Env:DB_*`).
- **Medición por pantalla** (consultas, ms, KB): `php vendor/bin/phpunit --group=medicion` → `storage/app/medicion/*.md`.
- Antes de terminar: la suite debe pasar en **SQLite y en MySQL**.

---

## 4. Reglas de oro (no negociables)

1. **Cero recargas de página.** Todo el panel y el portal en Livewire. Enlaces internos con `wire:navigate` (salvo PDF/etiquetas con `target="_blank"`). El layout del panel persiste cabecera + menú con `@persist('marco-panel')`. Nada de formularios HTML clásicos con POST y redirect completo (el logout es un `fetch` + `Livewire.navigate`).
2. **Nada quemado.** Valores de negocio en `config/taller.php`, tabla `empresa` (p. ej. `iva_porcentaje`) o catálogos en BD. Textos en `lang/es/*`. Roles como datos (Spatie). Si dudas, va a configuración.
3. **Paleta ERP sobria.** Fondo `#F4F5F7`; superficies blancas con borde 1px `#D1D5DB`; texto `#1F2937`; **único acento `#1E40AF`**. Sin degradados, sombras grandes, emojis, ilustraciones ni look "IA/startup". Sin logo real: placeholder neutro con el nombre de `empresa`. **No uses Flux en el panel**; Tailwind plano.
4. **No tocar Inventario** salvo que la tarea sea explícitamente de Inventario: `app/Livewire/Inventario/`, `app/Models/Inventario/`, `resources/views/livewire/inventario/`, `routes/inventario.php` son del segundo desarrollador. El único punto de integración es `// TODO INVENTARIO` en `app/Livewire/Ordenes/Formulario.php`; no lo implementes por tu cuenta.
5. **No tocar Facturación** (UI ni SRI): está diferida.
6. **MySQL 3307, Apache 8080.** Siempre.
7. **No cambiar versiones del stack.**
8. **Tests en verde (SQLite y MySQL) y consultas contadas** antes de dar una tarea por terminada.
9. **Eliminar = soft delete**, no columnas `activo`. Lo eliminado se restaura desde *Sistema > Registros eliminados* (solo superadmin). Hoy aplica a empresas colaboradoras; otros módulos siguen con `activo` hasta decidirlo.
10. **Todo modelo del panel usa el trait `App\Models\Concerns\Auditable`** (1 INSERT por evento, sin SELECT extra; nunca guarda contraseñas ni tokens). Acciones especiales (bloquear, reabrir OT, exportar…) con `Auditoria::registrar()`.
11. **Sección Sistema solo para superadmin**: rutas en `routes/sistema.php` con `role:superadmin` y los componentes usan el trait `SoloSuperadmin` (defensa en profundidad).

---

## 5. Rendimiento ("como un videojuego")

Aplica siempre, en todo componente que crees o modifiques:

- Eager loading (`with`, `withCount`, `select` de columnas). **Cero N+1.** `Model::preventLazyLoading(! app()->isProduction())` debe estar activo.
- Listados paginados; nunca `->get()` sobre tablas que crecen.
- `wire:key` en todo `@foreach`.
- `#[Computed]` para datos derivados; propiedades públicas pequeñas (IDs, no colecciones).
- Buscadores: `wire:model.live.debounce.300ms`. Formularios: `wire:model` diferido o `.blur`.
- `#[Lazy]` con placeholder para bloques costosos.
- `wire:loading` / `wire:dirty` para feedback inmediato (botón: `wire:loading.attr="disabled"` + "Guardando…"; pie de formulario: "Cambios sin guardar").
- `wire:key` en todo `@foreach`, **incluidos los `<option>`**.
- `Model::preventLazyLoading()` está activo fuera de producción: un N+1 lanza excepción en desarrollo y en tests.
- Alpine para interacciones puramente visuales (pestañas, abrir/cerrar), sin ida al servidor.
- Caché para catálogos y datos de `empresa`, **con invalidación** desde observers al guardar.
- Índices en toda columna usada en `where`, `join` u `order by` frecuente (ver tabla en `docs/ARQUITECTURA.md` §7.4).

### Animaciones (sobrias, de software de gestión)
- Definidas **una sola vez** en `resources/css/movimiento.css` y reutilizadas: `anim-enter/anim-leave` + `anim-from-up`/`anim-from-scale`/`anim-oculto` para `x-transition`; `anim-aparece*` para lo que Livewire monta con `@if`; `.fila-nueva` (evento `fila-nueva` + `data-fila`); `wire:transition` para salidas (modal que se cierra, fila eliminada); `<x-skeleton-tabla>` para listados.
- 120–200 ms (nada > 250 ms, salvo el resaltado de fila nueva ~1 s); ease-out al entrar, ease-in al salir; **solo `opacity` y `transform`**; `prefers-reduced-motion` → solo opacidad. Prohibido: rebotes, glow, brillos, degradados animados, sombras grandes, animaciones infinitas. `tests/Unit/MovimientoCssTest.php` lo vigila.
- Colores: tokens `--color-erp-*` en el `@theme` de `resources/css/app.css` (única fuente de hex). Excepción única: `--c-whatsapp`.

---

## 6. Dónde va cada cosa

| Qué | Dónde |
|---|---|
| Pantallas | `app/Livewire/{Modulo}/` + `resources/views/livewire/{modulo}/` |
| Auditoría | trait `app/Models/Concerns/Auditable.php`, modelo `app/Models/Auditoria.php`, listener `app/Listeners/AuditarAcceso.php` |
| Reglas de negocio reutilizables o transaccionales | `app/Services/` |
| Efectos automáticos al guardar | `app/Observers/` |
| Validaciones ecuatorianas | `app/Rules/CedulaRucEcuador`, `app/Rules/PlacaEcuador` |
| Rutas | `routes/{ordenes,clientes,vehiculos,citas,empleados,publico,sistema,auth}.php` (`web.php` las carga y define `settings/*`). `clientes.php` incluye convenios; `empleados.php` incluye catálogos y datos del taller |
| Parámetros | `config/taller.php` (IVA de respaldo, citas, portal, paginación, código OT, cargos técnico/asesor, transiciones de OT, auditoría, caché) |
| Textos | `lang/es/*` |
| Datos iniciales | `database/seeders/` (Roles, Organizacion, Catalogos, DemoOperacion) |

Patrón: la ruta apunta a un componente Livewire de página; el componente valida y orquesta; la lógica de negocio vive en Services; la vista no consulta la BD.

---

## 7. Lógica que no se rompe

- **Código OT `OT-YYYY-NNNN`**: solo vía `app/Services/GeneradorCodigoOT.php` (prefijo y relleno en `config/taller.php → codigo_ot`). Bloquea primero la fila de `empresa` y luego usa `lockForUpdate`. **No cambies ese orden** (resolvió deadlocks). Sin fila `empresa` lanza una excepción clara.
- **Descuento**: solo vía `app/Services/CalculadorDescuento.php` (% de la empresa colaboradora del cliente; 0 si "No aplica").
- **IVA**: el vigente está en `empresa.iva_porcentaje`. **Cada OT guarda su `iva_porcentaje`** al crearse; al editarla y en su PDF se usa el guardado, nunca el actual. Nunca escribir `0.15` ni `15` en código.
- **Dinero**: `decimal` en BD y cálculo exacto con `App\Support\Dinero` (BCMath; respaldo en centavos enteros). Nunca `float`.
- **Estados de OT**: constantes en `OrdenTrabajo::ESTADOS`; las **transiciones permitidas** en `config/taller.php → ordenes.transiciones`. `entregado` y `cancelado` son finales: solo el superadmin reabre (queda auditado). Entregar con cobro pendiente pide confirmación. Usar `OrdenTrabajo::puedePasarA()` / `estadosElegibles()`.
- **Técnico / asesor de una OT**: cargos permitidos en `config/taller.php → ordenes.cargos_tecnico` / `cargos_asesor`. Un usuario sin empleado vinculado elige el asesor en el formulario.
- **Placa**: se guarda y se busca normalizada (mayúsculas, sin guion). UNIQUE.
- **QR**: la URL usa `vehiculos.qr_token` (UUID), nunca el `id`.
- **Cédula/RUC**: módulo 10; RUC de 13 dígitos terminando en `001`.
- **Citas**: franjas entre 08:00 y 14:00 de `duracion_franja_min` (60), leídas de `config/taller.php → citas`.
- **Portal**: la búsqueda normaliza y valida con `PlacaEcuador` / `CedulaRucEcuador` antes de consultar.
- **Login**: por `users.cedula` + contraseña. Registro público deshabilitado. Un usuario con `bloqueado_at` no entra y pierde la sesión abierta. `settings/*` exige rol del panel.
- **Cargo ≠ rol**: `empleados.cargo` es descriptivo; el acceso lo dan los roles Spatie.
- **Portal público**: no mostrar precios internos, notas internas ni datos de otras personas.

---

## 8. Forma de trabajar

### Antes de escribir código
1. Lee `docs/ARQUITECTURA.md` y los archivos que vas a tocar.
2. Confirma en qué rama estás (`git branch --show-current`). Nunca trabajes directo en `main` ni en `develop`.
3. Para tareas medianas o grandes, presenta primero un **plan corto** (archivos a tocar, cambios en BD, riesgos) y espera aprobación, salvo que el prompt diga explícitamente "modo autónomo".

### Mientras programas
- Cambios mínimos y enfocados en la tarea. No refactorices ni "mejores" cosas fuera del alcance sin pedirlo.
- Sigue el estilo existente del módulo (nombres en español, estructura de componentes).
- Migraciones nuevas: nunca editar una migración ya ejecutada en `develop`; crear una nueva.
- Si algo es ambiguo, **elige la opción más conservadora, déjala anotada como supuesto** en tu resumen y sigue.

### Antes de terminar
1. `php artisan test` (SQLite) **y** `php vendor/bin/phpunit --configuration=phpunit.mysql.xml` (MySQL 3307) en verde. Si fallan tests, re-ejecuta solo los que fallaron mientras corriges. `HumoRutasTest` recorre todas las rutas GET con 4 perfiles: si agregas una ruta, debe cumplir su tabla.
2. Añade o actualiza tests Feature de lo que cambiaste (camino feliz + validación).
3. Revisa el número de consultas de cada pantalla tocada e infórmalo (antes/después si es optimización).
4. Verifica que no quedaron valores quemados, `dd()`, `dump()` ni `console.log`.
5. Si cambiaste algo estructural (tabla, módulo, flujo), **actualiza `docs/ARQUITECTURA.md`**.

### Resumen de entrega (formato)
```
Qué hice:
Archivos tocados:
Migraciones / cambios de BD:
Tests: X pasan / Y fallan
Consultas por pantalla: (pantalla → n)
Supuestos que tomé:
Pendiente / riesgos:
```

---

## 9. Git

- `main` protegida (solo PR desde `develop`). `develop` = integración. `feature/*` desde `develop`.
- Commits pequeños, en español, en imperativo: `Agrega filtro por sede en listado de OT`.
- **No hagas push, merge ni abras PR sin que se te pida.** Nunca `push --force` a `main` ni a `develop`.
- Lock files se commitean. Nunca commitear `.env`.
- Cambios que crucen la zona del otro desarrollador (Inventario ↔ resto): solo por PR y avisando.

---

## 10. Qué NO hacer

- `php artisan serve`, puerto 3306, `composer update`, `npm update`.
- Recargas completas de página, `<form method="POST">` clásico, redirects que no sean de Livewire navegables.
- Flux en el panel, colores fuera de la paleta, logos o iconos de marca inventados.
- Constantes de negocio en código (IVA, horarios, porcentajes, transiciones de OT, tamaños de página). Los estados y cargos sí son constantes de modelo (`ESTADOS`, `CARGOS`) porque el código ramifica por ellos.
- Columnas `activo` nuevas para "borrar": usar soft delete.
- `@livewire:evento` en atributos Alpine dentro de Blade (Blade lo toma como directiva): usar `x-on:livewire:evento`.
- Tocar Inventario o Facturación sin que la tarea lo pida.
- Consultas dentro de vistas Blade o dentro de bucles.
- Comandos destructivos sobre la BD sin confirmación.
- Contraseñas reales en seeders o en el repo.

---

## 11. Prompt de arranque sugerido (para pegar al iniciar una sesión)

```
Lee CLAUDE.md y docs/ARQUITECTURA.md. Luego:
1. Dime en qué rama estoy y el estado de git.
2. Resume en 5 líneas qué entendiste del proyecto y de las reglas de oro.
3. Tarea de hoy: <describir tarea>.
Antes de programar, dame un plan corto (archivos, cambios de BD, riesgos, cómo lo vas a probar) y espera mi aprobación.
```

### Prompt de optimización (fase 3 del traspaso)

```
Optimiza el panel y el portal sin tocar Inventario ni Facturación, en 3 fases:
FASE 1 – Auditoría (no cambies código): por cada pantalla lista número de consultas,
N+1, falta de índices, falta de wire:key/wire:navigate/debounce, propiedades públicas
pesadas, valores quemados y vistas que consultan la BD. Entrega una tabla priorizada.
Espera mi aprobación.
FASE 2 – Aplicar los cambios aprobados, uno por commit.
FASE 3 – Verificar: tabla de consultas antes/después por pantalla, php artisan test en
verde y tests de regresión para código OT, descuento+IVA, placa, cédula/RUC, portal y login.
```
