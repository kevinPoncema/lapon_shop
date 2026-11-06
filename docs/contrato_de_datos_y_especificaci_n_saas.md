# Especificación del Contrato de Datos (Data Contract Specification)
**Proyecto:** Plataforma SaaS Multi-Tenant de Comercio Electrónico Asistido (Schema-Driven)  
**Motor de Persistencia:** PostgreSQL 16+ / Redis 7+  
**ORM Objetivo:** Laravel 11+ (Eloquent)

## 1. Convenciones Globales y Tipos Base

El intercambio de información entre la base de datos, el backend en Laravel y los clientes consumidores (Storefront, Admin Panel y Partner Portal) se rige por las siguientes reglas invariables:

* **Identificadores Primarios y Foráneos:** Todos los identificadores utilizan formato UUID versión 4 o versión 7 de 36 caracteres en minúsculas.
* **Aislamiento Multi-Tenant:** Toda tabla operativa incluye la columna `tenant_id`. A nivel de API pública, el contexto del inquilino se inyecta mediante la cabecera HTTP `X-Tenant-Slug`.
* **Valores Monetarios:** En base de datos se almacenan como `DECIMAL(12,2)`. En payloads JSON se serializan como números de punto flotante con dos decimales exactos y nunca como cadenas de texto.
* **Fechas y Marcas de Tiempo:** Almacenadas en UTC bajo el tipo `TIMESTAMP WITH TIME ZONE` y serializadas en la API bajo el estándar ISO-8601 (`YYYY-MM-DDTHH:mm:ssZ`).
* **Claves Reservadas del Sistema:** El motor dinámico rechaza en `metadata_schema` el uso de los identificadores `id`, `tenant_id`, `name`, `slug`, `price`, `base_price`, `stock`, `sku`, `category`, `category_id` y `metadata`.

---

## 2. Diccionario de Datos Relacional (PostgreSQL)

### 2.1. Tabla `tenants`
Almacena la entidad maestra de cada comercio suscrito a la plataforma.

| Columna | Tipo SQL | Nulo | Restricción | Descripción |
|---|---|---|---|---|
| id | UUID | No | PK | Identificador único del inquilino |
| name | VARCHAR(150) | No | Ninguna | Razón social o nombre comercial |
| slug | VARCHAR(80) | No | UNIQUE | Identificador en URL y cabeceras |
| country_code | CHAR(2) | No | ISO 3166-1 | País operativo del comercio |
| currency_code | CHAR(3) | No | ISO 4217 | Moneda base de las transacciones |
| is_active | BOOLEAN | No | DEFAULT true | Estado operativo del inquilino |
| created_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de alta del registro |
| updated_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de última modificación |

### 2.2. Tabla `tenant_configs`
Almacena la configuración visual, logística y el esquema gobernador del catálogo en relación 1:1 con `tenants`.

| Columna | Tipo SQL | Nulo | Restricción | Descripción |
|---|---|---|---|---|
| tenant_id | UUID | No | PK, FK tenants | Inquilino propietario de la regla |
| branding | JSONB | No | DEFAULT '{}' | Variables de estilo y logotipos |
| contact_info | JSONB | No | DEFAULT '{}' | WhatsApp oficial y cuentas bancarias |
| shipping_zones | JSONB | No | DEFAULT '[]' | Tarifas por departamento o provincia |
| metadata_schema | JSONB | No | DEFAULT '[]' | Definición de atributos dinámicos |
| filter_config | JSONB | No | DEFAULT '[]' | Filtros activos en barra lateral |
| outbound_reward_rules | JSONB | No | DEFAULT '{}' | Umbrales para emitir vouchers aliados |
| updated_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de última actualización |

### 2.3. Tabla `users`
Operadores administrativos pertenecientes a un inquilino.

