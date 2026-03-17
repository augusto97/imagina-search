# PROMPT PARA CLAUDE CODE — Plugin WooCommerce Smart Search

## INSTRUCCIÓN PRINCIPAL

Desarrolla un plugin completo de WordPress/WooCommerce llamado **"Woo Smart Search"** que integre un motor de búsqueda externo (Meilisearch o Typesense, seleccionable desde el admin) para reemplazar la búsqueda nativa de WooCommerce con una experiencia de búsqueda instantánea, ultra-rápida y profesional.

El plugin debe ser de calidad comercial, listo para producción, con código limpio, documentado y siguiendo los estándares de WordPress Coding Standards.

---

## ARQUITECTURA GENERAL

```
WordPress/WooCommerce
    ├── Admin Panel (Configuración)
    │     ├── Selección de motor: Meilisearch o Typesense
    │     ├── Credenciales de conexión (Host, API Key, Puerto, Protocolo)
    │     ├── Configuración de índice (nombre, campos indexables)
    │     ├── Personalización visual del widget de búsqueda
    │     ├── Botón de sincronización manual (completa e incremental)
    │     └── Log de actividad / estado de sincronización
    │
    ├── Backend (Sincronización de datos)
    │     ├── Sincronización completa (bulk) de todos los productos
    │     ├── Sincronización incremental (hooks de WooCommerce)
    │     ├── Cola de sincronización con Action Scheduler
    │     ├── Manejo de productos variables (variaciones)
    │     └── Soporte para campos personalizados / ACF / metadatos
    │
    ├── Frontend (Widget de búsqueda)
    │     ├── Barra de búsqueda con autocompletado instantáneo
    │     ├── Resultados en tiempo real con imagen, precio, categoría
    │     ├── Filtros facetados (categoría, precio, atributos, stock)
    │     ├── Tolerancia a errores tipográficos
    │     ├── Resaltado de coincidencias (highlighting)
    │     ├── Diseño responsive y accesible
    │     └── Navegación por teclado en resultados
    │
    └── API Layer (Abstracción)
          ├── Interface SearchEngineInterface
          ├── Clase MeilisearchAdapter
          ├── Clase TypesenseAdapter
          └── Factory para instanciar el motor seleccionado
```

---

## REQUISITOS TÉCNICOS DETALLADOS

### 1. ESTRUCTURA DEL PLUGIN

```
woo-smart-search/
├── woo-smart-search.php              # Archivo principal del plugin
├── readme.txt                         # Readme estándar de WordPress
├── uninstall.php                      # Limpieza al desinstalar
├── composer.json                      # Dependencias PHP
├── package.json                       # Dependencias JS/CSS
│
├── includes/
│   ├── class-wss-loader.php           # Cargador de hooks y filtros
│   ├── class-wss-activator.php        # Lógica de activación
│   ├── class-wss-deactivator.php      # Lógica de desactivación
│   │
│   ├── engines/
│   │   ├── interface-wss-search-engine.php    # Interface común
│   │   ├── class-wss-meilisearch.php          # Adapter Meilisearch
│   │   ├── class-wss-typesense.php            # Adapter Typesense
│   │   └── class-wss-engine-factory.php       # Factory pattern
│   │
│   ├── sync/
│   │   ├── class-wss-product-sync.php         # Sincronización de productos
│   │   ├── class-wss-sync-queue.php           # Cola con Action Scheduler
│   │   └── class-wss-product-transformer.php  # Transforma producto a documento
│   │
│   ├── admin/
│   │   ├── class-wss-admin.php                # Página de administración
│   │   ├── class-wss-admin-ajax.php           # Endpoints AJAX del admin
│   │   └── views/                             # Templates del admin
│   │       ├── settings-page.php
│   │       ├── sync-status.php
│   │       └── log-viewer.php
│   │
│   └── frontend/
│       ├── class-wss-frontend.php             # Lógica del frontend
│       ├── class-wss-shortcode.php            # Shortcode [woo_smart_search]
│       └── class-wss-rest-api.php             # Endpoint proxy REST API
│
├── assets/
│   ├── js/
│   │   ├── admin.js                   # JS del panel admin
│   │   └── search-widget.js           # JS del widget de búsqueda (vanilla JS, sin jQuery)
│   ├── css/
│   │   ├── admin.css                  # Estilos del admin
│   │   └── search-widget.css          # Estilos del widget (personalizable via admin)
│   └── images/
│       └── placeholder.svg            # Imagen placeholder para productos sin foto
│
├── templates/
│   └── search-widget.php              # Template overridable del widget
│
└── languages/
    ├── woo-smart-search.pot
    └── woo-smart-search-es_ES.po      # Traducción al español
```

