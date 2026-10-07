import { createApp } from 'vue';
import ElementPlus from 'element-plus';
import 'element-plus/dist/index.css';
import es from 'element-plus/es/locale/lang/es';
import App from './App.vue';
import { t } from './i18n';
import './assets/style.css';

const root = document.getElementById('wss-admin-root');
if (root) {
  const app = createApp(App);
  // Element Plus' own texts (tables, pagination, pickers) follow the user's
  // admin language when a translation is bundled.
  const userLocale = (window.wssAdmin && window.wssAdmin.locale) || '';
  app.use(ElementPlus, { size: 'default', ...(userLocale.indexOf('es') === 0 ? { locale: es } : {}) });
  // Available in every template as t().
  app.config.globalProperties.t = t;
  app.mount(root);
}
