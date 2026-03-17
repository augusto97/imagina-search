# PROMPT DE REFACTORIZACIÓN — Woo Smart Search (Solo Meilisearch)

## CONTEXTO

Ya tenemos una base del plugin "Woo Smart Search" generada con soporte dual para Meilisearch y Typesense. Necesitamos refactorizar para:

1. **Eliminar todo lo relacionado con Typesense** (adapter, factory, selector de motor, dependencias)
2. **Simplificar la arquitectura** eliminando la abstracción innecesaria (Interface + Factory) y trabajando directamente con Meilisearch
3. **Agregar funcionalidades clave** que los plugins competidores no tienen
4. **Mejorar el widget de búsqueda frontend** para que sea visualmente superior

---

## PASO 1: ELIMINAR TYPESENSE Y SIMPLIFICAR

### Archivos a eliminar:
- `includes/engines/class-wss-typesense.php`
- `includes/engines/interface-wss-search-engine.php`
- `includes/engines/class-wss-engine-factory.php`

### Archivos a refactorizar:
- `includes/engines/class-wss-meilisearch.php` → Renombrar a `includes/class-wss-meilisearch.php` y convertirla en la clase principal de conexión con Meilisearch, sin implementar ninguna interface abstracta.
- Eliminar cualquier referencia a Typesense en todos los archivos del plugin.
- Eliminar el selector de motor del admin panel (ya no es necesario elegir entre motores).

### Dependencias (composer.json):
- Eliminar `"typesense/typesense-php"` de require
- Solo mantener `"meilisearch/meilisearch-php": "^1.0"`

### Admin Panel:
- En la pestaña de Conexión, eliminar el dropdown de "Selección de motor"
- Dejar solo los campos para Meilisearch: Host, Puerto, API Key Master, Search API Key, Nombre del índice

---

## PASO 2: MEJORAR LA SINCRONIZACIÓN (Diferenciador vs competencia)

Los plugins existentes (Yuto, Scry Search) tienen una sincronización muy básica. Necesitamos una sincronización robusta específica para WooCommerce.

### Problemas conocidos de la competencia que debemos resolver:

1. **Yuto carga TODOS los posts en memoria antes de indexar** — nosotros debemos usar batch processing real con WP_Query paginada + Action Scheduler
2. **Ningún plugin indexa correctamente productos variables** — nosotros debemos indexar el producto padre CON datos agregados de sus variaciones
3. **Ningún plugin maneja bien la sincronización incremental** — nosotros debemos tener hooks completos y una cola inteligente

### Implementar sincronización mejorada:

```php
/**
 * Sincronización por lotes usando WP_Query paginada
 * NUNCA cargar todos los productos en memoria
 */
public function bulk_sync() {
    $page = 1;
    $batch_size = get_option('wss_batch_size', 100);
    
    do {
        $products = wc_get_products([
            'status'  => 'publish',
            'limit'   => $batch_size,
            'page'    => $page,
            'type'    => ['simple', 'variable', 'grouped', 'external'],
            'return'  => 'objects',
        ]);
        
        if (empty($products)) break;
        
        $documents = array_map([$this->transformer, 'transform'], $products);
        $this->meilisearch->index($this->index_name)->addDocuments($documents);
        
        // Registrar progreso
        update_option('wss_sync_progress', [
            'page'     => $page,
            'indexed'  => ($page - 1) * $batch_size + count($products),
            'status'   => 'processing',
        ]);
        
        $page++;
        
    } while (count($products) === $batch_size);
}
```

### Transformación mejorada de productos variables:

Cuando el producto es de tipo `variable`, el documento debe incluir:

```php
// Para productos variables, agregar datos agregados de variaciones
if ($product->is_type('variable')) {
    $variations = $product->get_available_variations('objects');
    
    $document['price_min'] = $product->get_variation_price('min');
    $document['price_max'] = $product->get_variation_price('max');
    
    // Texto buscable de todas las variaciones
    $variation_texts = [];
    $all_attributes = [];
    
    foreach ($variations as $variation) {
        $attrs = $variation->get_attributes();
        foreach ($attrs as $key => $value) {
            $variation_texts[] = $value;
            $all_attributes[$key][] = $value;
        }
        // SKUs de variaciones también buscables
        if ($variation->get_sku()) {
            $variation_texts[] = $variation->get_sku();
        }
    }
    
    $document['variations_text'] = implode(' ', array_unique($variation_texts));
    $document['variation_attributes'] = $all_attributes;
    $document['variations_count'] = count($variations);
}
```

