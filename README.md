# CommerceHub · Multi-Tenant E-Commerce SaaS (WhatsApp Native & POS Partner Network)

Plataforma SaaS de comercio electrónico headless y multi-inquilino de alto rendimiento desarrollada con **Laravel 12 (PHP 8.5)**, **Inertia.js v3 + React 19**, **PostgreSQL 16 (JSONB GIN)**, **Redis** y **Tailwind CSS v4** bajo el sistema de diseño oscuro de alta precisión *Obsidian Kinetic*.

---

## 🚀 Inicio Rápido con Docker

El entorno local está dockerizado en su totalidad. Todo comando de consola y backend debe ejecutarse a través de los contenedores:

```bash
# 1. Levantar servicios (App PHP 8.5, PostgreSQL en puerto 5433, Redis en puerto 6380)
docker compose up -d

# 2. Refrescar base de datos y ejecutar el seeder de demostración (idempotente)
docker compose exec app php artisan migrate:fresh --seed

# 3. Compilar los assets del frontend (Vite / Rolldown)
docker compose exec app npm run build
# O para desarrollo continuo con hot reload:
npm run dev
```

---

## 🔐 Credenciales y Accesos de la Demostración

El seeder principal (`DemoTenantSeeder`) genera de forma predeterminada el inquilino modelo **Kinetic Wear HN** (`slug: kinetic-wear`, Moneda: `HNL`, País: `HN`).

### 1. Vitrina Pública (Storefront) — Sin autenticación requerida
Los compradores finales no requieren crear cuenta ni iniciar sesión para navegar, filtrar ni ordenar por WhatsApp:
- **Catálogo de Productos y Filtros:** [`/t/kinetic-wear/catalog`](http://localhost:8000/t/kinetic-wear/catalog)
- **Detalle de Producto y Variantes:** `/t/kinetic-wear/product/{slug}`
- **Bolsa y Checkout por WhatsApp:** [`/t/kinetic-wear/checkout`](http://localhost:8000/t/kinetic-wear/checkout)

---

### 2. Panel Administrativo del Tenant (Tenant Admin)
Acceso administrativo protegido por tokens Sanctum multi-tenant con granularidad de permisos (`tenant:admin` vs `tenant:staff`).

- **Pantalla de Inicio de Sesión Admin:** [`/t/kinetic-wear/admin/login`](http://localhost:8000/t/kinetic-wear/admin/login)

| Rol | Correo Electrónico | Contraseña | Permisos y Habilidades |
| :--- | :--- | :--- | :--- |
| **Owner (Administrador Principal)** | `admin@kineticwear.hn` | `password` | `tenant:admin` (Acceso completo a esquema, configuración, finanzas y liquidación) |
| **Staff (Operador de Tienda)** | `operaciones@kineticwear.hn` | `password` | `tenant:staff` (Gestión de catálogo, stock y procesamiento de órdenes) |

#### Módulos Administrativos Disponibles:
- **Catálogo y Variantes:** [`/t/kinetic-wear/admin/catalog`](http://localhost:8000/t/kinetic-wear/admin/catalog) (Búsqueda por SKU, filtros de categoría, estado, inventario, atributos dinámicos paginados y ajuste atómico de stock)
- **Centro de Órdenes WhatsApp:** [`/t/kinetic-wear/admin/orders`](http://localhost:8000/t/kinetic-wear/admin/orders) (Aprobación de pagos, transición de estados y enlace directo a WhatsApp)
- **Constructor de Esquema y Metadatos:** [`/t/kinetic-wear/admin/schema`](http://localhost:8000/t/kinetic-wear/admin/schema) (Diseño de `metadata_schema`, facetas, branding, zonas de envío y cuentas bancarias estructuradas)
- **Marketing y Cupones:** [`/t/kinetic-wear/admin/marketing`](http://localhost:8000/t/kinetic-wear/admin/marketing) (Promociones automáticas y reglas de cupones)
- **Red de Afiliados y Comisiones:** [`/t/kinetic-wear/admin/affiliates`](http://localhost:8000/t/kinetic-wear/admin/affiliates) (Liquidación de comisiones retenidas/aprobadas con soporte de comprobantes)
- **Comercios Aliados:** [`/t/kinetic-wear/admin/partners`](http://localhost:8000/t/kinetic-wear/admin/partners) (Red cruzada de vouchers POS)

---

### 3. Portal POS para Comercios Aliados (Partner Portal)
Terminal móvil ligera de punto de venta para validar y redimir vouchers de beneficios emitidos a clientes del tenant:

- **Pantalla de Inicio de Sesión POS:** [`/t/kinetic-wear/partner-portal/login`](http://localhost:8000/t/kinetic-wear/partner-portal/login)
- **Terminal de Escáner y Canje:** [`/t/kinetic-wear/partner-portal/scanner`](http://localhost:8000/t/kinetic-wear/partner-portal/scanner)

| Comercio Aliado | Correo Electrónico POS | Contraseña | Beneficio Configurado |
| :--- | :--- | :--- | :--- |
| **Espresso Lab Specialty Coffee** | `caja@espressolab.hn` | `password` | *Cold Brew Nitro de Cortesía o 25% OFF en Barra* |
| **Altitude Functional Studio** | `recepcion@altitude.hn` | `password` | *Pase Recovery Lounge + Batido Proteico Gratis* |

> [!TIP]
> **Voucher activo de prueba para canjear en Espresso Lab:**
> Introduce el código `RWD-KINE-CAFE-2026` en el escáner del Partner Portal para verificar su validación y canje atómico con idempotencia.

---

### 4. Cupones de Descuento de Prueba
Puedes aplicar estos códigos en el carrito de compras ([`/t/kinetic-wear/checkout`](http://localhost:8000/t/kinetic-wear/checkout)):
- **`KINETIC10`**: 10% de descuento porcentual en compras mayores a L500. Vinculado al afiliado Carlos Rivera (*TGU Run Club*).
- **`TRAILVIP`**: L250 de descuento directo en compras mayores a L1,800. Vinculado a la afiliada Sofía Valladares (*Trail Honduras*).

---

## ⚡ Pruebas de Estrés y Latencia (Miles de Productos)

El sistema incluye un seeder secundario independiente de inserción masiva (`DemoCatalogStressSeeder`) diseñado para evaluar filtros complejos, índices GIN de PostgreSQL y latencia con miles de productos y variantes:

```bash
# Ejecutar el seeder de estrés directamente:
docker compose exec app php artisan db:seed --class=DemoCatalogStressSeeder

# O activarlo mediante variable de entorno:
docker compose exec -e SEED_STRESS_CATALOG=true app php artisan db:seed
```

- **Rendimiento:** Genera e inserta **1,500 productos y 4,500 variantes** con atributos dinámicos en aproximadamente **1.2 segundos**.
- **Idempotencia:** Purga automáticamente los registros previos con prefijo `stress-%` antes de regenerar el catálogo.
- **Configuración:** Puedes ajustar el volumen deseado estableciendo `CATALOG_STRESS_COUNT=3000` en `.env`.

---

## 🧪 Pruebas Automatizadas y Aseguramiento de Calidad

```bash
# 1. Ejecutar la suite completa de pruebas Pest
docker compose exec app php artisan test --compact

# 2. Análisis estático con PHPStan (Nivel 6)
docker compose exec app vendor/bin/phpstan analyse --memory-limit=512M

# 3. Formateo y estándares de código PHP con Pint
docker compose exec app vendor/bin/pint --format agent

# 4. Verificación estricta de tipos en TypeScript
npm run types:check
```