### 2. INTERFACE DEL MOTOR DE BÚSQUEDA

```php
interface WSS_Search_Engine_Interface {
    
    // Conexión
    public function connect( array $config ): bool;
    public function test_connection(): array; // ['success' => bool, 'message' => string, 'version' => string]
    
    // Gestión de índice
    public function create_index( string $index_name, array $settings ): bool;
    public function delete_index( string $index_name ): bool;
    public function get_index_stats( string $index_name ): array;
    public function configure_index( string $index_name, array $settings ): bool;
    
    // Documentos
    public function index_documents( string $index_name, array $documents ): array;
    public function update_documents( string $index_name, array $documents ): array;
    public function delete_document( string $index_name, string $document_id ): bool;
    public function delete_all_documents( string $index_name ): bool;
    
    // Búsqueda
    public function search( string $index_name, string $query, array $options = [] ): array;
    // $options puede incluir: limit, offset, filters, facets, sort, highlight_fields
    
    // Configuración de búsqueda
    public function set_searchable_attributes( string $index_name, array $attributes ): bool;
    public function set_filterable_attributes( string $index_name, array $attributes ): bool;
    public function set_sortable_attributes( string $index_name, array $attributes ): bool;
    public function set_synonyms( string $index_name, array $synonyms ): bool;
    public function set_stop_words( string $index_name, array $stop_words ): bool;
}
```

### 3. ESTRUCTURA DEL DOCUMENTO INDEXADO

Cada producto de WooCommerce debe transformarse en un documento con esta estructura:

```php
[
    'id'                => (int) $product_id,
    'name'              => (string) nombre del producto,
    'slug'              => (string) slug/permalink,
    'description'       => (string) descripción corta (strip_tags, max 500 chars),
    'full_description'  => (string) descripción completa (strip_tags, max 2000 chars),
    'sku'               => (string) SKU del producto,
    'permalink'         => (string) URL completa del producto,
    'image'             => (string) URL de la imagen principal (thumbnail 300x300),
    'gallery'           => (array) URLs de imágenes de galería,
    'price'             => (float) precio actual (sale_price o regular_price),
    'regular_price'     => (float) precio regular,
    'sale_price'        => (float) precio de oferta (0 si no tiene),
    'on_sale'           => (bool) si está en oferta,
    'currency'          => (string) moneda de la tienda,
    'stock_status'      => (string) 'instock', 'outofstock', 'onbackorder',
    'stock_quantity'    => (int|null) cantidad en stock,
    'categories'        => (array) nombres de categorías,
    'category_ids'      => (array) IDs de categorías,
    'category_slugs'    => (array) slugs de categorías,
    'tags'              => (array) nombres de etiquetas,
    'attributes'        => (array) atributos del producto como key-value,
    'brand'             => (string) marca si usa plugin de marcas,
    'rating'            => (float) promedio de calificación,
    'review_count'      => (int) número de reseñas,
    'type'              => (string) tipo: simple, variable, grouped, external,
    'visibility'        => (string) visibilidad del producto,
    'featured'          => (bool) si es producto destacado,
    'date_created'      => (int) timestamp de creación,
    'date_modified'     => (int) timestamp de última modificación,
    'total_sales'       => (int) ventas totales (para ordenar por popularidad),
    'menu_order'        => (int) orden de menú,
    'weight'            => (string) peso,
    'dimensions'        => (array) dimensiones ['length', 'width', 'height'],
    
    // Campos para productos variables (agregar variaciones como texto buscable)
    'variations_text'   => (string) texto concatenado de variaciones para búsqueda,
    'price_min'         => (float) precio mínimo entre variaciones,
    'price_max'         => (float) precio máximo entre variaciones,
    
    // Campos personalizados (configurable desde admin)
    'custom_fields'     => (array) campos ACF/meta personalizados seleccionados,
]
```

### 4. CONFIGURACIÓN DEL ÍNDICE

Al crear el índice, configurar automáticamente:

