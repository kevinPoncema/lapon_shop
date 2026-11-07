# Especificación de Requisitos de Software (SRS)
**Proyecto:** Plataforma SaaS Multi-Tenant de Comercio Electrónico Asistido (*Schema-Driven E-Commerce*)  
**Stack Objetivo:** Laravel (PHP), PostgreSQL (`JSONB` + Índices GIN), Redis, Cloudflare Edge  
**Versión:** 1.0.0  

---

## 1. Propósito y Alcance del Sistema

El presente documento define los requisitos funcionales y no funcionales para el desarrollo de una plataforma SaaS multi-inquilino (*multi-tenant*) de comercio electrónico asistido. 

El sistema posee un **núcleo agnóstico al rubro comercial**: el código fuente no acopla reglas específicas de ninguna industria (como calzado, ropa o accesorios). En su lugar, implementa un motor de metadatos y filtros dinámicos gobernados por configuración (`tenant_configs`), permitiendo que cada tienda defina las propiedades de sus productos, las dimensiones que particionan su inventario (variantes) y los criterios de búsqueda pública. La conversión de ventas se formaliza mediante mensajería estructurada hacia WhatsApp para liquidación por transferencia bancaria, integrando motores nativos de descuentos, comisiones de afiliados y cupones cruzados para comercios aliados.

---

## 2. Arquitectura General del Sistema

1. **Capa de Enrutamiento y Edge:** Terminación SSL/TLS automatizada y resolución de dominios personalizados (`CNAME`) o subdominios hacia el backend.
2. **Capa de Aplicación (Laravel):** Monolito modular *stateless*. Aislamiento de inquilinos mediante *Middlewares* y *Global Scopes* de Eloquent.
3. **Capa de Caché y Colas (Redis):** Resolución rápida de configuración de inquilinos (`Host` $\rightarrow$ `tenant_id`), caché de catálogo principal y procesamiento asíncrono de correos/vouchers.
4. **Capa de Persistencia (PostgreSQL):** Modelo híbrido que combina integridad referencial transaccional (ACID) para inventario, órdenes y comisiones, con almacenamiento semiestructurado indexado (`JSONB` + `GIN`) para atributos y filtros dinámicos.

---

## 3. Requisitos Funcionales (RF)

### Módulo 1: Gestión Multi-Tenant, Dominios e Identidad Visual

#### RF-01: Gestión de Entidad Inquilino (`tenants`)
* **Descripción:** El sistema debe almacenar la identidad base de cada comercio en una tabla maestra `tenants` que contenga únicamente datos estructurales: `id` (UUID), `name`, `slug` (subdominio), `custom_domain` y estado `is_active`.
* **Criterios de Aceptación:**
  * Todas las tablas operativas del sistema deben incluir la clave foránea `tenant_id`.
  * Los modelos de Eloquent deben implementar un *Global Scope* (`BelongsToTenant`) que inyecte automáticamente la cláusula `WHERE tenant_id = ?` en toda consulta de lectura y asigne el `tenant_id` activo en cada inserción.

#### RF-02: Resolución Dinámica de Dominio y Caché de Contexto
* **Descripción:** El sistema debe identificar a qué tienda pertenece una petición HTTP entrante leyendo la cabecera `Host` (ya sea desde `mitienda.com` o `mitienda.plataforma.com`).
* **Criterios de Aceptación:**
  * Un middleware debe buscar la configuración del inquilino en Redis bajo la llave `tenant:host:{host}`.
  * Si no existe en caché, debe consultar PostgreSQL haciendo `JOIN` entre `tenants` y `tenant_configs`, almacenar el resultado en Redis y registrar la instancia del tenant en el contenedor de servicio de Laravel para el ciclo de vida de la petición.

#### RF-03: Configuración de Marca y Redes Sociales (`tenant_configs`)
* **Descripción:** Cada inquilino debe contar con un registro de configuración 1:1 en `tenant_configs` para definir su identidad visual y canales de atención sin requerir despliegues de código.
* **Criterios de Aceptación:**
  * Soporte para configurar: color primario, color secundario, color de acento, URL de logotipo, banner principal y enlaces a redes sociales (WhatsApp de ventas, Instagram, Facebook, TikTok).
  * El frontend debe inyectar estos valores como variables CSS (`:root`) al cargar la tienda.

