# Especificación del Contrato de API REST (API Contract)
**Proyecto:** Plataforma SaaS Multi-Tenant de Comercio Electrónico Asistido (Schema-Driven)  
**Versión de API:** v1  
**Formato de Comunicación:** JSON (`application/json`)  
**Mecanismo de Aislamiento Tenant:** Cabecera HTTP `X-Tenant-Slug: {slug}` o segmento de ruta.

---

## 1. Convenciones Generales de la API

### 1.1. Cabeceras HTTP Requeridas
* **Peticiones Públicas (Storefront):**
  * `X-Tenant-Slug: {slug}` (Identificador alfanumérico único del inquilino).
  * `Content-Type: application/json`
  * `Accept: application/json`
* **Peticiones Transaccionales (Checkout con Idempotencia):**
  * `Idempotency-Key: {UUID-v4}` (Obligatorio en endpoints de creación de órdenes para prevenir doble cobro o duplicación por reintentos de red).
* **Peticiones Protegidas (Admin y Partner Portal):**
  * `Authorization: Bearer {token}` (Token JWT o sesión autenticada con alcances específicos).

### 1.2. Estructura Estándar de Respuesta Exitosa
* **Respuesta Individual (`200 OK`, `201 Created`):**
```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "attributes": { ... }
  }
}
```
* **Respuesta Paginada (`200 OK`):**
```json
{
  "data": [
    { "id": "...", "..." }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 85,
    "last_page": 5
  }
}
```

---

## 2. Módulo A: Vitrina Pública y Catálogo Facetado (Storefront)

### 2.1. Bootstrap de Configuración de Vitrina
* **Endpoint:** `GET /api/v1/storefront/config`
* **Descripción:** Retorna las variables de marca (theming CSS), el esquema de filtros activos y las zonas geográficas configuradas por el inquilino. Debe servirse desde caché (Redis).
* **Cabeceras:** `X-Tenant-Slug: tienda-ejemplo`
* **Respuesta `200 OK`:**
```json
{
  "tenant": {
    "name": "Tienda Ejemplo",
    "currency_code": "HNL",
    "country_code": "HN"
  },
  "branding": {
    "primary_color": "#1E293B",
    "secondary_color": "#475569",
    "accent_color": "#F59E0B",
    "font_family": "Inter",
    "logo_url": "https://cdn.plataforma.com/t1/logo.webp",
    "banner_url": "https://cdn.plataforma.com/t1/banner.webp",
    "favicon_url": "https://cdn.plataforma.com/t1/favicon.ico"
  },
  "filters": [
    { "type": "system", "key": "price", "label": "Rango de Precio", "data_type": "range" },
    { "type": "system", "key": "category", "label": "Categoría", "data_type": "select" },
    { "type": "metadata", "key": "talla", "label": "Talla", "data_type": "select", "options": ["S", "M", "L", "XL"], "affects_stock": true },
    { "type": "metadata", "key": "material", "label": "Material Principal", "data_type": "text", "affects_stock": false }
  ],
  "shipping_zones": [
    { "code": "HN-CR", "name": "Cortés", "fee": 80.00 },
    { "code": "HN-FM", "name": "Francisco Morazán", "fee": 120.00 }
  ]
}
```

### 2.2. Búsqueda y Filtrado Facetado sobre JSONB
* **Endpoint:** `GET /api/v1/storefront/products`
* **Descripción:** Resuelve consultas combinadas de texto, rangos numéricos y pares clave-valor arbitrarios del JSONB en un único paso, utilizando índices GIN (`jsonb_path_ops`).
* **Query Params:**
  * `q` (String, opcional): Texto de búsqueda parcial en nombre.
  * `category_id` (UUID, opcional): Filtro por categoría.
  * `price_min` / `price_max` (Numeric, opcional): Rango de precios.
  * `attr[{key}]` (Mixed, opcional): Pares clave-valor dinámicos (ej. `attr[talla]=M&attr[color]=Negro`).
  * `sort` (String, opcional): `price_asc`, `price_desc`, `newest`.
  * `page` / `per_page` (Integer, opcional): Paginación.
