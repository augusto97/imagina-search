<template>
  <div>
    <!-- Engine Selector -->
    <div class="wss-section">
      <div class="wss-section-header">
        <div>
          <h3>{{ t('Search Engine') }}</h3>
          <p>{{ t('Choose between Meilisearch (cloud/self-hosted) or the built-in local engine.') }}</p>
        </div>
      </div>
      <div class="wss-section-body">
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Engine') }}</div>
          <div class="wss-form-control">
            <el-radio-group v-model="settings.search_engine">
              <el-radio-button value="meilisearch">Meilisearch</el-radio-button>
              <el-radio-button value="local">{{ t('Local Engine') }}</el-radio-button>
            </el-radio-group>
          </div>
        </div>
      </div>
    </div>

    <!-- Meilisearch Config -->
    <div v-if="settings.search_engine !== 'local'" class="wss-section">
      <div class="wss-section-header">
        <div>
          <h3>{{ t('Meilisearch Connection') }}</h3>
          <p>{{ t('Enter your Meilisearch server details.') }}</p>
        </div>
        <el-button type="primary" :loading="testing" @click="testConnection">
          {{ testing ? t('Testing...') : t('Test Connection') }}
        </el-button>
      </div>
      <div class="wss-section-body">
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Protocol') }}</div>
          <div class="wss-form-control">
            <el-select v-model="settings.protocol" style="width: 120px">
              <el-option value="http" label="HTTP" />
              <el-option value="https" label="HTTPS" />
            </el-select>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Host') }}</div>
          <div class="wss-form-control">
            <el-input v-model="settings.host" placeholder="localhost" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">
            {{ t('Port') }}
            <span class="wss-hint">{{ t('Leave empty for default') }}</span>
          </div>
          <div class="wss-form-control">
            <el-input v-model="settings.port" placeholder="7700" style="width: 120px" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Admin API Key') }}</div>
          <div class="wss-form-control">
            <el-input v-model="apiKey" type="password" show-password :placeholder="settings.has_api_key ? t('•••••••• (saved — leave empty to keep it)') : t('Master or Admin API key')" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">
            {{ t('Search API Key') }}
            <span class="wss-hint">{{ t('Public key for frontend direct search (optional)') }}</span>
          </div>
          <div class="wss-form-control">
            <el-input v-model="settings.search_api_key" :placeholder="t('Search-only API key')" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Index Name') }}</div>
          <div class="wss-form-control">
            <el-input v-model="settings.index_name" placeholder="woo_products" style="width: 250px" />
          </div>
        </div>
      </div>
      <div v-if="testResult" style="padding: 0 20px 16px">
        <el-alert :type="testResult.type" :title="testResult.msg" show-icon :closable="false" />
      </div>
    </div>

    <!-- Local Engine Config -->
    <div v-if="settings.search_engine === 'local'" class="wss-section">
      <div class="wss-section-header">
        <div>
          <h3>{{ t('Local Engine') }}</h3>
          <p>{{ t('MySQL-based search engine — no external dependencies.') }}</p>
        </div>
      </div>
      <div class="wss-section-body">
        <div class="wss-form-row">
          <div class="wss-form-label">{{ t('Index Name') }}</div>
          <div class="wss-form-control">
            <el-input v-model="settings.index_name" placeholder="woo_products" style="width: 250px" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">
            {{ t('Cache') }}
            <span class="wss-hint">{{ t('Frequent queries are cached for near-instant responses') }}</span>
          </div>
          <div class="wss-form-control">
            <div class="wss-cache-stats">
              <span v-if="cacheStats">
                <strong>{{ cacheStats.entries }}</strong>
                {{ cacheStats.entries === 1 ? t('cached query') : t('cached queries') }}
                <span class="wss-hint">({{ cacheStats.size }})</span>
              </span>
              <span v-else class="wss-hint">{{ t('Loading cache stats…') }}</span>
            </div>
            <el-button @click="purgeCache" :loading="purging" size="small">{{ t('Purge Cache') }}</el-button>
          </div>
        </div>
      </div>
    </div>

    <!-- Save -->
    <el-button type="primary" :loading="saving" @click="handleSave" size="large">
      {{ t('Save Settings') }}
    </el-button>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { t } from '@/i18n';
import { useSettings } from '@/composables/useSettings';
import { useApi } from '@/composables/useApi';

const { settings, saving, save } = useSettings();
const { post } = useApi();

const apiKey = ref('');
const testing = ref(false);
const testResult = ref(null);
const purging = ref(false);
const cacheStats = ref(null);

async function loadCacheStats() {
  try {
    const res = await post('wss_get_cache_stats');
    if (res.success && res.data) cacheStats.value = res.data;
  } catch { /* non-critical — leave stats hidden */ }
}

onMounted(loadCacheStats);

async function testConnection() {
  testing.value = true;
  testResult.value = null;
  try {
    const res = await post('wss_test_connection', {
      engine: settings.search_engine,
      host: settings.host,
      port: settings.port,
      protocol: settings.protocol,
      api_key: apiKey.value,
    });
    if (res.success) {
      testResult.value = { type: 'success', msg: t('Connected — Meilisearch v%s', res.data.version) };
    } else {
      testResult.value = { type: 'error', msg: res.data?.message || t('Connection failed') };
    }
  } catch (e) {
    testResult.value = { type: 'error', msg: e.message };
  } finally {
    testing.value = false;
  }
}

async function purgeCache() {
  purging.value = true;
  try {
    await post('wss_purge_search_cache');
    ElMessage.success(t('Cache purged'));
    loadCacheStats();
  } catch {
    ElMessage.error(t('Failed to purge cache'));
  } finally {
    purging.value = false;
  }
}

async function handleSave() {
  try {
    // Pass the API key as extra payload — it must NOT live in the shared
    // settings store (it's encrypted server-side and never echoed back).
    const extra = apiKey.value ? { api_key: apiKey.value } : {};
    const msg = await save('connection', extra);
    if (apiKey.value) {
      apiKey.value = '';
      settings.has_api_key = true;
    }
    ElMessage.success(msg);
  } catch (e) {
    ElMessage.error(e.message);
  }
}
</script>

<style scoped>
.wss-cache-stats {
  margin-bottom: 8px;
  color: var(--el-text-color-primary);
}
</style>