---

### Módulo 2: Motor de Metadatos y Configuración de Filtros

#### RF-04: Definición Dinámica de Metadatos de Producto (`metadata_schema`)
* **Descripción:** El administrador del inquilino debe poder definir qué atributos personalizados existen en su catálogo dentro del campo `metadata_schema` de `tenant_configs`.
* **Criterios de Aceptación:**
  * Cada definición de metadato debe especificar:
    * `key`: Identificador único en formato *snake_case* (ej. `size_standard`, `material`).
    * `label`: Nombre legible para humanos (ej. "Estándar de Medida", "Material").
    * `type`: Tipo de dato permitido (`text`, `number`, `boolean`, `select`).
    * `options`: Arreglo de valores permitidos (obligatorio cuando `type` es `select`).
    * `required`: Booleano que define si el campo es obligatorio al crear o actualizar un producto.
    * `affects_stock`: Booleano que determina si el metadato particiona el inventario en variantes.
  * El validador debe prohibir el uso de claves reservadas del sistema (`id`, `name`, `description`, `price`, `stock`, `sku`).

#### RF-05: Partición de Inventario según `affects_stock`
* **Descripción:** El sistema debe dividir el almacenamiento de los metadatos según su impacto en el inventario físico:
  1. **Metadatos Descriptivos (`affects_stock = false`):** Propiedades generales del producto (ej. marca, género, estilo, resistente al agua). Se guardan en `products.metadata`.
  2. **Metadatos Dimensionales (`affects_stock = true`):** Propiedades que dividen el stock (ej. talla, color, capacidad). Se guardan en `product_variants.metadata`.
* **Criterios de Aceptación:**
  * Al crear o editar un producto, el backend debe validar dinámicamente el payload contra el `metadata_schema` vigente del inquilino.
  * Si un producto maneja prendas o piezas únicas (sin múltiples tallas o variantes visibles), el sistema debe crear internamente una única fila en `product_variants` con el stock asignado para mantener uniforme toda la lógica transaccional del carrito.

#### RF-06: Configuración y Validación Cruzada de Filtros (`filter_config`)
* **Descripción:** El inquilino debe poder definir qué filtros se muestran en su tienda pública configurando el arreglo `filter_config` en `tenant_configs`.
* **Criterios de Aceptación:**
  * Soporte para filtros de sistema (`name` por coincidencia de texto y `price` por rango numérico) y filtros basados en metadatos (`type: "metadata"`).
  * **Regla de integridad estricta:** El sistema debe impedir que se guarde en `filter_config` cualquier filtro de tipo `metadata` cuya `key` no exista previamente en el `metadata_schema` del mismo inquilino.
  * Si un administrador elimina un metadato de `metadata_schema`, el sistema debe remover en cascada ese atributo de `filter_config` y limpiar la caché en Redis.

---

### Módulo 3: Catálogo, Variantes y Búsqueda Facetada

#### RF-07: Gestión de Productos Base
* **Descripción:** CRUD de productos con los campos universales mínimos.
* **Criterios de Aceptación:**
  * Campos base en la tabla `products`: `id` (UUID), `tenant_id`, `name` (requerido), `description` (opcional), `base_price` (requerido, $> 0$), `metadata` (`JSONB`), `is_active` (booleano).
  * Al crear o actualizar un producto, se deben invalidar las llaves de caché del catálogo principal del inquilino en Redis.

#### RF-08: Gestión de Variantes y Stock
* **Descripción:** Control de inventario exacto a nivel de variante vendible.
* **Criterios de Aceptación:**
  * Campos en la tabla `product_variants`: `id` (UUID), `tenant_id`, `product_id`, `sku` (opcional, único por tenant), `price` (nullable; si es nulo hereda `base_price`), `stock` (entero $\ge 0$), `metadata` (`JSONB`).
  * La interfaz pública debe mostrar el estado de disponibilidad y bloquear la selección de variantes con `stock` $= 0$.