* **Respuesta `200 OK`:**
```json
{
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Camisa Oxford Clásica",
      "slug": "camisa-oxford-clasica",
      "base_price": 650.00,
      "metadata": {
        "material": "Algodón Pima 100%"
      },
      "images": ["https://cdn.plataforma.com/t1/p1.webp"],
      "matched_variants": [
        {
          "id": "770e8400-e29b-41d4-a716-446655440111",
          "sku": "OXF-NEG-M",
          "effective_price": 650.00,
          "stock": 8,
          "is_default": false,
          "metadata": {
            "talla": "M",
            "color": "Negro"
          }
        }
      ]
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 1
  }
}
```

### 2.3. Cotización de Carrito, Promociones y Cupones
* **Endpoint:** `POST /api/v1/storefront/cart/quote`
* **Descripción:** Valida la disponibilidad de stock en tiempo real, calcula descuentos automáticos por volumen/monto y verifica reglas de no apilamiento (`allow_stacking`) al aplicar un cupón manual.
* **Request Body:**
```json
{
  "items": [
    { "variant_id": "770e8400-e29b-41d4-a716-446655440111", "quantity": 2 }
  ],
  "coupon_code": "JUAN10",
  "shipping_zone_code": "HN-CR"
}
```
* **Respuesta `200 OK`:**
```json
{
  "is_valid": true,
  "items": [
    {
      "variant_id": "770e8400-e29b-41d4-a716-446655440111",
      "product_name": "Camisa Oxford Clásica",
      "sku": "OXF-NEG-M",
      "variant_attributes": { "talla": "M", "color": "Negro" },
      "quantity": 2,
      "available_stock": 8,
      "unit_price": 650.00,
      "line_total": 1300.00
    }
  ],
  "summary": {
    "subtotal": 1300.00,
    "auto_promotion": {
      "applied": false,
      "name": null,
      "discount_amount": 0.00
    },
    "coupon": {
      "applied": true,
      "code": "JUAN10",
      "discount_amount": 130.00,
      "message": "Cupón aplicado exitosamente."
    },
    "shipping_fee": 80.00,
    "total_net": 1250.00
  }
}
```

### 2.4. Creación de Orden y Orquestación hacia WhatsApp
* **Endpoint:** `POST /api/v1/storefront/checkout`
* **Descripción:** Ejecuta una transacción ACID con bloqueo pesimista (`SELECT ... FOR UPDATE`), descuenta inventario, registra la orden en estado `PENDIENTE_PAGO`, crea la comisión retenida (si aplica afiliado) y retorna la URL oficial codificada de WhatsApp.
* **Cabecera Requerida:** `Idempotency-Key: {uuid}`
* **Request Body:**
```json
{
  "customer_info": {
    "full_name": "Carlos Mendoza",
    "whatsapp_phone": "+50498765432",
    "email": "carlos@correo.com"
  },
  "shipping_address": {
    "region_code": "HN-CR",
    "address_line": "Col. Trejo, 10 Calle, 15 Avenida, Casa #42",
    "reference": "Portón gris frente al parque"
  },
  "items": [
    { "variant_id": "770e8400-e29b-41d4-a716-446655440111", "quantity": 2 }
  ],
  "coupon_code": "JUAN10"
}
```
* **Respuesta `201 Created`:**
```json
{
  "order_id": "990e8400-e29b-41d4-a716-446655440999",
  "order_number": "#ORD-8392",
  "status": "PENDIENTE_PAGO",
  "total_net": 1250.00,
  "whatsapp_redirect_url": "https://wa.me/50499887766?text=Hola%2C%20deseo%20confirmar%20mi%20pedido%20*23ORD-8392*..."
}
```

---

## 3. Módulo B: Panel de Administración del Inquilino (Tenant Admin)

* **Autenticación:** Bearer Token o Cookie HTTP-only con protección CSRF.

### 3.1. Actualizar Esquema de Metadatos y Filtros
* **Endpoint:** `PUT /api/v1/admin/config/catalog-schema`
* **Descripción:** Actualiza el `metadata_schema` y `filter_config`. Valida claves reservadas y purga filtros huérfanos.
* **Request Body:**
```json
{
  "metadata_schema": [
    {
      "key": "talla",
      "label": "Talla",
      "data_type": "select",
      "options": ["S", "M", "L"],
      "is_required": true,
      "affects_stock": true
    }
  ],
  "filter_config": [
    { "type": "system", "key": "price", "label": "Precio", "order": 1 },
    { "type": "metadata", "key": "talla", "label": "Talla", "order": 2 }
  ]
}
```
* **Respuesta `200 OK`:** Retorna la configuración actualizada del tenant.

