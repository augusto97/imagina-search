<template>
  <div>
    <!-- Sync Status -->
    <div class="wss-stats-grid">
      <div class="wss-stat-card">
        <div class="stat-value">{{ stats.published ?? '—' }}</div>
        <div class="stat-label">Published</div>
      </div>
      <div class="wss-stat-card">
        <div class="stat-value">{{ stats.indexed ?? '—' }}</div>
        <div class="stat-label">Indexed</div>
      </div>
      <div class="wss-stat-card">
        <div class="stat-value">{{ stats.lastSync || 'Never' }}</div>
        <div class="stat-label">Last Sync</div>
      </div>
    </div>

    <!-- Sync Actions -->
    <div class="wss-section">
      <div class="wss-section-header">
        <div>
          <h3>Sync Actions</h3>
          <p>Re-index your content or clear the search index.</p>
        </div>
      </div>
      <div class="wss-section-body">
        <div style="display: flex; gap: 12px; margin-bottom: 16px">
          <el-button type="primary" :loading="syncing" @click="startSync">
            <el-icon><Refresh /></el-icon>&nbsp;Full Sync
          </el-button>
          <el-button type="danger" plain @click="clearIndex">
            <el-icon><Delete /></el-icon>&nbsp;Clear Index
          </el-button>
        </div>

        <!-- Progress -->
        <div v-if="syncing" class="wss-progress-wrap">
          <el-progress :percentage="progress" :stroke-width="8" />
          <div class="wss-progress-text">{{ progressText }}</div>
        </div>
      </div>
    </div>

    <!-- Indexing Settings -->
    <div class="wss-section">
      <div class="wss-section-header">
        <div>
          <h3>Indexing Settings</h3>
        </div>
      </div>
      <div class="wss-section-body">
        <div class="wss-form-row">
          <div class="wss-form-label">Batch Size</div>
          <div class="wss-form-control">
            <el-input-number v-model="settings.batch_size" :min="10" :max="500" :step="10" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">
            Auto Re-index Interval
            <span class="wss-hint">How often to rescan all content. Use shorter intervals if products are updated via external API or direct DB.</span>
          </div>
          <div class="wss-form-control">
            <el-select v-model="settings.reindex_interval" style="width:280px">
              <el-option :value="0" label="Disabled" />
              <el-option :value="5" label="Every 5 minutes" />
              <el-option :value="15" label="Every 15 minutes" />
              <el-option :value="30" label="Every 30 minutes" />
              <el-option :value="60" label="Every 1 hour" />
              <el-option :value="120" label="Every 2 hours" />
              <el-option :value="360" label="Every 6 hours (default)" />
              <el-option :value="720" label="Every 12 hours" />
              <el-option :value="1440" label="Every 24 hours" />
            </el-select>
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">
            Index Out of Stock
            <span class="wss-hint">
              On: out-of-stock products stay in the index (control their
              visibility with “Show Out of Stock” on the Results Page tab).
              Off: they are removed from the index and re-added automatically
              when restocked — keeps the index smaller on large catalogs.
            </span>
          </div>
          <div class="wss-form-control">
            <el-switch v-model="settings.index_out_of_stock" active-value="yes" inactive-value="no" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-label">
            Index Hidden Products
            <span class="wss-hint">
              Include products whose WooCommerce catalog visibility is
              “Hidden”. Off by default; when off, hidden products are removed
              from the index.
            </span>
          </div>
          <div class="wss-form-control">
            <el-switch v-model="settings.index_hidden" active-value="yes" inactive-value="no" />
          </div>
        </div>
        <div v-if="productCategories.length" class="wss-form-row">
          <div class="wss-form-label">
            Exclude Product Categories
            <span class="wss-hint">Products in these categories will not be indexed</span>
          </div>
          <div class="wss-form-control">
            <el-select
              v-model="settings.exclude_categories"
              multiple
              filterable
              placeholder="Select categories..."
              style="width: 100%; max-width: 400px"
            >
              <el-option
                v-for="cat in productCategories"
                :key="cat.id"
                :value="cat.id"
                :label="cat.name"
              />
            </el-select>
          </div>
        </div>
        <div v-if="productMetaKeys.length" class="wss-form-row">
          <div class="wss-form-label">
            Product Custom Fields
            <span class="wss-hint">Product meta / ACF fields to include</span>
          </div>
          <div class="wss-form-control">
            <el-select
              v-model="settings.custom_fields"
              multiple
              filterable
              placeholder="Select fields..."
              style="width: 100%; max-width: 400px"
            >
              <el-option v-for="k in productMetaKeys" :key="k" :value="k" :label="k" />
            </el-select>
          </div>
        </div>

        <!-- Dynamic taxonomy exclusions -->
        <div v-for="(tax, slug) in wpTaxonomies" :key="slug" class="wss-form-row">
          <div class="wss-form-label">
            Exclude {{ tax.label }}
            <span class="wss-hint">Content with these will not be indexed</span>
          </div>
          <div class="wss-form-control">
            <el-select
              v-model="excludeTax[slug]"
              multiple
              filterable
              :placeholder="`Select ${tax.label}...`"
              style="width: 100%; max-width: 400px"
            >
              <el-option
                v-for="term in tax.terms"
                :key="term.id"
                :value="term.id"
                :label="term.name"
              />
            </el-select>
          </div>
        </div>
      </div>
    </div>

    <el-button type="primary" :loading="saving" @click="handleSave" size="large">
      Save Settings
    </el-button>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue';