#### RF-09: Motor de Búsqueda y Filtrado sobre JSONB
* **Descripción:** Consulta pública de productos aplicando simultáneamente filtros de sistema y filtros de metadatos.
* **Criterios de Aceptación:**
  * El endpoint de catálogo debe aceptar filtros por nombre (`ILIKE`), rango de precio (`BETWEEN`) y un objeto dinámico de atributos (`?attr[key]=value`).
  * El motor debe clasificar las llaves recibidas en `attr`: aquellas con `affects_stock = false` se filtran sobre `products.metadata` y aquellas con `affects_stock = true` se filtran sobre `product_variants.metadata` verificando que `stock` $> 0$.
  * Las consultas sobre JSONB deben ejecutarse mediante los métodos nativos de Eloquent (`whereJsonContains` / operador `@>`) respaldados por índices GIN.

---

### Módulo 4: Carrito de Compras y Checkout vía WhatsApp

#### RF-10: Gestión del Carrito de Compras
* **Descripción:** Agrupación de variantes seleccionadas por el comprador antes de emitir la orden.
* **Criterios de Aceptación:**
  * Cada línea del carrito referencia un `variant_id`, cantidad solicitada y resumen legible de sus metadatos dimensionales (ej. `"Talla: 9.5 | Estándar: US"`).
  * El backend debe verificar la disponibilidad de stock antes de proceder a la generación de la orden.

#### RF-11: Datos de Despacho y Ubicación Geográfica
* **Descripción:** Captura de información del comprador sin obligar a la creación previa de una cuenta de usuario.
* **Criterios de Aceptación:**
  * Formulario con campos obligatorios: Nombre completo, teléfono de contacto, correo electrónico (para envío de vouchers/recibos), dirección detallada y selector de **Departamento / Provincia** para cálculo o coordinación de envío.

#### RF-12: Generación de Orden y Redirección a WhatsApp
* **Descripción:** Orquestación del pedido para cierre asistido y pago mediante transferencia bancaria.
* **Criterios de Aceptación:**
  * Al confirmar el pedido, el sistema registra la orden en base de datos en estado `PENDIENTE_PAGO` con un folio único (`#ORD-XXXX`) y guarda una instantánea (*snapshot*) de los precios y metadatos de las variantes compradas.
  * El sistema retorna una URL hacia la API de WhatsApp (`https://wa.me/{telefono_tienda}?text={mensaje}`) con el desglose completo del pedido, descuentos aplicados, departamento de destino y total a transferir.

---

### Módulo 5: Motor de Descuentos y Promociones

#### RF-13: Promociones Automáticas por Condición
* **Descripción:** Aplicación automática de rebajas en el carrito según reglas configuradas por el inquilino.
* **Criterios de Aceptación:**
  * Soporte para descuentos porcentuales (`PERCENTAGE`) o de monto fijo (`FIXED_AMOUNT`).
  * Evaluación de condiciones almacenadas en `JSONB`: cantidad mínima de artículos en el carrito (`min_items`) o subtotal mínimo (`min_amount`).

#### RF-14: Cupones de Descuento Temporales y por Límite de Uso
* **Descripción:** Generación de códigos promocionales redimibles manualmente por el comprador en el checkout.
* **Criterios de Aceptación:**
  * Validación de vigencia temporal (`starts_at`, `ends_at`), estado activo y límite máximo de redenciones globales (`max_uses` $>$ `used_count`).
  * Regla de exclusión: un cupón manual no se apila sobre promociones automáticas previas salvo que la promoción tenga habilitado el indicador `allow_stacking: true`.

---

### Módulo 6: Micro-Afiliados y Comisiones (Inbound)

#### RF-15: Vinculación de Cupones a Colaboradores Externos
* **Descripción:** El inquilino puede registrar colaboradores/afiliados y asignarles códigos de descuento específicos.
* **Criterios de Aceptación:**
  * Cada cupón vinculado a un afiliado define el beneficio para el comprador (descuento) y la regla de comisión para el colaborador (porcentaje sobre venta neta o monto fijo).

#### RF-16: Liquidación de Comisiones Condicionada al Pago
* **Descripción:** Registro y control contable de las comisiones generadas por órdenes referidas.
* **Criterios de Aceptación:**
  * Cuando se genera la orden vía WhatsApp, la comisión se registra en estado `RETENIDA`.
  * Únicamente cuando el administrador del comercio verifica la transferencia bancaria y cambia el estado de la orden a `COMPLETADA`, la comisión pasa a estado `APROBADA` y suma al saldo liquidable del colaborador.
  * Si la orden pasa a `CANCELADA`, la comisión se anula automáticamente.

