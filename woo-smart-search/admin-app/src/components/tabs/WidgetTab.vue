<template>
  <div>
    <!-- Shortcodes & Blocks -->
    <div class="wss-section">
      <div class="wss-section-header"><div><h3>{{ t('Shortcodes & Blocks') }}</h3><p>{{ t('Use these to place the search bar anywhere on your site.') }}</p></div></div>
      <div class="wss-section-body">
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Search Bar') }}</div>
          <div class="wss-form-control">
            <code style="display:inline-block;background:#f3f4f6;padding:6px 14px;border-radius:6px;font-size:13px;user-select:all;cursor:text">[woo_smart_search]</code>
            <div style="margin-top:4px;font-size:12px;color:#6b7280">{{ t('Place the search widget in any page, post, or widget area. Also available as a Gutenberg block:') }} <strong>Woo Smart Search</strong>.</div>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">
            {{ t('Optional Attributes') }}
            <span class="wss-hint">{{ t('All are optional — defaults come from these settings.') }}</span>
          </div>
          <div class="wss-form-control" style="font-size:12px;color:#6b7280;line-height:1.8">
            <code>placeholder</code>, <code>layout</code>, <code>theme</code>, <code>width</code>, <code>max_results</code>, <code>show_image</code>, <code>show_price</code>, <code>show_category</code>, <code>show_icon</code>, <code>icon_position</code><br>
            {{ t('Example:') }} <code style="background:#f3f4f6;padding:2px 6px;border-radius:4px">[woo_smart_search placeholder="Search..." layout="expanded" max_results="6"]</code>
          </div>
        </div>
      </div>
    </div>

    <!-- Integration & Layout -->
    <div class="wss-section">
      <div class="wss-section-header"><div><h3>{{ t('Integration & Layout') }}</h3></div></div>
      <div class="wss-section-body">
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Integration Mode') }}</div>
          <div class="wss-form-control">
            <el-select v-model="settings.integration_mode">
              <el-option value="replace" :label="t('Replace native search')" />
              <el-option value="shortcode" :label="t('Shortcode only')" />
            </el-select>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Widget Layout') }}
            <span class="wss-hint">{{ t('Desktop layout.') }}</span>
          </div>
          <div class="wss-form-control">
            <el-select v-model="settings.widget_layout">
              <el-option value="standard" :label="t('Standard — Vertical list')" />
              <el-option value="expanded" :label="t('Expanded — Two columns')" />
              <el-option value="compact" :label="t('Compact — No images')" />
              <el-option value="amazon" :label="t('Amazon — Text suggestions')" />
              <el-option value="falabella" :label="t('Multi-column — Columns layout')" />
              <el-option value="fullscreen" :label="t('Fullscreen — Overlay')" />
            </el-select>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Mobile Widget Layout') }}
            <span class="wss-hint">{{ t('Layout used on phones (≤767px). Choose “Same as desktop” to follow the layout above, or pick a different one just for mobile.') }}</span>
          </div>
          <div class="wss-form-control">
            <el-select v-model="settings.widget_layout_mobile">
              <el-option value="same" :label="t('Same as desktop')" />
              <el-option value="standard" :label="t('Standard — Vertical list')" />
              <el-option value="expanded" :label="t('Expanded — Two columns')" />
              <el-option value="compact" :label="t('Compact — No images')" />
              <el-option value="amazon" :label="t('Amazon — Text suggestions')" />
              <el-option value="falabella" :label="t('Multi-column — Columns layout')" />
              <el-option value="fullscreen" :label="t('Fullscreen — Overlay')" />
            </el-select>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Autocomplete Results') }}</div>
          <div class="wss-form-control">
            <el-input-number v-model="settings.max_autocomplete_results" :min="1" :max="20" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Placeholder Text') }}</div>
          <div class="wss-form-control">
            <el-input v-model="settings.placeholder_text" :placeholder="t('Search products...')" />
          </div>
        </div>
      </div>
    </div>

    <!-- Theme & Colors -->
    <div class="wss-section">
      <div class="wss-section-header"><div><h3>{{ t('Theme & Colors') }}</h3></div></div>
      <div class="wss-section-body">
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Theme') }}</div>
          <div class="wss-form-control">
            <el-radio-group v-model="settings.theme">
              <el-radio-button value="light">{{ t('Light') }}</el-radio-button>
              <el-radio-button value="dark">{{ t('Dark') }}</el-radio-button>
              <el-radio-button value="custom">{{ t('Custom') }}</el-radio-button>
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
          <div class="wss-form-label">{{ t('Font Size (px)') }}</div>
          <div class="wss-form-control">
            <el-slider v-model.number="fontSizeNum" :min="10" :max="24" :show-tooltip="true" style="max-width:300px" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Border Radius (px)') }}</div>
          <div class="wss-form-control">
            <el-slider v-model.number="borderRadiusNum" :min="0" :max="30" :show-tooltip="true" style="max-width:300px" />
          </div>
        </div>
      </div>
    </div>

    <!-- Visible Elements -->
    <div class="wss-section">
      <div class="wss-section-header"><div><h3>{{ t('Visible Elements') }}</h3></div></div>
      <div class="wss-section-body">
        <div v-for="el in visibleElements" :key="el.key" class="wss-toggle-row">
          <span class="wss-toggle-label">{{ el.label }}</span>
          <el-switch v-model="settings[el.key]" active-value="yes" inactive-value="no" />
        </div>
      </div>
    </div>

    <!-- Custom CSS -->
    <div class="wss-section">
      <div class="wss-section-header"><div><h3>{{ t('Custom CSS') }}</h3></div></div>
      <div class="wss-section-body">
        <el-input v-model="settings.custom_css" type="textarea" :rows="5" :placeholder="t('/* Your custom CSS */')" style="max-width: 100%; font-family: monospace" />
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

const widgetColors = [
  { key: 'primary_color', label: t('Primary Color') },
  { key: 'bg_color', label: t('Background Color') },
  { key: 'text_color', label: t('Text Color') },
  { key: 'border_color', label: t('Border Color') },
];

const visibleElements = [
  { key: 'show_image', label: t('Featured Image') },
  { key: 'show_category', label: t('Categories') },
  { key: 'show_price', label: t('Price') },
  { key: 'show_sku', label: t('SKU') },
  { key: 'show_stock', label: t('Stock Status') },
  { key: 'show_rating', label: t('Rating') },
  { key: 'show_sale_badge', label: t('Sale Badge') },
  { key: 'show_excerpt', label: t('Excerpt / Description') },
  { key: 'show_author', label: t('Author') },
  { key: 'show_date', label: t('Date') },
  { key: 'show_post_type', label: t('Post Type Badge') },
  { key: 'enable_analytics', label: t('Enable Analytics') },
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