import { Refresh, Delete } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useSettings } from '@/composables/useSettings';
import { useApi } from '@/composables/useApi';

const { settings, saving, save } = useSettings();
const { post } = useApi();

const adminData = window.wssAdmin || {};
const productCategories = adminData.productCategories || [];
const productMetaKeys = adminData.productMetaKeys || [];
const wpTaxonomies = adminData.wpTaxonomies || {};

const stats = reactive({ published: adminData.published, indexed: '—', lastSync: adminData.lastSync });
const syncing = ref(false);
const progress = ref(0);
const progressText = ref('');
const excludeTax = reactive(settings.exclude_taxonomies || {});

let pollTimer = null;

onMounted(async () => {
  try {
    const res = await post('wss_get_index_stats');
    if (res.success) stats.indexed = res.data.numberOfDocuments ?? 0;
  } catch { /* ignore */ }
});

onUnmounted(() => { if (pollTimer) clearInterval(pollTimer); });

async function startSync() {
  try {
    await ElMessageBox.confirm('This will re-index all content. Continue?', 'Full Sync', { type: 'warning' });
  } catch { return; }

  syncing.value = true;
  progress.value = 0;
  progressText.value = 'Starting...';

  try {
    await post('wss_full_sync');
    await pumpSync();
  } catch (e) {
    ElMessage.error(e.message);
    syncing.value = false;
  }
}

// Drive the batches from the browser instead of waiting for Action Scheduler /
// WP-Cron. Each call processes one batch server-side and returns progress, so a
// Full Sync completes even on sites where scheduled tasks are not running (which
// is exactly when it used to sit stuck at 0%). Keeping the admin tab open is all
// that is required.
async function pumpSync() {
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  let guard = 0;      // hard cap on iterations (very large catalogs)
  let stalls = 0;     // consecutive errors / no-progress
  let last = -1;

  while (guard++ < 20000) {
    let res;
    try {
      res = await post('wss_run_sync_batch');
    } catch {
      if (++stalls > 20) break;
      await sleep(2000);
      continue;
    }

    if (!res || !res.success || !res.data) {
      if (++stalls > 20) break;
      await sleep(1000);
      continue;
    }

    const d = res.data;

    if (d.status === 'failed') {
      syncing.value = false;
      progressText.value = 'Failed';
      ElMessage.error('Sync failed — check the Logs tab for details.');
      return;
    }

    if (d.status !== 'running') {
      progress.value = 100;
      progressText.value = 'Completed';
      syncing.value = false;
      ElMessage.success('Sync completed');
      try {
        const sr = await post('wss_get_index_stats');
        if (sr.success) stats.indexed = sr.data.numberOfDocuments ?? 0;
      } catch { /* ignore */ }
      return;
    }

    const pct = d.total > 0 ? Math.round((d.processed / d.total) * 100) : 0;
    progress.value = pct;
    progressText.value = `${pct}% (${d.processed}/${d.total})`;

    // Detect a genuine stall (another process holds the lock, or no advance).
    if (d.processed === last) {
      if (++stalls > 40) {
        syncing.value = false;
        ElMessage.warning('Sync is not advancing — check the Logs tab.');
        return;
      }
      await sleep(1500);
    } else {
      stalls = 0;
      last = d.processed;
    }
  }

  syncing.value = false;
}

async function clearIndex() {
  try {
    await ElMessageBox.confirm('This will delete ALL indexed data. Are you sure?', 'Clear Index', { type: 'error' });
  } catch { return; }

  try {
    await post('wss_clear_index');
    ElMessage.success('Index cleared');
    stats.indexed = 0;
  } catch (e) {
    ElMessage.error(e.message);
  }
}

async function handleSave() {
  try {
    settings.exclude_taxonomies = { ...excludeTax };
    const msg = await save('indexing');
    ElMessage.success(msg);
  } catch (e) {
    ElMessage.error(e.message);
  }
}
</script>
