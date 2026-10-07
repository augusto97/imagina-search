<template>
  <div>
    <!-- Page & Layout -->
    <div class="wss-section">
      <div class="wss-section-header"><div><h3>{{ t('Page & Layout') }}</h3></div></div>
      <div class="wss-section-body">
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Results Page') }}</div>
          <div class="wss-form-control">
            <el-select v-model="settings.results_page_id" filterable :placeholder="t('Select a page...')">
              <el-option :value="0" :label="t('— Select a page —')" />
              <el-option v-for="p in pages" :key="p.id" :value="p.id" :label="p.title" />
            </el-select>
            <div style="margin-top:6px; font-size:12px; color:#6b7280; line-height:1.5">
              {{ t('Just select any page — the search results will render automatically. No shortcode needed. (Advanced: you can still use') }}
              <code style="background:#f3f4f6;padding:1px 5px;border-radius:3px">[woo_smart_search_results]</code>
              {{ t('to control exact placement.)') }}
            </div>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">
            {{ t('Results Layout') }}
            <span class="wss-hint">{{ t('Desktop layout') }}</span>
          </div>
          <div class="wss-form-control">
            <el-select v-model="settings.results_layout">
              <el-option value="default" :label="t('Default — Clean grid with sidebar')" />
              <el-option value="amazon" :label="t('Amazon — Ratings, Add to Cart')" />
              <el-option value="temu" :label="t('Temu — Vibrant discounts, dense grid')" />
              <el-option value="mercadolibre" :label="t('MercadoLibre — List view, shipping badges')" />
              <el-option value="aliexpress" :label="t('AliExpress — Multi-column, orders count')" />
              <el-option value="shopify" :label="t('Shopify — Minimal, elegant')" />
            </el-select>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">
            {{ t('Mobile Layout') }}
            <span class="wss-hint">{{ t('Layout used on phones (≤768px)') }}</span>
          </div>
          <div class="wss-form-control">
            <el-select v-model="settings.results_layout_mobile">
              <el-option value="same" :label="t('Same as desktop')" />
              <el-option value="default" :label="t('Default — Clean grid')" />
              <el-option value="amazon" :label="t('Amazon — Ratings, Add to Cart')" />
              <el-option value="temu" :label="t('Temu — Vibrant discounts, dense grid')" />
              <el-option value="mercadolibre" :label="t('MercadoLibre — List view, shipping badges')" />
              <el-option value="aliexpress" :label="t('AliExpress — Multi-column, orders count')" />
              <el-option value="shopify" :label="t('Shopify — Minimal, elegant')" />
            </el-select>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Grid Columns') }}</div>
          <div class="wss-form-control">
            <el-radio-group v-model="settings.results_columns">
              <el-radio-button v-for="n in [2,3,4,5]" :key="n" :value="String(n)">{{ n }}</el-radio-button>
            </el-radio-group>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Results Per Page') }}</div>
          <div class="wss-form-control">
            <el-input-number v-model="settings.results_per_page" :min="1" :max="100" />
          </div>
        </div>
      </div>
    </div>

    <!-- Search Behavior -->
    <div class="wss-section">
      <div class="wss-section-header"><div><h3>{{ t('Search Behavior') }}</h3></div></div>
      <div class="wss-section-body">
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Faceted Filters') }}</div>
          <div class="wss-form-control">
            <el-switch v-model="settings.enable_facets" active-value="yes" inactive-value="no" />
            <div style="margin-top:4px; font-size:12px; color:#6b7280">{{ t('Master toggle — turn the entire sidebar on/off.') }}</div>
          </div>
        </div>
        <div class="wss-form-row" v-if="settings.enable_facets === 'yes'">
          <div class="wss-form-label">
            {{ t('Visible Facets') }}
            <span class="wss-hint">{{ t('Which filters appear in the sidebar') }}</span>
          </div>
          <div class="wss-form-control">
            <el-checkbox-group v-model="visibleFacets">
              <el-checkbox v-for="f in facetOptions" :key="f.value" :value="f.value" :label="f.label" style="display:block; margin-bottom:2px" />
            </el-checkbox-group>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Search by SKU') }}</div>
          <div class="wss-form-control">
            <el-switch v-model="settings.search_by_sku" active-value="yes" inactive-value="no" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">
            {{ t('Show Out of Stock') }}
            <span class="wss-hint">
              {{ t('Whether out-of-stock products appear in search results. This is a search-time filter and works even while the products stay indexed (see “Index Out of Stock” on the Indexing tab). Turn off to hide them from shoppers without removing them from the index.') }}
            </span>
          </div>
          <div class="wss-form-control">
            <el-switch v-model="settings.show_out_of_stock_results" active-value="yes" inactive-value="no" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Cache TTL (seconds)') }}</div>
          <div class="wss-form-control">
            <el-input-number v-model="settings.cache_ttl" :min="0" :max="3600" :step="60" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Rate Limit (req/min)') }}</div>
          <div class="wss-form-control">
            <el-input-number v-model="settings.rate_limit" :min="1" :max="200" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-control" style="grid-column: 1 / -1">
            <el-alert
              type="info"
              :closable="false"
              show-icon
              :title="t('Synonyms and stop words are managed in the “Synonyms & Typos” tab.')"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Visible Elements -->
    <div class="wss-section">
      <div class="wss-section-header"><div><h3>{{ t('Visible Elements') }}</h3><p>{{ t('Show or hide elements on each product/post card in results.') }}</p></div></div>
      <div class="wss-section-body">
        <div v-for="el in rpElements" :key="el.key" class="wss-toggle-row">
          <span class="wss-toggle-label">{{ el.label }}</span>
          <el-switch v-model="settings[el.key]" active-value="yes" inactive-value="no" />
        </div>
      </div>
    </div>

    <!-- Card & Colors -->
    <div class="wss-section">
      <div class="wss-section-header"><div><h3>{{ t('Results Page Appearance') }}</h3><p>{{ t('Colors and styles for the search results page.') }}</p></div></div>
      <div class="wss-section-body">
        <div class="wss-form-row" v-for="c in rpColors" :key="c.key">
          <div class="wss-form-label">{{ c.label }}</div>
          <div class="wss-form-control">
            <div class="wss-color-row">
              <el-color-picker v-model="settings[c.key]" />
              <span class="wss-color-hex">{{ settings[c.key] }}</span>
            </div>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Card Shadow') }}</div>
          <div class="wss-form-control">
            <el-select v-model="settings.rp_card_shadow" style="width:160px">
              <el-option value="none" :label="t('None')" />
              <el-option value="subtle" :label="t('Subtle')" />
              <el-option value="medium" :label="t('Medium')" />
              <el-option value="strong" :label="t('Strong')" />
            </el-select>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">
            {{ t('Button Radius (px)') }}
            <span class="wss-hint">{{ t('Add to Cart button corners') }}</span>
          </div>
          <div class="wss-form-control">
            <el-slider v-model.number="buttonRadiusNum" :min="0" :max="30" style="max-width:300px" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Image Ratio') }}</div>
          <div class="wss-form-control">
            <el-select v-model="settings.rp_image_ratio" style="width:200px">
              <el-option value="1:1" :label="t('1:1 — Square')" />
              <el-option value="4:3" :label="t('4:3 — Landscape')" />
              <el-option value="3:4" :label="t('3:4 — Portrait')" />
              <el-option value="16:9" :label="t('16:9 — Wide')" />
              <el-option value="auto" :label="t('Auto — Original')" />
            </el-select>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Image Fit') }}</div>
          <div class="wss-form-control">
            <el-radio-group v-model="settings.rp_image_fit">
              <el-radio-button value="cover">{{ t('Cover') }}</el-radio-button>
              <el-radio-button value="contain">{{ t('Contain') }}</el-radio-button>
            </el-radio-group>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Card Spacing (px)') }}</div>
          <div class="wss-form-control">
            <el-slider v-model.number="cardGapNum" :min="0" :max="48" style="max-width:300px" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Card Radius (px)') }}</div>
          <div class="wss-form-control">
            <el-slider v-model.number="cardRadiusNum" :min="0" :max="30" style="max-width:300px" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Name Lines') }}</div>
          <div class="wss-form-control">
            <el-radio-group v-model="settings.rp_name_lines">
              <el-radio-button v-for="n in ['1','2','3']" :key="n" :value="n">{{ n }}</el-radio-button>
            </el-radio-group>
          </div>
        </div>
      </div>
    </div>

    <!-- Custom CSS -->
    <div class="wss-section">
      <div class="wss-section-header"><div><h3>{{ t('Results Page Custom CSS') }}</h3></div></div>
      <div class="wss-section-body">
        <el-input v-model="settings.rp_custom_css" type="textarea" :rows="4" style="max-width:100%; font-family:monospace" />
      </div>
    </div>

    <el-button type="primary" :loading="saving" @click="handleSave" size="large">{{ t('Save Settings') }}</el-button>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { ElMessage } from 'element-plus';
