<template>
  <div class="wss-app">
    <!-- Sidebar -->
    <aside class="wss-sidebar">
      <div class="wss-sidebar-brand">
        <h2>Smart Search</h2>
        <div class="wss-version">v{{ version }}</div>
      </div>
      <nav class="wss-sidebar-nav">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          class="wss-nav-item"
          :class="{ active: activeTab === tab.id }"
          @click="activeTab = tab.id"
        >
          <span class="nav-icon">
            <el-icon><component :is="tab.icon" /></el-icon>
          </span>
          {{ tab.label }}
        </button>
      </nav>
    </aside>

    <!-- Main -->
    <div class="wss-main">
      <!-- Header -->
      <header class="wss-header">
        <h1>{{ currentTab.label }}</h1>
        <div class="wss-header-actions">
          <span>
            <span class="wss-status-dot" :class="connectionStatus" />
            {{ connectionLabel }}
          </span>
        </div>
      </header>

      <!-- Content -->
      <div class="wss-content">
        <ConnectionTab      v-if="activeTab === 'connection'" />
        <ContentSourcesTab  v-if="activeTab === 'content_sources'" />
        <IndexingTab        v-if="activeTab === 'indexing'" />
        <WidgetTab          v-if="activeTab === 'appearance'" />
        <ResultsPageTab     v-if="activeTab === 'search'" />
        <TranslationsTab    v-if="activeTab === 'translations'" />
        <AnalyticsTab       v-if="activeTab === 'analytics'" />
        <LogsTab            v-if="activeTab === 'logs'" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import {
  Link, DataBoard, Refresh, Setting, Brush,
  Document, EditPen, DataAnalysis, Tickets,
} from '@element-plus/icons-vue';
import { useApi } from '@/composables/useApi';
import { useSettings } from '@/composables/useSettings';

import ConnectionTab from '@/components/tabs/ConnectionTab.vue';
import ContentSourcesTab from '@/components/tabs/ContentSourcesTab.vue';
import IndexingTab from '@/components/tabs/IndexingTab.vue';
import WidgetTab from '@/components/tabs/WidgetTab.vue';
import ResultsPageTab from '@/components/tabs/ResultsPageTab.vue';
import TranslationsTab from '@/components/tabs/TranslationsTab.vue';
import AnalyticsTab from '@/components/tabs/AnalyticsTab.vue';
import LogsTab from '@/components/tabs/LogsTab.vue';

const { post } = useApi();
const { load } = useSettings();

const version = window.wssAdmin?.version || '5.2.0';

const tabs = [
  { id: 'connection',      label: 'Connection',      icon: Link },
  { id: 'content_sources', label: 'Content Sources',  icon: DataBoard },
  { id: 'indexing',        label: 'Indexing',         icon: Refresh },
  { id: 'appearance',      label: 'Widget',           icon: Brush },
  { id: 'search',          label: 'Results Page',     icon: Document },
  { id: 'translations',    label: 'Translations',     icon: EditPen },
  { id: 'analytics',       label: 'Analytics',        icon: DataAnalysis },
  { id: 'logs',            label: 'Logs',             icon: Tickets },
];

const activeTab = ref('connection');
const connectionStatus = ref('idle');
const connectionLabel = ref('Checking...');

const currentTab = computed(() => tabs.find((t) => t.id === activeTab.value) || tabs[0]);

onMounted(async () => {
  load();
  try {
    const res = await post('wss_get_connection_status');
    if (res.success && res.data) {
      const d = res.data;
      if (d.status === 'connected') {
        connectionStatus.value = 'connected';
        let info = d.version ? `v${d.version}` : '';
        if (d.documents !== undefined) info += (info ? ', ' : '') + `${d.documents} docs`;
        connectionLabel.value = `Connected${info ? ` (${info})` : ''}`;
      } else if (d.status === 'not_configured') {
        connectionStatus.value = 'idle';
        connectionLabel.value = 'Not Configured';
      } else {
        connectionStatus.value = 'error';
        connectionLabel.value = d.message || 'Error';
      }
    }
  } catch {
    connectionStatus.value = 'error';
    connectionLabel.value = 'Connection error';
  }
});
</script>
