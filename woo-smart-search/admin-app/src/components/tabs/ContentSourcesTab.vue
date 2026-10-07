<template>
  <div>
    <div class="wss-section">
      <div class="wss-section-header">
        <div>
          <h3>{{ t('Content Source') }}</h3>
          <p>{{ t('Choose what content to index and make searchable.') }}</p>
        </div>
      </div>
      <div class="wss-section-body">
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Source') }}</div>
          <div class="wss-form-control">
            <el-radio-group v-model="settings.content_source">
              <el-radio value="auto" size="large">{{ t('Auto-detect') }}</el-radio>
              <el-radio value="woocommerce" size="large">{{ t('WooCommerce Products') }}</el-radio>
              <el-radio value="wordpress" size="large">{{ t('WordPress Content') }}</el-radio>
              <el-radio value="mixed" size="large">{{ t('Mixed (Both)') }}</el-radio>
            </el-radio-group>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showWpSettings" class="wss-section">
      <div class="wss-section-header">
        <div>
          <h3>{{ t('WordPress Content Settings') }}</h3>
          <p>{{ t('Select which post types and custom fields to index.') }}</p>
        </div>
      </div>
      <div class="wss-section-body">
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Post Types') }}</div>
          <div class="wss-form-control">
            <el-checkbox-group v-model="settings.wp_post_types">
              <el-checkbox
                v-for="pt in postTypes"
                :key="pt.value"
                :value="pt.value"
                :label="pt.label"
              />
            </el-checkbox-group>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">
            {{ t('Custom Fields') }}
            <span class="wss-hint">{{ t('Meta fields to include in the index') }}</span>
          </div>
          <div class="wss-form-control">
            <el-select
              v-model="settings.wp_custom_fields"
              multiple
              filterable
              :placeholder="t('Select custom fields...')"
              style="width: 100%; max-width: 400px"
            >
              <el-option
                v-for="f in customFields"
                :key="f"
                :value="f"
                :label="f"
              />
            </el-select>
          </div>
        </div>
      </div>
    </div>

    <el-button type="primary" :loading="saving" @click="handleSave" size="large">
      {{ t('Save Settings') }}
    </el-button>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { ElMessage } from 'element-plus';
import { t } from '@/i18n';
import { useSettings } from '@/composables/useSettings';

const { settings, saving, save } = useSettings();

const postTypes = window.wssAdmin?.postTypes || [
  { value: 'post', label: t('Posts') },
  { value: 'page', label: t('Pages') },
];

const customFields = window.wssAdmin?.wpCustomFields || [];

const showWpSettings = computed(() =>
  ['wordpress', 'mixed'].includes(settings.content_source)
);

async function handleSave() {
  try {
    const msg = await save('content_sources');
    ElMessage.success(msg);
  } catch (e) {
    ElMessage.error(e.message);
  }
}
</script>