import { t } from '@/i18n';
import { useSettings } from '@/composables/useSettings';

const { settings, saving, save } = useSettings();
const pages = window.wssAdmin?.pages || [];
const isEcommerce = window.wssAdmin?.isEcommerce !== false;
const isMixed = window.wssAdmin?.isMixed === true;

// Build facet options based on content mode.
const facetOptions = (() => {
  const opts = [
    { value: 'categories', label: t('Categories') },
    { value: 'tags', label: t('Tags') },
  ];
  if (isEcommerce || isMixed) {
    opts.push(
      { value: 'price', label: t('Price') },
      { value: 'stock', label: t('Stock') },
      { value: 'attributes', label: t('Attributes (Color, Size...)') },
      { value: 'brands', label: t('Brands') },
      { value: 'rating', label: t('Rating') }
    );
  }
  if (!isEcommerce || isMixed) {
    opts.push(
      { value: 'post_type', label: t('Content Type') },
      { value: 'author', label: t('Author') }
    );
  }
  return opts;
})();

// visible_facets is stored as an array; ensure it's reactive.
const visibleFacets = computed({
  get: () => Array.isArray(settings.visible_facets) ? settings.visible_facets : [],
  set: (v) => { settings.visible_facets = v; },
});

const buttonRadiusNum = computed({
  get: () => parseInt(settings.rp_button_radius) || 8,
  set: (v) => { settings.rp_button_radius = String(v); },
});