**Campos buscables (searchable) en orden de prioridad:**
1. name
2. sku
3. categories
4. tags
5. brand
6. description
7. attributes
8. variations_text

**Campos filtrables (filterable):**
- categories, category_ids, category_slugs
- tags
- price, price_min, price_max
- stock_status
- on_sale
- featured
- rating
- brand
- type
- attributes (cada atributo como filtro independiente)

**Campos ordenables (sortable):**
- price, price_min, price_max
- date_created, date_modified
- name
- rating
- total_sales
- menu_order

### 5. SINCRONIZACIÓN DE PRODUCTOS

#### Sincronización completa (Bulk)
- Procesar productos en lotes de 100 (configurable)
- Usar Action Scheduler para no bloquear el servidor
- Barra de progreso en tiempo real en el admin vía AJAX polling
- Solo indexar productos publicados y visibles en el catálogo
- Excluir productos según configuración del admin
- Log detallado del proceso

#### Sincronización incremental (Hooks)
Hooks de WooCommerce a escuchar para mantener el índice actualizado:

```php
// Cuando se crea/actualiza un producto
add_action('save_post_product', ...);
add_action('woocommerce_update_product', ...);
add_action('woocommerce_new_product', ...);

// Cuando se elimina un producto
add_action('before_delete_post', ...);
add_action('wp_trash_post', ...);
add_action('untrash_post', ...);

// Cuando cambia el stock
add_action('woocommerce_product_set_stock', ...);
add_action('woocommerce_variation_set_stock', ...);

// Cuando cambia el precio
add_action('woocommerce_product_on_sale_status', ...);

// Cuando cambia la categoría/taxonomía
add_action('set_object_terms', ...);

// Cuando se actualiza metadata/ACF
add_action('updated_post_meta', ...);
add_action('added_post_meta', ...);

// Cuando cambia el estado de publicación
add_action('transition_post_status', ...);
```

Cada hook debe encolar la actualización (no ejecutarla inmediatamente) para evitar múltiples actualizaciones del mismo producto en una sola request. Usar Action Scheduler con un delay de 30 segundos para agrupar cambios.

### 6. PANEL DE ADMINISTRACIÓN

Crear una página de settings bajo WooCommerce > Smart Search con las siguientes pestañas:

#### Pestaña: Conexión
- Selector: Motor de búsqueda (Meilisearch / Typesense)
- Campo: Host (URL del servidor)
- Campo: Puerto
- Campo: Protocolo (http/https)
- Campo: API Key (Master Key para admin)
- Campo: Search API Key (solo lectura, para frontend)
- Campo: Nombre del índice (default: "woo_products")
- Botón: Probar conexión (AJAX, muestra resultado con versión del motor)

#### Pestaña: Indexación
- Botón: Sincronización completa (con confirmación)
- Botón: Sincronización incremental forzada
- Botón: Limpiar índice completo (con confirmación doble)
- Barra de progreso de sincronización actual
- Estadísticas: productos indexados, último sync, tamaño del índice
- Configuración:
  - Tamaño de lote (default: 100)
  - Campos personalizados a indexar (selector multi-select con campos meta disponibles)
  - Categorías a excluir de la indexación
  - Indexar productos agotados (sí/no)
  - Indexar productos ocultos del catálogo (sí/no)

#### Pestaña: Búsqueda
- Configuración de sinónimos (textarea JSON o interfaz visual)
- Configuración de stop words
- Número máximo de resultados en autocompletado (default: 8)
- Número de resultados por página en página de búsqueda (default: 20)
- Habilitar/deshabilitar filtros facetados
- Seleccionar qué filtros mostrar (categorías, precio, stock, atributos, marcas)
- Habilitar/deshabilitar búsqueda por SKU
- Habilitar/deshabilitar productos agotados en resultados

#### Pestaña: Apariencia
- Modo de integración: Reemplazar búsqueda nativa / Solo shortcode
- Posición del widget: header / widget area / shortcode
- Tema visual: Claro / Oscuro / Personalizado
- Colores personalizables:
  - Color primario (botón, resaltado)
  - Color de fondo del dropdown
  - Color de texto
  - Color de borde
- Tamaño de fuente base
- Border radius
- Mostrar/ocultar: imagen, precio, categoría, SKU, stock, rating
- Texto placeholder personalizable
- CSS personalizado (textarea)
- Preview en vivo de los cambios