---

### Módulo 7: Recompensas Cruzadas con Comercios Aliados (Outbound)

#### RF-17: Emisión de Vouchers de Aliados por Volumen de Compra
* **Descripción:** Generación de un cupón de regalo para ser canjeado en un comercio aliado externo cuando el cliente supera un umbral de compra configurado (por cantidad de artículos o monto total).
* **Criterios de Aceptación:**
  * Al marcarse como `COMPLETADA` una orden que cumple la condición, el sistema genera un registro en `outbound_rewards` con un código de verificación único, asignado a un comercio aliado (`partner_store_id`).

#### RF-18: Envío de Voucher con Imagen por Correo Electrónico
* **Descripción:** Despacho automatizado del cupón al correo del comprador.
* **Criterios de Aceptación:**
  * Un *Job* en segundo plano (Laravel Queue con Redis) genera la representación visual del cupón (imagen o plantilla renderizada con el código de verificación y fecha de expiración) y la envía por correo electrónico al cliente.

#### RF-19: Portal de Validación para Comercios Aliados
* **Descripción:** Interfaz protegida para que el comercio aliado verifique y redima los cupones presentados por los clientes.
* **Criterios de Aceptación:**
  * El colaborador externo ingresa el código de verificación en su vista de validación.
  * El sistema informa si el código es válido, a qué beneficio corresponde y permite marcarlo como `CANJEADO`, registrando la fecha y hora exacta e impidiendo cualquier uso posterior.

---

## 4. Requisitos No Funcionales (RNF)

### RNF-01: Rendimiento y Estrategia de Caché
* **Caché de Portada y Configuración:** La configuración del inquilino (`tenant_configs`) y las primeras páginas del catálogo sin filtros deben servirse desde Redis con un tiempo de respuesta de backend $\le 15\text{ ms}$.
* **Rendimiento de Filtrado JSONB:** Las consultas con filtros dinámicos deben ejecutarse directamente en PostgreSQL utilizando índices compuestos GIN (`jsonb_path_ops`), manteniendo tiempos de ejecución de consulta $\le 50\text{ ms}$ para catálogos de hasta $10,000$ productos por tienda.

### RNF-02: Seguridad y Aislamiento de Datos
* **Aislamiento Estricto:** Queda prohibida cualquier consulta SQL cruda que omita el filtro `tenant_id`. Las pruebas automatizadas deben verificar que un token de administración del Inquilino A reciba un error `404` o `403` al intentar operar sobre recursos del Inquilino B.
* **Autenticación:** Uso de Laravel Sanctum para proteger las rutas administrativas y el portal de aliados comerciales, utilizando cookies seguras `HttpOnly` o tokens con expiración definida.

### RNF-03: Concurrencia e Integridad Transaccional
* **Control de Stock Atómico:** La confirmación de órdenes y el descuento de inventario deben ejecutarse dentro de transacciones `DB::transaction()` utilizando bloqueo pesimista (`lockForUpdate()`) sobre las filas de `product_variants` afectadas, evitando sobreventas (*race conditions*).
* **Consistencia Referencial:** No se permite eliminar físicamente (`HARD DELETE`) variantes ni órdenes que tengan historial transaccional o comisiones asociadas; se debe utilizar borrado lógico (`SoftDeletes`) o desactivación (`is_active = false`).

### RNF-04: Procesamiento Asíncrono
* La generación de imágenes de cupones y el envío de correos electrónicos nunca deben ejecutarse de forma síncrona durante la petición HTTP del usuario; deben despacharse obligatoriamente a través de colas en Redis (`ShouldQueue`).

### RNF-05: Mantenibilidad y Diseño de Interfaz (*Schema-Driven UI*)
* El frontend administrativo y la barra de filtros pública deben construirse mediante componentes dinámicos que interpreten `metadata_schema` y `filter_config`. Agregar un nuevo atributo o filtro desde el panel de un inquilino no debe requerir cambios en el código fuente ni en el esquema de tablas SQL.

---

## 5. Especificación de Estructuras JSON (`tenant_configs`)

