import { createApp } from 'vue';
import ElementPlus from 'element-plus';
import 'element-plus/dist/index.css';
import App from './App.vue';
import './assets/style.css';

const root = document.getElementById('wss-admin-root');
if (root) {
  const app = createApp(App);
  app.use(ElementPlus, { size: 'default' });
  app.mount(root);
}