| Columna | Tipo SQL | Nulo | Restricción | Descripción |
|---|---|---|---|---|
| id | UUID | No | PK | Identificador del usuario |
| tenant_id | UUID | No | FK tenants | Inquilino al que pertenece |
| name | VARCHAR(120) | No | Ninguna | Nombre completo del operador |
| email | VARCHAR(150) | No | UQ(tenant_id, email) | Correo de acceso administrativo |
| password | VARCHAR(255) | No | Hash Bcrypt/Argon2 | Credencial encriptada |
| role | VARCHAR(40) | No | OWNER, STAFF | Nivel de privilegio interno |
| is_active | BOOLEAN | No | DEFAULT true | Permite o bloquea el inicio de sesión |
| created_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de creación |
| updated_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de actualización |

### 2.4. Tabla `categories`
Árbol de clasificación del catálogo por inquilino.

| Columna | Tipo SQL | Nulo | Restricción | Descripción |
|---|---|---|---|---|
| id | UUID | No | PK | Identificador de categoría |
| tenant_id | UUID | No | FK tenants | Inquilino propietario |
| parent_id | UUID | Sí | FK categories | Categoría padre opcional |
| name | VARCHAR(120) | No | Ninguna | Nombre público de categoría |
| slug | VARCHAR(120) | No | UQ(tenant_id, slug) | Identificador amigable para URL |
| is_active | BOOLEAN | No | DEFAULT true | Visibilidad en vitrina |
| created_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de creación |
| updated_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de actualización |

### 2.5. Tabla `products`
Entidad universal de producto y contenedor de metadatos descriptivos (`affects_stock = false`).

| Columna | Tipo SQL | Nulo | Restricción | Descripción |
|---|---|---|---|---|
| id | UUID | No | PK | Identificador del producto base |
| tenant_id | UUID | No | FK tenants | Inquilino propietario |
| category_id | UUID | Sí | FK categories | Categoría principal asociada |
| name | VARCHAR(180) | No | Ninguna | Título comercial del producto |
| slug | VARCHAR(200) | No | UQ(tenant_id, slug) | Ruta amigable única por tienda |
| description | TEXT | Sí | Ninguna | Descripción extendida opcional |
| base_price | DECIMAL(12,2) | No | Mayor a 0 | Precio base de referencia |
| metadata | JSONB | No | GIN jsonb_path_ops | Atributos que no alteran el stock |
| images | JSONB | No | DEFAULT '[]' | Arreglo ordenado de URLs de imágenes |
| is_active | BOOLEAN | No | DEFAULT true | Estado de publicación en catálogo |
| created_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de creación |
| updated_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de actualización |
| deleted_at | TIMESTAMPTZ | Sí | Soft Delete | Marca de borrado lógico |

### 2.6. Tabla `product_variants`
Unidad física vendible y contenedor de metadatos dimensionales (`affects_stock = true`).

| Columna | Tipo SQL | Nulo | Restricción | Descripción |
|---|---|---|---|---|
| id | UUID | No | PK | Identificador de la variante |
| tenant_id | UUID | No | FK tenants | Inquilino propietario |
| product_id | UUID | No | FK products | Producto padre asociado |
| sku | VARCHAR(80) | No | UQ(tenant_id, sku) | Código de inventario único por tienda |
| price | DECIMAL(12,2) | Sí | Mayor a 0 | Sobrescribe `base_price` si no es nulo |
| stock | INTEGER | No | Mayor o igual a 0 | Existencia física disponible |
| metadata | JSONB | No | GIN jsonb_path_ops | Atributos que particionan el stock |
| is_default | BOOLEAN | No | DEFAULT false | Indica variante transparente por defecto |
| is_active | BOOLEAN | No | DEFAULT true | Habilita o bloquea su venta |
| created_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de creación |
| updated_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de actualización |
| deleted_at | TIMESTAMPTZ | Sí | Soft Delete | Marca de borrado lógico |

### 2.7. Tabla `promotions`
Reglas de descuento automáticas por carrito o plantillas base para cupones manuales.