### 5.1. Ejemplo de `metadata_schema` (Caso: Tienda de Calzado e Indumentaria)
```json
[
  {
    "key": "garment_category",
    "label": "Tipo de Artículo",
    "type": "select",
    "options": ["Calzado", "Camiseta Deportiva", "Accesorios"],
    "required": true,
    "affects_stock": false
  },
  {
    "key": "gender",
    "label": "Género / Sección",
    "type": "select",
    "options": ["Hombre", "Mujer", "Unisex", "Niños"],
    "required": true,
    "affects_stock": false
  },
  {
    "key": "size_standard",
    "label": "Estándar de Medida",
    "type": "select",
    "options": ["US", "UK", "EUR", "CM", "Estándar Textil"],
    "required": true,
    "affects_stock": true
  },
  {
    "key": "size",
    "label": "Talla",
    "type": "text",
    "required": true,
    "affects_stock": true
  },
  {
    "key": "jersey_version",
    "label": "Versión de Camiseta",
    "type": "select",
    "options": ["N/A", "Versión Fan", "Versión Jugador"],
    "required": false,
    "affects_stock": true
  },
  {
    "key": "team_scope",
    "label": "Ámbito del Equipo",
    "type": "select",
    "options": ["N/A", "Nacional", "Internacional"],
    "required": false,
    "affects_stock": false
  }
]
```

### 5.2. Ejemplo de `filter_config` Validado
```json
[
  { "key": "name", "type": "system", "label": "Buscar producto" },
  { "key": "price", "type": "system", "label": "Rango de Precio" },
  { "key": "garment_category", "type": "metadata", "label": "Categoría" },
  { "key": "gender", "type": "metadata", "label": "Género / Niños" },
  { "key": "size_standard", "type": "metadata", "label": "Estándar de Talla" },
  { "key": "size", "type": "metadata", "label": "Talla" },
  { "key": "jersey_version", "type": "metadata", "label": "Versión (Fan / Jugador)" },
  { "key": "team_scope", "type": "metadata", "label": "Equipo (Nacional / Internacional)" }
]
```

---

## 6. Esquema Base de Tablas en PostgreSQL

| Tabla | Clave Primaria | Índices Críticos | Descripción |
| :--- | :--- | :--- | :--- |
| `tenants` | `id` (UUID) | `UNIQUE(slug)`, `UNIQUE(custom_domain)` | Registro maestro de tiendas. |
| `tenant_configs` | `tenant_id` (UUID) | `PK(tenant_id)` | Configuración visual, `metadata_schema` y `filter_config`. |
| `users` | `id` (UUID) | `INDEX(tenant_id, email)` | Administradores de tienda y usuarios de comercios aliados. |
| `products` | `id` (UUID) | `GIN(tenant_id, metadata jsonb_path_ops)` | Catálogo base y metadatos descriptivos (`affects_stock = false`). |
| `product_variants` | `id` (UUID) | `GIN(tenant_id, metadata jsonb_path_ops)` | Inventario real y metadatos dimensionales (`affects_stock = true`). |
| `promotions` | `id` (UUID) | `INDEX(tenant_id, is_active)` | Reglas de descuento automáticas o por cupón y sus `conditions`. |
| `affiliates` | `id` (UUID) | `INDEX(tenant_id)` | Colaboradores externos que reciben comisión por ventas referidas. |
| `coupons` | `id` (UUID) | `UNIQUE(tenant_id, code)` | Códigos de descuento ligados a una promoción y opcionalmente a un afiliado. |
| `orders` | `id` (UUID) | `INDEX(tenant_id, status, created_at)` | Cabecera del pedido, datos del comprador, departamento y totales. |
| `order_items` | `id` (UUID) | `INDEX(order_id)` | Líneas del pedido con snapshot de precio y metadatos de la variante. |
| `commissions` | `id` (UUID) | `INDEX(tenant_id, affiliate_id, status)` | Registro de comisiones (`RETENIDA`, `APROBADA`, `PAGADA`). |
| `partner_stores` | `id` (UUID) | `INDEX(tenant_id)` | Comercios aliados receptores de cupones cruzados. |
| `outbound_rewards` | `id` (UUID) | `UNIQUE(tenant_id, verification_code)` | Vouchers generados por volumen de compra para canje externo. |