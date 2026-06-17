import { createApp } from 'vue';
import ElementPlus from 'element-plus';
import 'element-plus/dist/index.css';
import App from './App.vue';
import './assets/style.css';

const root = document.getElementById('wss-admin-root');
if (root) {
  // WordPress renders admin notices (.notice, .updated, .error) inside
  // the .wrap div that also contains our mount point. Move them ABOVE
  // the panel so they don't render inside the sidebar / Vue layout.
  const wrap = root.closest('.wrap');
  if (wrap) {
    wrap.querySelectorAll(':scope > .notice, :scope > .updated, :scope > .error, :scope > .is-dismissible').forEach((el) => {
      wrap.parentNode.insertBefore(el, wrap);
    });
  }

  const app = createApp(App);
  app.use(ElementPlus, { size: 'default' });
  app.mount(root);
}
