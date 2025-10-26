import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  plugins: [react()],
  root: './web/react',
  base: '/js/dist/',
  build: {
    outDir: path.resolve(__dirname, 'web/js/dist'),
    emptyOutDir: true,
    rollupOptions: {
      input: path.resolve(__dirname, 'web/react/index.html'),
      output: {
        entryFileNames: 'dashboard-bundle.js',
        chunkFileNames: '[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          // Keep CSS filename consistent for easy inclusion
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'dashboard-bundle.css';
          }
          return '[name]-[hash].[ext]';
        }
      }
    },
    sourcemap: true
  },
  resolve: {
    extensions: ['.js', '.jsx', '.json']
  },
  server: {
    port: 9000,
    open: false,
    cors: true,
    hmr: true
  }
});
