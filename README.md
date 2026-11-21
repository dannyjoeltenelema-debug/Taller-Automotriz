# Taller Automotriz

Sistema de gestión para taller automotriz: órdenes de trabajo, vehículos (con QR), clientes, citas, empleados y portal público. El módulo de **Inventario** está en construcción (ver más abajo).

**Stack:** Laravel 13 · Livewire 4 (starter kit oficial, Volt + Flux) · MySQL · Vite.
**Requisitos:** PHP 8.3+, Composer, Node.js + npm, MySQL (en Laragon corre en el puerto **3307**).
Zona horaria: `America/Guayaquil` · Idioma: español.

---

## Instalación (máquina nueva)

```bash
git clone <repo> Taller-Automotriz
cd Taller-Automotriz
composer install
npm install
copy .env.example .env   # (cp en Linux/Mac)
php artisan key:generate
# crear la base MySQL 'taller_automotriz' en el puerto 3307
php artisan migrate:fresh --seed
npm run build
```

El `.env.example` ya trae la configuración de base de datos (MySQL, `127.0.0.1:3307`, base `taller_automotriz`, usuario `root` sin contraseña, como en Laragon). Si tu MySQL usa otro puerto o tiene contraseña, ajústalo en tu `.env`. El `.env` nunca se sube a git.

**Acceso en Laragon:**

- `http://taller-automotriz.test:8080`
- o `http://localhost:8080/Taller-Automotriz/public`

Para desarrollo con recarga en caliente usa `npm run dev` en lugar de `npm run build`.

## Tests

```bash
php artisan test
```

Los tests usan SQLite en memoria (ver `phpunit.xml`), así que no tocan tu base MySQL.

## Credenciales de prueba

Las crea el seeder (`php artisan migrate:fresh --seed`):

El inicio de sesión es por **cédula** (campo "Usuario"), no por correo.

| Rol         | Usuario (cédula) | Contraseña   | Nombre                 |
|-------------|------------------|--------------|------------------------|
| Superadmin  | `1729057412`     | `1729057412` | Super Administrador 1  |
| Superadmin  | `1721708608`     | `1721708608` | Super Administrador 2  |
| Admin       | `1710000009`     | `1710000009` | María Fernanda López (correo `admin@taller.ec`) |

> ⚠️ **Solo para desarrollo:** usar la cédula como contraseña es inseguro. En producción, cambie estas contraseñas por contraseñas fuertes (o no ejecute este seeder) antes de publicar el sistema.
>
> La recuperación de contraseña por correo solo funciona para cuentas que tengan un correo registrado.

> El registro público está deshabilitado: las cuentas las crea el administrador desde el panel.

---

## División de trabajo y handoff de Inventario

**El módulo de Inventario es tuyo.** Trabaja en la rama `feature/inventario`, sacada de `develop`:

```bash
git checkout develop
git pull
git checkout -b feature/inventario
```

### Lo que ya existe

- **Tablas (migraciones ya creadas):** `categorias_producto`, `proveedores`, `productos`, `producto_proveedor`, `inventarios`, `movimientos_inventario`.
- **Modelos:** `app/Models/Inventario/` (`CategoriaProducto`, `Proveedor`, `Producto`, `Inventario`, `MovimientoInventario`).

### Dónde va tu código

- **Rutas:** `routes/inventario.php` (hoy vacío, ya incluido desde `routes/web.php`). **No edites los otros archivos de rutas.**
- **Componentes Livewire:** `app/Livewire/Inventario/`
- **Vistas:** `resources/views/livewire/inventario/`

### Integración con Órdenes de Trabajo

En `app/Livewire/Ordenes/Formulario.php` está el comentario `// TODO INVENTARIO`. Ahí se conectan las líneas de tipo `producto`, que deben **descontar stock** y **generar un movimiento de salida** en `movimientos_inventario` (con `orden_id` apuntando a la OT).

El formulario de OT ya respeta y suma las líneas `producto` en los totales, así que tu trabajo no lo va a pisar.

### Menú

En `resources/views/components/layouts/panel.blade.php`, el ítem "Inventario" está deshabilitado con la etiqueta "Próximamente". Actívalo cuando tu módulo esté listo.

### Reglas de trabajo

- Bajá `develop` a diario (`git pull origin develop` sobre tu rama).
- PRs chicos y frecuentes hacia `develop`.
- **Nunca** hagas push directo a `main` ni a `develop`.