const rpElements = [
  { key: 'rp_show_image', label: t('Product Image') },
  { key: 'rp_show_category', label: t('Category') },
  { key: 'rp_show_price', label: t('Price') },
  { key: 'rp_show_sale_badge', label: t('Sale Badge / Discount') },
  { key: 'rp_show_stock', label: t('Stock Status') },
  { key: 'rp_show_rating', label: t('Rating Stars') },
  { key: 'rp_show_sku', label: t('SKU') },
  { key: 'rp_show_description', label: t('Short Description') },
  { key: 'rp_show_add_to_cart', label: t('Add to Cart Button') },
  { key: 'rp_show_shipping', label: t('Free Shipping Badge') },
  { key: 'rp_show_sold', label: t('Sold Count') },
];

const rpColors = [
  { key: 'rp_card_bg', label: t('Card Background') },
  { key: 'rp_card_border', label: t('Card Border') },
  { key: 'rp_price_color', label: t('Price Color') },
  { key: 'rp_sale_color', label: t('Sale Price Color') },
  { key: 'rp_badge_bg', label: t('Sale Badge BG') },
  { key: 'rp_badge_text', label: t('Sale Badge Text') },
  { key: 'rp_stars_color', label: t('Rating Stars') },
  { key: 'rp_button_bg', label: t('Button BG') },
  { key: 'rp_button_text', label: t('Button Text') },
  { key: 'rp_button_hover_bg', label: t('Button BG (Hover)') },
  { key: 'rp_button_hover_text', label: t('Button Text (Hover)') },
  { key: 'rp_sidebar_bg', label: t('Sidebar BG') },
  { key: 'rp_toolbar_bg', label: t('Toolbar BG') },
  { key: 'rp_page_bg', label: t('Page BG') },
];

const cardGapNum = computed({
  get: () => parseInt(settings.rp_card_gap) || 20,
  set: (v) => { settings.rp_card_gap = String(v); },
});
const cardRadiusNum = computed({
  get: () => parseInt(settings.rp_card_radius) || 8,
  set: (v) => { settings.rp_card_radius = String(v); },
});

async function handleSave() {
  try {
    const msg = await save('search');
    ElMessage.success(msg);
  } catch (e) {
    ElMessage.error(e.message);
  }
}
</script>
