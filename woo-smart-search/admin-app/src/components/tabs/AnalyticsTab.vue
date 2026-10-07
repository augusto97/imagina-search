<template>
  <div>
    <!-- Stats Cards -->
    <div class="wss-stats-grid">
      <div class="wss-stat-card">
        <div class="stat-value">{{ data.totals?.today ?? '—' }}</div>
        <div class="stat-label">{{ t('Today') }}</div>
      </div>
      <div class="wss-stat-card">
        <div class="stat-value">{{ data.totals?.week ?? '—' }}</div>
        <div class="stat-label">{{ t('This Week') }}</div>
      </div>
      <div class="wss-stat-card">
        <div class="stat-value">{{ data.totals?.month ?? '—' }}</div>
        <div class="stat-label">{{ t('This Month') }}</div>
      </div>
      <div class="wss-stat-card">
        <div class="stat-value">{{ ctr }}</div>
        <div class="stat-label">{{ t('Click-Through Rate') }}</div>
      </div>
    </div>

    <!-- Top Queries -->
    <div class="wss-section">
      <div class="wss-section-header"><div><h3>{{ t('Top Search Queries') }}</h3></div></div>
      <div class="wss-section-body" style="padding:0">
        <el-table :data="data.top_queries || []" stripe style="width:100%" :empty-text="t('No data yet')">
          <el-table-column type="index" label="#" width="50" />
          <el-table-column prop="query" :label="t('Query')" />
          <el-table-column prop="count" :label="t('Count')" width="100" sortable />
          <el-table-column prop="last_searched" :label="t('Last Searched')" width="180" />
        </el-table>
      </div>
    </div>

    <!-- Zero Results -->
    <div class="wss-section">
      <div class="wss-section-header"><div><h3>{{ t('Searches with No Results') }}</h3><p>{{ t('Consider adding synonyms or content for these queries.') }}</p></div></div>
      <div class="wss-section-body" style="padding:0">
        <el-table :data="data.zero_result_queries || []" stripe style="width:100%" :empty-text="t('No data yet')">
          <el-table-column type="index" label="#" width="50" />
          <el-table-column prop="query" :label="t('Query')" />
          <el-table-column prop="count" :label="t('Count')" width="100" sortable />
          <el-table-column prop="last_searched" :label="t('Last Searched')" width="180" />
        </el-table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, computed, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';

const { post } = useApi();
const data = reactive({});

const ctr = computed(() => {
  const rate = data.click_through_rate;
  return rate !== undefined ? `${rate}%` : '—';
});

onMounted(async () => {
  try {
    const res = await post('wss_get_analytics', { period: 'week' });
    if (res.success) Object.assign(data, res.data);
  } catch { /* ignore */ }
});
</script>
