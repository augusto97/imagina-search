<template>
  <div>
    <!-- Engine Selector -->
    <div class="wss-section">
      <div class="wss-section-header">
        <div>
          <h3>Search Engine</h3>
          <p>Choose between Meilisearch (cloud/self-hosted) or the built-in local engine.</p>
        </div>
      </div>
      <div class="wss-section-body">
        <div class="wss-form-row">
          <div class="wss-form-label">Engine</div>
          <div class="wss-form-control">
            <el-radio-group v-model="settings.search_engine">
              <el-radio-button value="meilisearch">Meilisearch</el-radio-button>
              <el-radio-button value="local">Local Engine</el-radio-button>
            </el-radio-group>
          </div>
        </div>
      </div>
    </div>

    <!-- Meilisearch Config -->
    <div v-if="settings.search_engine !== 'local'" class="wss-section">
      <div class="wss-section-header">
        <div>
          <h3>Meilisearch Connection</h3>
          <p>Enter your Meilisearch server details.</p>
        </div>
        <el-button type="primary" :loading="testing" @click="testConnection">
          {{ testing ? 'Testing...' : 'Test Connection' }}
        </el-button>
      </div>
      <div class="wss-section-body">
        <div class="wss-form-row">
          <div class="wss-form-label">Protocol</div>
          <div class="wss-form-control">
            <el-select v-model="settings.protocol" style="width: 120px">
              <el-option value="http" label="HTTP" />
              <el-option value="https" label="HTTPS" />
            </el-select>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">Host</div>
          <div class="wss-form-control">
            <el-input v-model="settings.host" placeholder="localhost" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">
            Port
            <span class="wss-hint">Leave empty for default</span>
          </div>
          <div class="wss-form-control">
            <el-input v-model="settings.port" placeholder="7700" style="width: 120px" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">Admin API Key</div>
          <div class="wss-form-control">
            <el-input v-model="apiKey" type="password" show-password placeholder="Master or Admin API key" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">
            Search API Key
            <span class="wss-hint">Public key for frontend direct search (optional)</span>
          </div>
          <div class="wss-form-control">
            <el-input v-model="settings.search_api_key" placeholder="Search-only API key" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">Index Name</div>
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
          <h3>Local Engine</h3>
          <p>MySQL-based search engine — no external dependencies.</p>
        </div>
      </div>
      <div class="wss-section-body">
        <div class="wss-form-row">
          <div class="wss-form-label">Index Name</div>
          <div class="wss-form-control">
            <el-input v-model="settings.index_name" placeholder="woo_products" style="width: 250px" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">Cache</div>
          <div class="wss-form-control">
            <el-button @click="purgeCache" :loading="purging" size="small">Purge Cache</el-button>
          </div>
        </div>
      </div>
    </div>

    <!-- Save -->
    <el-button type="primary" :loading="saving" @click="handleSave" size="large">
      Save Settings
    </el-button>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { ElMessage } from 'element-plus';
import { useSettings } from '@/composables/useSettings';
import { useApi } from '@/composables/useApi';

const { settings, saving, save } = useSettings();
const { post } = useApi();

const apiKey = ref('');
const testing = ref(false);
const testResult = ref(null);
const purging = ref(false);

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
      testResult.value = { type: 'success', msg: `Connected — Meilisearch v${res.data.version}` };
    } else {
      testResult.value = { type: 'error', msg: res.data?.message || 'Connection failed' };
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
    ElMessage.success('Cache purged');
  } catch {
    ElMessage.error('Failed to purge cache');
  } finally {
    purging.value = false;
  }
}

async function handleSave() {
  try {
    const payload = { ...settings };
    if (apiKey.value) payload.api_key = apiKey.value;
    const msg = await save('connection');
    ElMessage.success(msg);
  } catch (e) {
    ElMessage.error(e.message);
  }
}
</script>