### 3.2. Crear Producto y Variantes Gobernadas por Esquema
* **Endpoint:** `POST /api/v1/admin/products`
* **Descripción:** Registra un producto validando su `metadata` contra el esquema y creando las variantes con inventario independiente.
* **Request Body:**
```json
{
  "category_id": "110e8400-e29b-41d4-a716-446655440001",
  "name": "Camisa Oxford Clásica",
  "description": "Camisa 100% algodón de corte regular.",
  "base_price": 650.00,
  "is_active": true,
  "metadata": {
    "material": "Algodón Pima 100%"
  },
  "images": ["https://cdn.plataforma.com/p1.webp"],
  "variants": [
    {
      "sku": "OXF-M",
      "price": 680.00,
      "stock": 8,
      "metadata": { "talla": "M" }
    }
  ]
}
```
* **Respuesta `201 Created`:** Retorna el producto creado con sus variantes.

### 3.3. Actualizar Estado de Orden (Confirmación de Pago)
* **Endpoint:** `PATCH /api/v1/admin/orders/{order_id}/status`
* **Descripción:** Cambia el estado de la orden a `COMPLETADA` o `CANCELADA`. Si pasa a completada, aprueba comisiones y evalúa la emisión de vouchers de fidelización cruzada.
* **Request Body:**
```json
{
  "status": "COMPLETADA",
  "payment_notes": "Transferencia BAC #9482910 verificada"
}
```
* **Respuesta `200 OK`:**
```json
{
  "order_id": "990e8400-e29b-41d4-a716-446655440999",
  "status": "COMPLETADA",
  "commission_status": "APROBADA",
  "outbound_reward_generated": true,
  "completed_at": "2026-11-06T19:55:00Z"
}
```

### 3.4. Liquidación de Comisiones a Afiliados
* **Endpoint:** `POST /api/v1/admin/affiliates/{affiliate_id}/commissions/settle`
* **Descripción:** Marca comisiones en estado `APROBADA` como `PAGADA`, registrando el comprobante de transferencia.
* **Request Body:**
```json
{
  "commission_ids": [
    "330e8400-e29b-41d4-a716-446655440333"
  ],
  "payment_reference": "ACH-884920",
  "payment_receipt_url": "https://cdn.plataforma.com/receipts/ach-884920.pdf"
}
```
* **Respuesta `200 OK`:**
```json
{
  "settled_count": 1,
  "total_paid": 130.00,
  "status": "PAGADA",
  "paid_at": "2026-11-06T20:10:00Z"
}
```

---

## 4. Módulo C: Portal de Comercios Aliados (Outbound Rewards)

* **Autenticación:** Token Bearer con alcance exclusivo para aliados (`scope: partner-portal`).

### 4.1. Consultar / Validar Voucher de Regalo
* **Endpoint:** `GET /api/v1/partner-portal/vouchers/{code}`
* **Descripción:** Permite al comercio aliado verificar la autenticidad y vigencia de un voucher presentado por un cliente en tienda física.
* **Respuesta `200 OK`:**
```json
{
  "voucher_code": "RWD-9F8A-7C2B-4E1D",
  "status": "EMITIDO",
  "is_redeemable": true,
  "benefit_title": "20% de descuento en consumo total",
  "customer_name": "Carlos Mendoza",
  "origin_tenant_name": "Tienda Ejemplo",
  "expires_at": "2026-12-06T23:59:59Z",
  "redeemed_at": null
}
```

### 4.2. Canjear Voucher (Idempotente)
* **Endpoint:** `POST /api/v1/partner-portal/vouchers/{code}/redeem`
* **Descripción:** Ejecuta el canje definitivo del voucher con bloqueo pesimista para evitar doble uso.
* **Respuesta `200 OK`:**
```json
{
  "voucher_code": "RWD-9F8A-7C2B-4E1D",
  "status": "CANJEADO",
  "redeemed_at": "2026-11-06T20:15:30Z",
  "message": "Beneficio canjeado exitosamente."
}
```
* **Respuesta `409 Conflict` (Cupón ya redimido):**
```json
{
  "error": {
    "code": "VOUCHER_ALREADY_REDEEMED",
    "message": "Este cupón ya fue canjeado el 2026-11-06 a las 20:15:30.",
    "redeemed_at": "2026-11-06T20:15:30Z"
  }
}
```