#### Pestaña: Logs
- Tabla de registros de actividad (sincronizaciones, errores, búsquedas)
- Filtro por tipo (error, info, warning)
- Filtro por fecha
- Botón limpiar logs
- Exportar logs a CSV

### 7. FRONTEND — WIDGET DE BÚSQUEDA

#### Funcionamiento
1. El usuario escribe en la barra de búsqueda
2. Después de 200ms de debounce, se hace una petición al endpoint proxy del plugin
3. El endpoint proxy reenvía la consulta al motor de búsqueda externo
4. Los resultados se muestran en un dropdown debajo de la barra
5. El usuario puede navegar los resultados con teclado (flechas, Enter, Escape)
6. Al hacer clic o presionar Enter en un resultado, va al producto
7. Si presiona Enter sin seleccionar, va a la página de resultados completos

#### Endpoint Proxy REST API

Crear un endpoint REST API en WordPress que actúe como proxy:

```
GET /wp-json/wss/v1/search?q={query}&limit={limit}&filters={filters}&page={page}&sort={sort}
```

Esto es necesario por seguridad para no exponer la API Key del motor de búsqueda al frontend.

Implementar:
- Rate limiting (máximo 30 requests por minuto por IP)
- Cache de resultados con Transients (TTL configurable, default: 5 minutos)
- Sanitización de la query
- Nonce verification para requests autenticados
- CORS headers apropiados

#### HTML del Widget

```html
<div class="wss-search-wrapper" role="search" aria-label="Búsqueda de productos">
    <div class="wss-search-input-container">
        <input 
            type="search" 
            class="wss-search-input" 
            placeholder="Buscar productos..."
            autocomplete="off"
            aria-autocomplete="list"
            aria-controls="wss-results-list"
            aria-expanded="false"
            role="combobox"
        />
        <span class="wss-search-icon"><!-- SVG icon --></span>
        <span class="wss-search-spinner" style="display:none"><!-- Loading SVG --></span>
        <button class="wss-search-clear" style="display:none" aria-label="Limpiar búsqueda">×</button>
    </div>
    
    <div class="wss-results-dropdown" style="display:none" role="listbox" id="wss-results-list">
        <!-- Resultados se insertan dinámicamente -->
        <div class="wss-results-products">
            <!-- Cada resultado: -->
            <a href="{permalink}" class="wss-result-item" role="option">
                <div class="wss-result-image">
                    <img src="{image}" alt="{name}" loading="lazy" />
                </div>
                <div class="wss-result-info">
                    <span class="wss-result-category">{category}</span>
                    <h4 class="wss-result-title">{name con highlighting}</h4>
                    <span class="wss-result-sku">SKU: {sku}</span>
                    <div class="wss-result-price">
                        <span class="wss-price-current">{precio actual}</span>
                        <span class="wss-price-regular">{precio regular tachado si está en oferta}</span>
                        <span class="wss-price-badge">-{porcentaje}%</span>
                    </div>
                    <span class="wss-result-stock wss-stock-{status}">{stock status}</span>
                </div>
            </a>
        </div>
        
        <div class="wss-results-footer">
            <a href="/search?q={query}" class="wss-view-all">
                Ver todos los {total} resultados →
            </a>
        </div>
    </div>
</div>
```

#### JavaScript del Widget (Vanilla JS, sin jQuery)

El JS debe ser ligero (< 15KB minificado) e incluir:

- Debounce de 200ms en el input
- Peticiones fetch al endpoint proxy REST API
- Cancelación de peticiones anteriores con AbortController
- Caché local en memoria para queries recientes
- Navegación por teclado completa:
  - Flechas arriba/abajo: navegar resultados
  - Enter: ir al resultado seleccionado o buscar
  - Escape: cerrar dropdown
  - Tab: cerrar dropdown
- Cerrar dropdown al hacer clic fuera
- Loading spinner mientras carga
- Estado vacío ("No se encontraron resultados para '{query}'")
- Estado de error ("Error de conexión, intenta de nuevo")
- Manejo del highlight (wrapping en <mark>)
- Soporte para query mínima (no buscar con menos de 2 caracteres)
- Analytics: disparar evento personalizado 'wss_search' con la query para que GA/GTM lo capture
- Formateo de precios según configuración de WooCommerce (moneda, decimales, posición)
- Soporte para imágenes lazy loading
- Responsive: dropdown fullscreen en móviles (< 768px)