### Agregar soporte para campos ACF / Custom Fields:

```php
// En el transformer, agregar campos personalizados seleccionados en el admin
$custom_fields = get_option('wss_custom_fields', []);
foreach ($custom_fields as $field_key) {
    $value = get_post_meta($product->get_id(), $field_key, true);
    if (!empty($value)) {
        // Si es ACF, intentar obtener el label
        if (function_exists('get_field_object')) {
            $field_obj = get_field_object($field_key, $product->get_id());
            $document['cf_' . $field_key] = is_array($value) ? implode(', ', $value) : $value;
        } else {
            $document['cf_' . $field_key] = is_array($value) ? implode(', ', $value) : (string) $value;
        }
    }
}
```

---

## PASO 3: MEJORAR EL WIDGET DE BÚSQUEDA FRONTEND (Diferenciador visual clave)

Este es el punto más importante. Los plugins existentes tienen widgets muy básicos — texto plano sin imágenes, sin precios, sin badges. Nuestro widget debe verse como Doofinder o Searchanise.

### Diseño del widget de autocompletado:

El dropdown de resultados debe mostrar para cada producto:
- **Imagen thumbnail** del producto (a la izquierda, 60x60px)
- **Nombre del producto** con highlighting de la coincidencia en `<mark>`
- **Categoría** en texto pequeño y gris encima del nombre
- **Precio actual** en negrita
- **Precio regular tachado** si está en oferta
- **Badge de descuento** (ej: "-20%") en rojo si está en oferta
- **Indicador de stock**: punto verde "En stock" / punto rojo "Agotado"
- **SKU** en texto pequeño (opcional, configurable)
- **Rating** con estrellas (opcional, configurable)

### Secciones del dropdown:

```
┌──────────────────────────────────────────────┐
│ 🔍 [campo de búsqueda..................] ✕   │
├──────────────────────────────────────────────┤
│ Sugerencias de categorías (si aplica)        │
│  📁 Categoría A (12)  📁 Categoría B (5)    │
├──────────────────────────────────────────────┤
│ Productos                                     │
│                                               │
│ [IMG] Categoría                               │
│       Nombre del Producto Destacado    -20%   │
│       ██ $80.00  $100.00               ●Stock │
│                                               │
│ [IMG] Categoría                               │
│       Otro Producto Relevante                 │
│       $45.00                           ●Stock │
│                                               │
│ [IMG] Categoría                               │
│       Tercer Resultado                        │
│       $120.00                       ●Agotado  │
│                                               │
├──────────────────────────────────────────────┤
│        Ver los 24 resultados →                │
└──────────────────────────────────────────────┘
```

### CSS Variables para personalización:

```css
:root {
    /* Personalizables desde el admin */
    --wss-primary-color: #2563eb;
    --wss-primary-hover: #1d4ed8;
    --wss-bg-color: #ffffff;
    --wss-text-color: #1f2937;
    --wss-text-secondary: #6b7280;
    --wss-border-color: #e5e7eb;
    --wss-highlight-bg: #fef3c7;
    --wss-highlight-text: #92400e;
    --wss-sale-badge-bg: #ef4444;
    --wss-sale-badge-text: #ffffff;
    --wss-instock-color: #10b981;
    --wss-outofstock-color: #ef4444;
    --wss-border-radius: 8px;
    --wss-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    --wss-font-size-base: 14px;
    --wss-input-height: 44px;
    --wss-dropdown-max-height: 480px;
    --wss-image-size: 60px;
    --wss-z-index: 999999;
}

/* Modo oscuro */
.wss-dark {
    --wss-bg-color: #1f2937;
    --wss-text-color: #f9fafb;
    --wss-text-secondary: #9ca3af;
    --wss-border-color: #374151;
    --wss-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
}
```

### Modo móvil (< 768px):

En móviles, el dropdown debe convertirse en un **overlay fullscreen** con:
- Fondo semi-transparente oscuro detrás
- El campo de búsqueda fijo arriba
- Resultados scrolleables debajo
- Botón de cerrar (X) visible
- Transición suave de apertura/cierre

### Animaciones:

```css
/* Entrada del dropdown */
.wss-results-dropdown {
    opacity: 0;
    transform: translateY(-8px);
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.wss-results-dropdown.wss-visible {
    opacity: 1;
    transform: translateY(0);
}

/* Hover en resultado */
.wss-result-item:hover,
.wss-result-item.wss-active {
    background-color: var(--wss-border-color);
    transition: background-color 0.15s ease;
}

/* Skeleton loading mientras carga */
.wss-skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: wss-shimmer 1.5s infinite;
}
```

---

## PASO 4: PÁGINA DE RESULTADOS COMPLETA CON FILTROS FACETADOS

Ningún plugin existente ofrece esto. Crear una página de resultados que reemplace la de WooCommerce con:

### Layout:

```
┌─────────────────────────────────────────────────────┐
│ Resultados para "zapatillas"  (24 productos)        │
├──────────┬──────────────────────────────────────────┤
│ FILTROS  │  Ordenar: [Relevancia ▼]  Vista: ▦ ▤    │
│          │──────────────────────────────────────────│
│ Categoría│  [Producto] [Producto] [Producto]        │
│ □ Zapatos│  [Producto] [Producto] [Producto]        │
│ □ Deporte│  [Producto] [Producto] [Producto]        │
│ □ Casual │                                          │
│          │                                          │
│ Precio   │  ← 1  2  3  4  5 →                      │
│ ●────────● │                                        │
│ $10  $200│                                          │
│          │                                          │
│ Stock    │                                          │
│ □ En stock│                                         │
│ □ Agotado│                                          │
│          │                                          │
│ Marca    │                                          │
│ □ Nike   │                                          │
│ □ Adidas │                                          │
│          │                                          │
└──────────┴──────────────────────────────────────────┘
```

### Implementación de filtros facetados:

- **Categorías**: Checkboxes generados dinámicamente desde facets de Meilisearch
- **Rango de precio**: Slider dual (min/max) usando el rango real de productos encontrados
- **Stock**: Toggle En stock / Agotado
- **Marcas**: Checkboxes si el sitio usa un plugin de marcas
- **Atributos**: Checkboxes dinámicos por cada atributo de WooCommerce (Color, Talla, etc.)
- **Rating**: Filtro por estrellas mínimas

Los filtros deben:
- Actualizar resultados en tiempo real sin recargar la página (AJAX)
- Mostrar conteo de productos por cada opción del filtro
- Ser colapsables/expandibles
- Recordar la selección en la URL (query params) para compartir búsquedas
- En móvil, los filtros se ocultan en un panel lateral deslizable

### Ordenamiento:
- Relevancia (default)
- Precio: menor a mayor
- Precio: mayor a menor
- Más recientes
- Más populares (por ventas)
- Mejor calificación

---

## PASO 5: PANEL DE APARIENCIA EN ADMIN (Preview en vivo)

Agregar una pestaña de "Apariencia" en el admin con preview en vivo del widget:

```
┌──────────────────────────────────────────────────────────┐
│ Apariencia del Widget de Búsqueda                        │
├─────────────────────┬────────────────────────────────────┤
│                     │                                     │
│ Tema: [Claro ▼]     │     PREVIEW EN VIVO                │
│                     │                                     │
│ Color primario:     │  ┌─────────────────────────────┐   │
│ [#2563eb] [■]       │  │ 🔍 Buscar productos...      │   │
│                     │  ├─────────────────────────────┤   │
│ Fondo dropdown:     │  │ [IMG] Categoría             │   │
│ [#ffffff] [■]       │  │       Producto de ejemplo   │   │
│                     │  │       $80.00  $100.00  -20%  │   │
│ Color de texto:     │  │                             │   │
│ [#1f2937] [■]       │  │ [IMG] Categoría             │   │
│                     │  │       Otro producto          │   │
│ Border radius:      │  │       $45.00                │   │
│ [8] px              │  │                             │   │
│                     │  │    Ver todos los resultados  │   │
│ Mostrar:            │  └─────────────────────────────┘   │
│ ☑ Imagen            │                                     │
│ ☑ Precio            │                                     │
│ ☑ Categoría         │                                     │
│ ☐ SKU               │                                     │
│ ☑ Stock             │                                     │
│ ☐ Rating            │                                     │
│ ☑ Badge de oferta   │                                     │
│                     │                                     │
│ Max resultados: [8] │                                     │
│                     │                                     │
│ Placeholder:        │                                     │
│ [Buscar productos..]│                                     │
│                     │                                     │
│ CSS Personalizado:  │                                     │
│ ┌─────────────────┐ │                                     │
│ │                 │ │                                     │
│ └─────────────────┘ │                                     │
│                     │                                     │
│ [💾 Guardar]        │                                     │
└─────────────────────┴────────────────────────────────────┘
```

