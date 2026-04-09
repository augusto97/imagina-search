<template>
  <div>
    <!-- Toolbar -->
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px; flex-wrap:wrap">
      <el-select v-model="logType" style="width:150px" @change="loadLogs(1)">
        <el-option value="" label="All types" />
        <el-option value="info" label="Info" />
        <el-option value="warning" label="Warning" />
        <el-option value="error" label="Error" />
      </el-select>
      <el-button @click="loadLogs(currentPage)">
        <el-icon><Refresh /></el-icon>&nbsp;Refresh
      </el-button>
      <el-button @click="exportLogs">Export CSV</el-button>
      <el-button type="danger" plain @click="clearLogs">Clear All</el-button>
    </div>

    <!-- Table -->
    <div class="wss-section">
      <div class="wss-section-body" style="padding:0">
        <el-table :data="logs" stripe v-loading="loading" style="width:100%" empty-text="No log entries">
          <el-table-column label="Type" width="90">
            <template #default="{ row }">
              <span class="wss-log-badge" :class="row.type">{{ row.type }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="message" label="Message" />
          <el-table-column prop="created_at" label="Date" width="170" />
        </el-table>
      </div>
    </div>

    <!-- Pagination -->
    <div style="display:flex; justify-content:center; margin-top:16px">
      <el-pagination
        v-if="totalPages > 1"
        :current-page="currentPage"
        :page-count="totalPages"
        layout="prev, pager, next"
        @current-change="loadLogs"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Refresh } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useApi } from '@/composables/useApi';

const { post } = useApi();

const logs = ref([]);
const loading = ref(false);
const logType = ref('');
const currentPage = ref(1);
const totalPages = ref(1);

onMounted(() => loadLogs(1));

async function loadLogs(page) {
  loading.value = true;
  currentPage.value = page;
  try {
    const res = await post('wss_get_logs', { log_type: logType.value, page });
    if (res.success) {
      logs.value = res.data.logs || [];
      totalPages.value = res.data.pages || 1;
    }
  } catch { /* ignore */ }
  loading.value = false;
}

async function clearLogs() {
  try {
    await ElMessageBox.confirm('Delete all log entries?', 'Clear Logs', { type: 'warning' });
    await post('wss_clear_logs');
    ElMessage.success('Logs cleared');
    loadLogs(1);
  } catch { /* cancelled */ }
}

async function exportLogs() {
  try {
    const res = await post('wss_export_logs');
    if (res.success && res.data?.csv) {
      const blob = new Blob([res.data.csv], { type: 'text/csv' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `wss-logs-${new Date().toISOString().split('T')[0]}.csv`;
      a.click();
      URL.revokeObjectURL(url);
    }
  } catch {
    ElMessage.error('Export failed');
  }
}
</script>
