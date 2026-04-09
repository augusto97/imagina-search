<template>
  <div>
    <!-- Integration & Layout -->
    <div class="wss-section">
      <div class="wss-section-header"><div><h3>Integration &amp; Layout</h3></div></div>
      <div class="wss-section-body">
        <div class="wss-form-row">
          <div class="wss-form-label">Integration Mode</div>
          <div class="wss-form-control">
            <el-select v-model="settings.integration_mode">
              <el-option value="replace" label="Replace native search" />
              <el-option value="shortcode" label="Shortcode only" />
            </el-select>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">Widget Layout</div>
          <div class="wss-form-control">
            <el-select v-model="settings.widget_layout">
              <el-option value="standard" label="Standard — Vertical list" />
              <el-option value="expanded" label="Expanded — Two columns" />
              <el-option value="compact" label="Compact — No images" />
              <el-option value="amazon" label="Amazon — Text suggestions" />
              <el-option value="falabella" label="Multi-column — Columns layout" />
              <el-option value="fullscreen" label="Fullscreen — Overlay" />
            </el-select>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">Autocomplete Results</div>
          <div class="wss-form-control">
            <el-input-number v-model="settings.max_autocomplete_results" :min="1" :max="20" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">Placeholder Text</div>
          <div class="wss-form-control">
            <el-input v-model="settings.placeholder_text" placeholder="Search products..." />
          </div>
        </div>
      </div>
    </div>

    <!-- Theme & Colors -->
    <div class="wss-section">
      <div class="wss-section-header"><div><h3>Theme &amp; Colors</h3></div></div>
      <div class="wss-section-body">
        <div class="wss-form-row">
          <div class="wss-form-label">Theme</div>
          <div class="wss-form-control">
            <el-radio-group v-model="settings.theme">
              <el-radio-button value="light">Light</el-radio-button>
              <el-radio-button value="dark">Dark</el-radio-button>
              <el-radio-button value="custom">Custom</el-radio-button>
            </el-radio-group>
          </div>
        </div>
        <div class="wss-form-row" v-for="c in widgetColors" :key="c.key">
          <div class="wss-form-label">{{ c.label }}</div>
          <div class="wss-form-control">
            <div class="wss-color-row">
              <el-color-picker v-model="settings[c.key]" />
              <span class="wss-color-hex">{{ settings[c.key] }}</span>
            </div>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">Font Size (px)</div>
          <div class="wss-form-control">
            <el-slider v-model.number="fontSizeNum" :min="10" :max="24" :show-tooltip="true" style="max-width:300px" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">Border Radius (px)</div>
          <div class="wss-form-control">
            <el-slider v-model.number="borderRadiusNum" :min="0" :max="30" :show-tooltip="true" style="max-width:300px" />
          </div>
        </div>
      </div>
    </div>

    <!-- Visible Elements -->
    <div class="wss-section">
      <div class="wss-section-header"><div><h3>Visible Elements</h3></div></div>
      <div class="wss-section-body">
        <div v-for="el in visibleElements" :key="el.key" class="wss-toggle-row">
          <span class="wss-toggle-label">{{ el.label }}</span>
          <el-switch v-model="settings[el.key]" active-value="yes" inactive-value="no" />
        </div>
      </div>
    </div>

    <!-- Custom CSS -->
    <div class="wss-section">
      <div class="wss-section-header"><div><h3>Custom CSS</h3></div></div>
      <div class="wss-section-body">
        <el-input v-model="settings.custom_css" type="textarea" :rows="5" placeholder="/* Your custom CSS */" style="max-width: 100%; font-family: monospace" />
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

const widgetColors = [
  { key: 'primary_color', label: 'Primary Color' },
  { key: 'bg_color', label: 'Background Color' },
  { key: 'text_color', label: 'Text Color' },
  { key: 'border_color', label: 'Border Color' },
];

const visibleElements = [
  { key: 'show_image', label: 'Featured Image' },
  { key: 'show_category', label: 'Categories' },
  { key: 'show_price', label: 'Price' },
  { key: 'show_sku', label: 'SKU' },
  { key: 'show_stock', label: 'Stock Status' },
  { key: 'show_rating', label: 'Rating' },
  { key: 'show_sale_badge', label: 'Sale Badge' },
  { key: 'show_excerpt', label: 'Excerpt / Description' },
  { key: 'show_author', label: 'Author' },
  { key: 'show_date', label: 'Date' },
  { key: 'show_post_type', label: 'Post Type Badge' },
  { key: 'enable_analytics', label: 'Enable Analytics' },
];

const fontSizeNum = computed({
  get: () => parseInt(settings.font_size) || 14,
  set: (v) => { settings.font_size = String(v); },
});

const borderRadiusNum = computed({
  get: () => parseInt(settings.border_radius) || 8,
  set: (v) => { settings.border_radius = String(v); },
});

async function handleSave() {
  try {
    const msg = await save('appearance');
    ElMessage.success(msg);
  } catch (e) {
    ElMessage.error(e.message);
  }
}
</script>