| Columna | Tipo SQL | Nulo | Restricción | Descripción |
|---|---|---|---|---|
| id | UUID | No | PK | Identificador de la promoción |
| tenant_id | UUID | No | FK tenants | Inquilino propietario |
| name | VARCHAR(150) | No | Ninguna | Nombre interno o comercial |
| type | VARCHAR(20) | No | AUTOMATIC, COUPON | Mecanismo de activación |
| discount_type | VARCHAR(20) | No | PERCENTAGE, FIXED_AMOUNT | Naturaleza del cálculo |
| discount_value | DECIMAL(12,2) | No | Mayor a 0 | Valor porcentual o monetario fijo |
| conditions | JSONB | No | DEFAULT '{}' | Reglas de volumen o monto mínimo |
| allow_stacking | BOOLEAN | No | DEFAULT false | Permite combinar con cupón manual |
| starts_at | TIMESTAMPTZ | No | Ninguna | Inicio de vigencia |
| ends_at | TIMESTAMPTZ | Sí | Mayor a starts_at | Fin de vigencia opcional |
| is_active | BOOLEAN | No | DEFAULT true | Interruptor de activación |
| created_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de creación |
| updated_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de actualización |

### 2.8. Tabla `affiliates`
Colaboradores o micro-afiliados que refieren ventas mediante cupones asignados.

| Columna | Tipo SQL | Nulo | Restricción | Descripción |
|---|---|---|---|---|
| id | UUID | No | PK | Identificador del afiliado |
| tenant_id | UUID | No | FK tenants | Inquilino propietario |
| name | VARCHAR(150) | No | Ninguna | Nombre completo del colaborador |
| email | VARCHAR(150) | No | UQ(tenant_id, email) | Correo de notificaciones |
| phone | VARCHAR(30) | No | Ninguna | Teléfono de contacto |
| commission_type | VARCHAR(20) | No | PERCENTAGE, FIXED_AMOUNT | Modalidad de ganancia |
| commission_value | DECIMAL(12,2) | No | Mayor a 0 | Tasa o monto fijo por orden |
| payment_info | JSONB | No | DEFAULT '{}' | Datos bancarios para liquidación |
| is_active | BOOLEAN | No | DEFAULT true | Estado activo del colaborador |
| created_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de alta |
| updated_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de modificación |

### 2.9. Tabla `coupons`
Códigos alfanuméricos ingresados manualmente en el checkout.

| Columna | Tipo SQL | Nulo | Restricción | Descripción |
|---|---|---|---|---|
| id | UUID | No | PK | Identificador del cupón |
| tenant_id | UUID | No | FK tenants | Inquilino propietario |
| promotion_id | UUID | No | FK promotions | Regla de descuento vinculada |
| affiliate_id | UUID | Sí | FK affiliates | Afiliado atribuido opcional |
| code | VARCHAR(50) | No | UQ(tenant_id, code) | Código en mayúsculas sin espacios |
| max_uses | INTEGER | Sí | Mayor a 0 | Límite global de redenciones |
| used_count | INTEGER | No | Menor o igual a max_uses | Contador atómico de canjes |
| starts_at | TIMESTAMPTZ | No | Ninguna | Inicio de validez del código |
| ends_at | TIMESTAMPTZ | Sí | Mayor a starts_at | Expiración del código |
| is_active | BOOLEAN | No | DEFAULT true | Estado del código |
| created_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de creación |
| updated_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de actualización |

### 2.10. Tabla `orders`
Cabecera transaccional del pedido orquestado hacia WhatsApp.