#### CSS del Widget

- Usar CSS custom properties (variables) para personalización
- Mobile-first responsive design
- Animaciones suaves para dropdown (slideDown, fadeIn)
- Highlight de resultados con color personalizable
- Soporte para temas oscuros
- Z-index alto para el dropdown (99999)
- No usar !important
- Prefijo wss- en todas las clases para evitar conflictos
- Usar contenedor con position: relative para posicionamiento del dropdown

### 8. PÁGINA DE RESULTADOS COMPLETOS

Opcionalmente, el plugin puede reemplazar la página de resultados de búsqueda nativa de WooCommerce con una versión mejorada que:

- Muestre resultados desde el motor externo
- Incluya filtros facetados en sidebar (categorías, rango de precio slider, atributos, stock)
- Soporte paginación
- Soporte ordenamiento (relevancia, precio, fecha, popularidad, rating)
- Layout grid/lista toggleable
- URL amigable con parámetros de búsqueda
- Contador de resultados

### 9. SHORTCODE

```
[woo_smart_search 
    placeholder="Buscar productos..." 
    max_results="8"
    show_image="true"
    show_price="true"
    show_category="true"
    show_sku="false"
    show_stock="true"
    show_rating="false"
    theme="light"
    width="100%"
    categories=""
    exclude_categories=""
]
```

### 10. WIDGET DE WORDPRESS

Registrar un widget de WordPress clásico y un bloque de Gutenberg que permitan insertar la barra de búsqueda en cualquier widget area o dentro del editor de bloques.

### 11. SEGURIDAD

- Sanitizar todas las entradas con sanitize_text_field(), absint(), etc.
- Escapar todas las salidas con esc_html(), esc_attr(), esc_url()
- Usar nonces en todos los formularios y peticiones AJAX del admin
- La Search API Key nunca se expone directamente al frontend; todo pasa por el proxy
- Rate limiting en el endpoint de búsqueda
- Validar capacidades del usuario (manage_woocommerce) para el admin
- Prepared statements para cualquier query directa a la BD
- CORS configurado apropiadamente
- La Master API Key se almacena encriptada en wp_options

### 12. RENDIMIENTO

- Lazy loading de assets: solo cargar JS/CSS en páginas que usan el widget
- Cache de resultados con WordPress Transients API
- Compresión gzip de responses del proxy
- JS y CSS minificados para producción
- Debounce en el frontend para evitar requests excesivos
- Bulk indexing con batches para no agotar memoria del servidor
- Uso de Action Scheduler en vez de WP-Cron para sincronización
- Object cache compatible (Redis/Memcached)

### 13. INTERNACIONALIZACIÓN

- Todo el plugin debe ser traducible con funciones __(), _e(), esc_html__()
- Text domain: 'woo-smart-search'
- Incluir archivo .pot generado
- Incluir traducción al español (es_ES) completa
- Soporte para WooCommerce multi-moneda (detectar moneda activa)
- Soporte para WPML/Polylang (indexar por idioma si está activo)

### 14. COMPATIBILIDAD

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+ (preferiblemente 8.0+)
- Meilisearch v1.0+
- Typesense v0.25+
- Compatible con temas populares: Astra, GeneratePress, OceanWP, Flatsome, Storefront
- Compatible con page builders: Elementor, Divi, WPBakery
- Compatible con plugins de caché: WP Rocket, W3 Total Cache, LiteSpeed Cache
- HPOS (High-Performance Order Storage) compatible

### 15. HOOKS Y FILTROS PARA DESARROLLADORES

Exponer hooks para que otros desarrolladores puedan extender el plugin:

```php
// Filtros
apply_filters('wss_product_document', $document, $product);        // Modificar documento antes de indexar
apply_filters('wss_search_results', $results, $query, $options);   // Modificar resultados
apply_filters('wss_searchable_attributes', $attributes);           // Modificar campos buscables
apply_filters('wss_filterable_attributes', $attributes);           // Modificar campos filtrables
apply_filters('wss_index_settings', $settings);                    // Modificar configuración del índice
apply_filters('wss_product_query_args', $args);                    // Modificar query de productos para sync
apply_filters('wss_search_widget_html', $html, $atts);             // Modificar HTML del widget
apply_filters('wss_result_item_html', $html, $result);             // Modificar HTML de cada resultado
apply_filters('wss_proxy_response', $response, $query);            // Modificar respuesta del proxy
apply_filters('wss_should_index_product', $should_index, $product); // Decidir si indexar un producto
apply_filters('wss_cache_ttl', $ttl, $query);                     // Modificar TTL del caché
apply_filters('wss_rate_limit', $limit);                           // Modificar rate limit
apply_filters('wss_debounce_time', $ms);                           // Modificar debounce del frontend

// Acciones
do_action('wss_before_full_sync');                                 // Antes de sync completa
do_action('wss_after_full_sync', $total_indexed);                  // Después de sync completa
do_action('wss_product_indexed', $product_id, $document);          // Producto indexado
do_action('wss_product_removed', $product_id);                     // Producto removido del índice
do_action('wss_search_performed', $query, $results_count);         // Búsqueda realizada
do_action('wss_index_created', $index_name);                       // Índice creado
do_action('wss_connection_established', $engine_type);             // Conexión establecida
do_action('wss_sync_error', $error, $product_id);                  // Error en sincronización
```

### 16. DEPENDENCIAS PHP (composer.json)

```json
{
    "name": "your-vendor/woo-smart-search",
    "description": "Smart search for WooCommerce with Meilisearch/Typesense",
    "require": {
        "php": ">=7.4",
        "meilisearch/meilisearch-php": "^1.0",
        "typesense/typesense-php": "^4.0"
    },
    "autoload": {
        "psr-4": {
            "WooSmartSearch\\": "includes/"
        }
    }
}
```

### 17. TESTING

- Incluir tests unitarios con PHPUnit para:
  - Transformación de productos a documentos
  - Adapters de Meilisearch y Typesense (con mocks)
  - Endpoint proxy REST API
  - Sanitización y validación de datos
  - Sincronización incremental (hooks)
- Incluir al menos un test de integración básico

### 18. DOCUMENTACIÓN

- PHPDoc en todas las clases y métodos
- README.md con:
  - Descripción del plugin
  - Requisitos
  - Guía de instalación paso a paso
  - Configuración inicial
  - Guía de configuración de Meilisearch en VPS
  - Guía de configuración de Typesense en VPS
  - Referencia de shortcodes
  - Referencia de hooks y filtros
  - FAQ
  - Changelog

---

## INSTRUCCIONES DE IMPLEMENTACIÓN

1. **Comienza por la estructura de archivos y el archivo principal del plugin** con headers correctos de WordPress.

2. **Implementa primero la capa de abstracción** (Interface + Adapters + Factory) para que el resto del plugin sea agnóstico al motor.

3. **Implementa la sincronización de productos**, primero la completa (bulk) y luego la incremental (hooks).

4. **Implementa el panel de administración** con todas las pestañas y funcionalidades.

5. **Implementa el endpoint proxy REST API** con rate limiting y cache.

6. **Implementa el widget de búsqueda frontend** (JS + CSS + HTML).

7. **Implementa el shortcode, widget y bloque de Gutenberg.**

8. **Implementa internacionalización** generando el .pot y la traducción al español.

9. **Implementa los tests.**

10. **Genera la documentación.**

---

## NOTAS IMPORTANTES

- El plugin debe funcionar inmediatamente después de introducir las credenciales de conexión y hacer la primera sincronización.
- El JavaScript del frontend debe ser vanilla JS puro, sin dependencias de jQuery ni frameworks. Debe ser ultra-ligero.
- El CSS no debe usar frameworks externos. Debe ser CSS puro con custom properties.
- Todo el código PHP debe seguir WordPress Coding Standards (WPCS).
- Los nombres de funciones y clases deben usar el prefijo `wss_` para funciones globales y `WSS_` para clases.
- El plugin debe manejar graciosamente la situación donde el motor de búsqueda no está disponible, haciendo fallback a la búsqueda nativa de WooCommerce.
- La sincronización nunca debe bloquear el admin ni causar timeouts.
- El widget debe verse bien con los temas más populares de WooCommerce sin necesidad de CSS adicional.
- Incluir un mecanismo de health check que verifique periódicamente la conexión con el motor y notifique al admin si hay problemas.
