/**
 * Admin UI translations.
 *
 * Strings are written in English in the components and wrapped in t().
 * scripts/extract-i18n.mjs collects every literal t() call into
 * includes/admin/admin-app-strings.php, where each one goes through
 * WordPress' __(), so they are translated with the plugin's regular
 * .po/.mo files (or Loco Translate) and arrive here via wssAdmin.i18n.
 *
 * Only literal strings can be extracted (a quoted string as first argument;
 * not a variable). Use %s placeholders for the dynamic parts.
 */
const dict = (typeof window !== 'undefined' && window.wssAdmin && window.wssAdmin.i18n) || {};

export function t(text, ...args) {
  let out = Object.prototype.hasOwnProperty.call(dict, text) && dict[text] ? dict[text] : text;
  args.forEach((arg) => {
    out = out.replace(/%s|%d/, String(arg));
  });
  return out;
}

export default t;
