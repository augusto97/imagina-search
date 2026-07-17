import { reactive, ref, watch } from 'vue';
import { useApi } from './useApi';

/**
 * Global settings state shared across all tabs.
 *
 * Settings are loaded once from wssAdmin.settings (injected by PHP)
 * and saved via the existing wss_save_settings AJAX endpoint.
 */
const settings = reactive({});
const loaded = ref(false);
const saving = ref(false);
const dirty = ref(false);
let watching = false;

export function useSettings() {
  const { post } = useApi();

  function load() {
    if (loaded.value) return;
    const initial = window.wssAdmin?.settings || {};
    Object.assign(settings, initial);

    // Defaults for keys that may be absent on installs upgraded from older
    // versions, so selects show a sensible value instead of blank.
    if (settings.results_layout_mobile === undefined) settings.results_layout_mobile = 'same';
    if (settings.widget_layout_mobile === undefined) settings.widget_layout_mobile = 'same';
    if (settings.local_fuzzy === undefined) settings.local_fuzzy = 'yes';

    // Coerce numeric selects to numbers — legacy values saved by the old
    // jQuery admin may be strings, and el-select compares with ===.
    ['reindex_interval', 'results_page_id', 'batch_size', 'max_autocomplete_results',
      'results_per_page', 'cache_ttl', 'rate_limit'].forEach((key) => {
      if (typeof settings[key] === 'string' && settings[key] !== '') {
        settings[key] = parseInt(settings[key], 10) || 0;
      }
    });

    loaded.value = true;

    // Track unsaved changes. Registered after the initial assign — Vue
    // watchers flush async, so the load itself never marks the state dirty.
    if (!watching) {
      watching = true;
      watch(settings, () => { dirty.value = true; }, { deep: true });
    }
  }

  async function save(tab, extra = {}) {
    saving.value = true;
    try {
      const payload = { _wss_tab: tab, ...settings, ...extra };
      const res = await post('wss_save_settings', payload);
      if (!res.success) throw new Error(res.data?.message || 'Save failed');
      dirty.value = false;
      return res.data?.message || 'Settings saved.';
    } finally {
      saving.value = false;
    }
  }

  return { settings, loaded, saving, dirty, load, save };
}