| Columna | Tipo SQL | Nulo | Restricción | Descripción |
|---|---|---|---|---|
| id | UUID | No | PK | Identificador de la orden |
| tenant_id | UUID | No | FK tenants | Inquilino propietario |
| order_number | VARCHAR(30) | No | UQ(tenant_id, order_number) | Código legible (ej. #ORD-8392) |
| status | VARCHAR(30) | No | Estado válido | PENDIENTE_PAGO, COMPLETADA, CANCELADA |
| customer_info | JSONB | No | Ninguna | Datos de contacto del comprador |
| shipping_address | JSONB | No | Ninguna | Dirección y departamento de entrega |
| subtotal | DECIMAL(12,2) | No | Mayor o igual a 0 | Suma bruta de líneas de pedido |
| auto_discount_total | DECIMAL(12,2) | No | DEFAULT 0.00 | Descuento por promoción automática |
| coupon_discount_total | DECIMAL(12,2) | No | DEFAULT 0.00 | Descuento por cupón manual |
| shipping_fee | DECIMAL(12,2) | No | DEFAULT 0.00 | Costo de envío según región |
| total_net | DECIMAL(12,2) | No | Mayor o igual a 0 | Monto final a transferir |
| applied_promotion_id | UUID | Sí | FK promotions | Promoción automática aplicada |
| applied_coupon_id | UUID | Sí | FK coupons | Cupón manual aplicado |
| whatsapp_url | TEXT | No | Ninguna | Enlace generado con payload codificado |
| completed_at | TIMESTAMPTZ | Sí | Ninguna | Marca de confirmación de pago |
| cancelled_at | TIMESTAMPTZ | Sí | Ninguna | Marca de anulación y reversión de stock |
| created_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de registro |
| updated_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de modificación |

### 2.11. Tabla `order_items`
Desglose de líneas de cada orden con captura inmutable de la variante comprada.

| Columna | Tipo SQL | Nulo | Restricción | Descripción |
|---|---|---|---|---|
| id | UUID | No | PK | Identificador de línea |
| tenant_id | UUID | No | FK tenants | Inquilino propietario |
| order_id | UUID | No | FK orders | Orden padre |
| product_id | UUID | No | FK products | Referencia al producto |
| variant_id | UUID | No | FK product_variants | Variante que descontó inventario |
| quantity | INTEGER | No | Mayor a 0 | Unidades solicitadas |
| unit_price | DECIMAL(12,2) | No | Mayor o igual a 0 | Precio unitario congelado al comprar |
| line_total | DECIMAL(12,2) | No | quantity * unit_price | Subtotal de la línea |
| variant_snapshot | JSONB | No | Ninguna | Copia inmutable de nombre, SKU y metadatos |
| created_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de creación |

### 2.12. Tabla `commissions`
Registro contable de comisiones generadas por cupones de afiliados.

| Columna | Tipo SQL | Nulo | Restricción | Descripción |
|---|---|---|---|---|
| id | UUID | No | PK | Identificador de comisión |
| tenant_id | UUID | No | FK tenants | Inquilino propietario |
| order_id | UUID | No | UQ(tenant_id, order_id) | Orden que originó la comisión |
| affiliate_id | UUID | No | FK affiliates | Beneficiario de la comisión |
| coupon_id | UUID | No | FK coupons | Cupón utilizado en la compra |
| amount | DECIMAL(12,2) | No | Mayor a 0 | Monto calculado a favor del afiliado |
| status | VARCHAR(20) | No | Estado válido | RETENIDA, APROBADA, PAGADA, ANULADA |
| paid_at | TIMESTAMPTZ | Sí | Ninguna | Fecha de liquidación bancaria |
| payment_reference | VARCHAR(120) | Sí | Ninguna | Número de comprobante o transferencia |
| payment_receipt_url | VARCHAR(255) | Sí | Ninguna | URL del comprobante adjunto |
| created_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de creación |
| updated_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de actualización |

### 2.13. Tabla `partner_stores`
Comercios aliados externos habilitados para redimir vouchers de fidelización cruzada.

| Columna | Tipo SQL | Nulo | Restricción | Descripción |
|---|---|---|---|---|
| id | UUID | No | PK | Identificador del comercio aliado |
| tenant_id | UUID | No | FK tenants | Inquilino emisor de la alianza |
| name | VARCHAR(150) | No | Ninguna | Nombre comercial del aliado |
| portal_email | VARCHAR(150) | No | UQ(tenant_id, portal_email) | Usuario de acceso al portal de canje |
| portal_password | VARCHAR(255) | No | Hash Bcrypt/Argon2 | Contraseña del comercio aliado |
| benefit_title | VARCHAR(200) | No | Ninguna | Beneficio impreso en el voucher |
| terms_conditions | TEXT | No | Ninguna | Restricciones legales de uso |
| contact_info | JSONB | No | DEFAULT '{}' | Dirección física y teléfono del local |
| is_active | BOOLEAN | No | DEFAULT true | Estado de la alianza comercial |
| created_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de alta |
| updated_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de actualización |

### 2.14. Tabla `outbound_rewards`
Vouchers criptográficos emitidos al completarse órdenes que superan el umbral configurado.

| Columna | Tipo SQL | Nulo | Restricción | Descripción |
|---|---|---|---|---|
| id | UUID | No | PK | Identificador del voucher |
| tenant_id | UUID | No | FK tenants | Inquilino emisor |
| order_id | UUID | No | UQ(order_id, partner_store_id) | Orden que disparó el beneficio |
| partner_store_id | UUID | No | FK partner_stores | Comercio donde se puede canjear |
| code | VARCHAR(64) | No | UNIQUE | Token criptográfico irrepetible |
| status | VARCHAR(20) | No | Estado válido | EMITIDO, CANJEADO, EXPIRADO |
| expires_at | TIMESTAMPTZ | No | Ninguna | Fecha límite de redención física |
| redeemed_at | TIMESTAMPTZ | Sí | Ninguna | Marca exacta de canje en tienda aliada |
| email_sent_at | TIMESTAMPTZ | Sí | Ninguna | Confirmación de despacho por cola |
| created_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de emisión |
| updated_at | TIMESTAMPTZ | No | DEFAULT NOW() | Fecha de modificación |

---

## 3. Contratos de Esquemas JSONB (Estructura y Validación)

### 3.1. Esquema de `tenant_configs.metadata_schema`
```json
[
  {
    "key": "talla",
    "label": "Talla de Prenda",
    "data_type": "select",
    "options": ["S", "M", "L", "XL"],
    "is_required": true,
    "affects_stock": true
  },
  {
    "key": "material",
    "label": "Composición Textil",
    "data_type": "text",
    "options": null,
    "is_required": true,
    "affects_stock": false
  }
]
```

### 3.2. Esquema de `tenant_configs.filter_config`
```json
[
  { "type": "system", "key": "price", "label": "Precio", "order": 1 },
  { "type": "system", "key": "category", "label": "Categoría", "order": 2 },
  { "type": "metadata", "key": "talla", "label": "Talla", "order": 3 }
]
```

---

## 4. Códigos de Error de Dominio (`error.code`)

| Código de Error | HTTP Status | Causa Operativa |
|---|---|---|
| TENANT_NOT_FOUND | 404 | El `X-Tenant-Slug` no existe o el inquilino está inactivo. |
| RESERVED_METADATA_KEY | 422 | Intento de registrar una palabra reservada en `metadata_schema`. |
| ORPHAN_FILTER_KEY | 422 | Filtro en `filter_config` apunta a una clave inexistente en `metadata_schema`. |
| INSUFFICIENT_VARIANT_STOCK | 422 | Cantidad solicitada supera `product_variants.stock` o es igual a cero. |
| COUPON_EXPIRED_OR_EXHAUSTED | 422 | El cupón superó `max_uses` o se encuentra fuera de vigencia. |
| NON_STACKABLE_COUPON | 422 | El carrito tiene una promoción activa con `allow_stacking = false`. |
| VOUCHER_ALREADY_REDEEMED | 409 | Intento de canje duplicado sobre un registro `outbound_rewards` en estado `CANJEADO`. |