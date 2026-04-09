import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
  plugins: [vue()],
  build: {
    outDir: resolve(__dirname, '../assets/admin-app'),
    emptyOutDir: true,
    rollupOptions: {
      input: resolve(__dirname, 'src/main.js'),
      output: {
        entryFileNames: 'js/wss-admin.js',
        chunkFileNames: 'js/wss-admin-[name].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'css/wss-admin.css';
          }
          return 'assets/[name][extname]';
        },
      },
    },
    // Produce a single JS + CSS bundle for WordPress enqueuing.
    cssCodeSplit: false,
    sourcemap: false,
    minify: 'esbuild',
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
});
