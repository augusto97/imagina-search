import { reactive, ref } from 'vue';
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

export function useSettings() {
  const { post } = useApi();

  function load() {
    if (loaded.value) return;
    const initial = window.wssAdmin?.settings || {};
    Object.assign(settings, initial);
    loaded.value = true;
  }

  async function save(tab) {
    saving.value = true;
    try {
      const payload = { _wss_tab: tab, ...settings };
      const res = await post('wss_save_settings', payload);
      if (!res.success) throw new Error(res.data?.message || 'Save failed');
      return res.data?.message || 'Settings saved.';
    } finally {
      saving.value = false;
    }
  }

  return { settings, loaded, saving, load, save };
}
