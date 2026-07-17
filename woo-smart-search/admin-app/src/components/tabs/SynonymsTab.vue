<template>
  <div>
    <!-- Typo tolerance (local engine) -->
    <div class="wss-section">
      <div class="wss-section-header">
        <div>
          <h3>Typo Tolerance</h3>
          <p>
            Find products even when the shopper mistypes a letter — e.g.
            "pantlon" or "pantilon" still match "pantalón".
          </p>
        </div>
      </div>
      <div class="wss-section-body">
        <div class="wss-form-row">
          <div class="wss-form-label">
            Enable fuzzy matching
            <span class="wss-hint">Local engine only — Meilisearch is typo-tolerant by default</span>
          </div>
          <div class="wss-form-control">
            <el-switch v-model="fuzzyOn" active-text="On" inactive-text="Off" />
          </div>
        </div>
        <div class="wss-form-row">
          <div class="wss-form-control" style="grid-column: 1 / -1">
            <el-alert
              type="info"
              :closable="false"
              show-icon
              title="Only runs on searches that would otherwise return nothing, so correctly spelled searches are not slowed down. Tolerance scales with word length: 1 typo for 5–8 letters, 2 for 9+."
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Synonyms -->
    <div class="wss-section">
      <div class="wss-section-header">
        <div>
          <h3>Synonyms</h3>
          <p>
            Make a word also match other terms. Searching the word on the left
            returns products containing any of the terms on the right
            (comma-separated).
          </p>
        </div>
        <el-button @click="addRow" size="small">
          <el-icon><Plus /></el-icon>&nbsp;Add synonym
        </el-button>
      </div>
      <div class="wss-section-body">
        <div v-if="rows.length === 0" class="wss-empty">
          No synonyms yet. Click “Add synonym” to create one — for example:
          <strong>pantalón</strong> → <em>jean, pantalones</em>.
        </div>

        <div v-for="(row, i) in rows" :key="i" class="wss-syn-row">
          <el-input
            v-model="row.word"
            placeholder="Word (e.g. pantalón)"
            class="wss-syn-word"
          />
          <el-icon class="wss-syn-arrow"><Right /></el-icon>
          <el-input
            v-model="row.terms"
            placeholder="Equivalent terms, comma-separated (e.g. jean, pantalones)"
            class="wss-syn-terms"
          />
          <el-button
            type="danger"
            plain
            size="small"
            circle
            @click="removeRow(i)"
          >
            <el-icon><Delete /></el-icon>
          </el-button>
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
import { ref, computed } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus, Delete, Right } from '@element-plus/icons-vue';
import { useSettings } from '@/composables/useSettings';

const { settings, saving, save } = useSettings();

// Fuzzy toggle maps the 'yes'/'no' string setting to a boolean switch.
const fuzzyOn = computed({
  get: () => settings.local_fuzzy !== 'no',
  set: (v) => { settings.local_fuzzy = v ? 'yes' : 'no'; },
});

// Parse the stored synonyms JSON ({ word: [terms] }) into editable rows.
function parseSynonyms() {
  let obj = {};
  const raw = settings.synonyms;
  if (raw && typeof raw === 'string') {
    try { obj = JSON.parse(raw) || {}; } catch { obj = {}; }
  } else if (raw && typeof raw === 'object') {
    obj = raw;
  }
  return Object.entries(obj).map(([word, terms]) => ({
    word,
    terms: Array.isArray(terms) ? terms.join(', ') : String(terms),
  }));
}

const rows = ref(parseSynonyms());

function addRow() {
  rows.value.push({ word: '', terms: '' });
}

function removeRow(i) {
  rows.value.splice(i, 1);
}

// Build the { word: [terms] } map that the backend/engines expect.
function serialize() {
  const obj = {};
  for (const row of rows.value) {
    const word = (row.word || '').trim();
    if (!word) continue;
    const list = (row.terms || '')
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean);
    if (list.length === 0) continue;
    obj[word] = list;
  }
  return Object.keys(obj).length ? JSON.stringify(obj) : '';
}

async function handleSave() {
  try {
    settings.synonyms = serialize();
    if (settings.local_fuzzy === undefined) settings.local_fuzzy = 'yes';
    const msg = await save('synonyms');
    ElMessage.success(msg);
  } catch (e) {
    ElMessage.error(e.message);
  }
}
</script>

<style scoped>
.wss-syn-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 10px;
}
.wss-syn-word {
  flex: 0 0 240px;
}
.wss-syn-terms {
  flex: 1 1 auto;
}
.wss-syn-arrow {
  color: var(--el-text-color-secondary);
  flex: 0 0 auto;
}
.wss-empty {
  padding: 8px 0 16px;
  color: var(--el-text-color-secondary);
}
</style>
