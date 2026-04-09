/**
 * WordPress AJAX API wrapper.
 *
 * Reuses the existing wssAdmin global injected by PHP via wp_localize_script.
 */
export function useApi() {
  const cfg = window.wssAdmin || {};

  async function post(action, data = {}) {
    const body = new URLSearchParams();
    body.append('action', action);
    body.append('nonce', cfg.nonce || '');

    // Flatten data into FormData-compatible key=value pairs.
    flattenToForm(body, data);

    const res = await fetch(cfg.ajaxUrl || '/wp-admin/admin-ajax.php', {
      method: 'POST',
      credentials: 'same-origin',
      body,
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  }

  return { post, cfg };
}

/**
 * Recursively flatten an object into URLSearchParams, handling arrays and nested objects.
 */
function flattenToForm(params, obj, prefix = '') {
  for (const [key, value] of Object.entries(obj)) {
    const fullKey = prefix ? `${prefix}[${key}]` : key;

    if (value === null || value === undefined) {
      continue;
    } else if (Array.isArray(value)) {
      value.forEach((v) => params.append(`${fullKey}[]`, v));
    } else if (typeof value === 'object') {
      flattenToForm(params, value, fullKey);
    } else {
      params.append(fullKey, value);
    }
  }
}