El preview debe actualizarse en tiempo real cuando el admin cambia cualquier opción (usando JavaScript para actualizar CSS variables y mostrar/ocultar elementos).

---

## PASO 6: HEALTH CHECK Y NOTIFICACIONES

Implementar un sistema de monitoreo:

```php
// Cron job cada 5 minutos
add_action('wss_health_check', function() {
    $meilisearch = WSS_Meilisearch::get_instance();
    $result = $meilisearch->test_connection();
    
    if (!$result['success']) {
        // Guardar estado de error
        update_option('wss_connection_status', 'error');
        update_option('wss_last_error', $result['message']);
        update_option('wss_last_error_time', time());
        
        // Notificar al admin (una vez cada hora máximo)
        $last_notification = get_option('wss_last_error_notification', 0);
        if (time() - $last_notification > HOUR_IN_SECONDS) {
            $admin_email = get_option('admin_email');
            wp_mail(
                $admin_email,
                '[Woo Smart Search] Error de conexión con Meilisearch',
                'El servidor de Meilisearch no responde. Último error: ' . $result['message']
            );
            update_option('wss_last_error_notification', time());
        }
        
        // Activar fallback a búsqueda nativa de WooCommerce
        update_option('wss_fallback_active', true);
    } else {
        update_option('wss_connection_status', 'connected');
        update_option('wss_fallback_active', false);
        delete_option('wss_last_error');
    }
});
```

Mostrar indicador de estado en el admin:
- 🟢 Conectado (versión X.X, Y documentos indexados)
- 🟡 Sincronizando... (X% completado)
- 🔴 Error de conexión (último error hace X minutos) — Búsqueda nativa activada como fallback

---

## PASO 7: ANALYTICS BÁSICOS DE BÚSQUEDA

Registrar las búsquedas realizadas para que el admin pueda ver:

- Top 20 búsquedas más frecuentes
- Búsquedas sin resultados (para agregar sinónimos o productos)
- Búsquedas por día/semana (gráfico simple)
- Tasa de clics en resultados

Almacenar en una tabla custom: `{prefix}wss_search_log`

```sql
CREATE TABLE {prefix}wss_search_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    query VARCHAR(255) NOT NULL,
    results_count INT UNSIGNED DEFAULT 0,
    clicked_product_id BIGINT UNSIGNED DEFAULT NULL,
    user_ip VARCHAR(45),
    user_agent VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_query (query),
    INDEX idx_created (created_at)
);
```

---

## RESUMEN DE CAMBIOS

| Acción | Descripción |
|--------|-------------|
| ELIMINAR | Todo código relacionado con Typesense |
| ELIMINAR | Interface abstracta y Factory pattern |
| SIMPLIFICAR | Conexión directa con clase Meilisearch |
| MEJORAR | Sincronización con batches reales via WP_Query paginada |
| MEJORAR | Transformer con soporte completo para productos variables |
| AGREGAR | Soporte para campos ACF / Custom Fields |
| MEJORAR | Widget frontend con diseño visual profesional (imágenes, precios, badges) |
| AGREGAR | Modo móvil fullscreen overlay |
| AGREGAR | Skeleton loading y animaciones |
| AGREGAR | Página de resultados con filtros facetados |
| AGREGAR | Slider de rango de precio |
| AGREGAR | Panel de Apariencia con preview en vivo |
| AGREGAR | Health check con fallback automático a búsqueda nativa |
| AGREGAR | Notificaciones por email al admin si Meilisearch cae |
| AGREGAR | Analytics básicos de búsqueda |
| AGREGAR | Indicador de estado en el admin |

---

## IMPORTANTE

- NO elimines los archivos existentes que funcionan bien (admin, sync base, REST API proxy). Solo modifícalos.
- Mantén todos los hooks y filtros que ya existen y agrega los nuevos.
- El JavaScript del frontend sigue siendo vanilla JS puro, sin jQuery.
- Todos los estilos nuevos del widget deben usar CSS custom properties.
- Mantén la compatibilidad con el shortcode [woo_smart_search] existente.
- Asegúrate de que el fallback a búsqueda nativa funcione transparentemente cuando Meilisearch no esté disponible.
