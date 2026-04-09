<template>
  <div>
    <!-- Page & Layout -->
    <div class="wss-section">
      <div class="wss-section-header"><div><h3>Page &amp; Layout</h3></div></div>
      <div class="wss-section-body">
        <div class="wss-form-row">
          <div class="wss-form-label">Results Page</div>
          <div class="wss-form-control">
            <el-select v-model="settings.results_page_id" filterable placeholder="Select a page...">
              <el-option :value="0" label="— Select a page —" />
              <el-option v-for="p in pages" :key="p.id" :value="p.id" :label="p.title" />
            </el-select>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">Results Layout</div>
          <div class="wss-form-control">
            <el-select v-model="settings.results_layout">
              <el-option value="default" label="Default — Clean grid with sidebar" />
              <el-option value="amazon" label="Amazon — Ratings, Add to Cart" />
              <el-option value="temu" label="Temu — Vibrant discounts, dense grid" />
              <el-option value="mercadolibre" label="MercadoLibre — List view, shipping badges" />
              <el-option value="aliexpress" label="AliExpress — Multi-column, orders count" />
              <el-option value="shopify" label="Shopify — Minimal, elegant" />
            </el-select>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">Grid Columns</div>
          <div class="wss-form-control">
            <el-radio-group v-model="settings.results_columns">
              <el-radio-button v-for="n in [2,3,4,5]" :key="n" :value="String(n)">{{ n }}</el-radio-button>
            </el-radio-group>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">Results Per Page</div>
          <div class="wss-form-control">
            <el-input-number v-model="settings.results_per_page" :min="1" :max="100" />
          </div>
        </div>
      </div>
    </div>

    <!-- Search Behavior -->
    <div class="wss-section">
      <div class="wss-section-header"><div><h3>Search Behavior</h3></div></div>
      <div class="wss-section-body">
        <div class="wss-form-row">
          <div class="wss-form-label">Faceted Filters</div>
          <div class="wss-form-control">
            <el-switch v-model="settings.enable_facets" active-value="yes" inactive-value="no" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">Search by SKU</div>
          <div class="wss-form-control">
            <el-switch v-model="settings.search_by_sku" active-value="yes" inactive-value="no" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">Show Out of Stock</div>
          <div class="wss-form-control">
            <el-switch v-model="settings.show_out_of_stock_results" active-value="yes" inactive-value="no" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">Cache TTL (seconds)</div>
          <div class="wss-form-control">
            <el-input-number v-model="settings.cache_ttl" :min="0" :max="3600" :step="60" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">Rate Limit (req/min)</div>
          <div class="wss-form-control">
            <el-input-number v-model="settings.rate_limit" :min="1" :max="200" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">
            Synonyms
            <span class="wss-hint">JSON: {"hoodie": ["sweatshirt"]}</span>
          </div>
          <div class="wss-form-control">
            <el-input v-model="settings.synonyms" type="textarea" :rows="3" style="font-family:monospace" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">
            Stop Words
            <span class="wss-hint">Comma-separated</span>
          </div>
          <div class="wss-form-control">
            <el-input v-model="settings.stop_words" type="textarea" :rows="2" />
          </div>
        </div>
      </div>
    </div>

    <!-- Card & Colors -->
    <div class="wss-section">
      <div class="wss-section-header"><div><h3>Results Page Appearance</h3><p>Colors and styles for the search results page.</p></div></div>
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
          <div class="wss-form-label">Card Shadow</div>
          <div class="wss-form-control">
            <el-select v-model="settings.rp_card_shadow" style="width:160px">
              <el-option value="none" label="None" />
              <el-option value="subtle" label="Subtle" />
              <el-option value="medium" label="Medium" />
              <el-option value="strong" label="Strong" />
            </el-select>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">Image Ratio</div>
          <div class="wss-form-control">
            <el-select v-model="settings.rp_image_ratio" style="width:200px">
              <el-option value="1:1" label="1:1 — Square" />
              <el-option value="4:3" label="4:3 — Landscape" />
              <el-option value="3:4" label="3:4 — Portrait" />
              <el-option value="16:9" label="16:9 — Wide" />
              <el-option value="auto" label="Auto — Original" />
            </el-select>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">Image Fit</div>
          <div class="wss-form-control">
            <el-radio-group v-model="settings.rp_image_fit">
              <el-radio-button value="cover">Cover</el-radio-button>
              <el-radio-button value="contain">Contain</el-radio-button>
            </el-radio-group>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">Card Spacing (px)</div>
          <div class="wss-form-control">
            <el-slider v-model.number="cardGapNum" :min="0" :max="48" style="max-width:300px" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">Card Radius (px)</div>
          <div class="wss-form-control">
            <el-slider v-model.number="cardRadiusNum" :min="0" :max="30" style="max-width:300px" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">Name Lines</div>
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
      <div class="wss-section-header"><div><h3>Results Page Custom CSS</h3></div></div>
      <div class="wss-section-body">
        <el-input v-model="settings.rp_custom_css" type="textarea" :rows="4" style="max-width:100%; font-family:monospace" />
      </div>
    </div>

    <el-button type="primary" :loading="saving" @click="handleSave" size="large">Save Settings</el-button>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { ElMessage } from 'element-plus';
import { useSettings } from '@/composables/useSettings';

const { settings, saving, save } = useSettings();
const pages = window.wssAdmin?.pages || [];

const rpColors = [
  { key: 'rp_card_bg', label: 'Card Background' },
  { key: 'rp_card_border', label: 'Card Border' },
  { key: 'rp_price_color', label: 'Price Color' },
  { key: 'rp_sale_color', label: 'Sale Price Color' },
  { key: 'rp_badge_bg', label: 'Sale Badge BG' },
  { key: 'rp_badge_text', label: 'Sale Badge Text' },
  { key: 'rp_stars_color', label: 'Rating Stars' },
  { key: 'rp_button_bg', label: 'Button BG' },
  { key: 'rp_button_text', label: 'Button Text' },
  { key: 'rp_sidebar_bg', label: 'Sidebar BG' },
  { key: 'rp_toolbar_bg', label: 'Toolbar BG' },
  { key: 'rp_page_bg', label: 'Page BG' },
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
