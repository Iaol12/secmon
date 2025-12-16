import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'
import { fileURLToPath } from 'url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

export default defineConfig({
  plugins: [react()],
  server: {
    port: 3000,
    open: true,
    proxy: {
      '/api': {
        target: 'https://localhost:8443',
        changeOrigin: true,
        secure: false,
        rejectUnauthorized: false,
        configure: (proxy, options) => {
          proxy.on('error', (err, _req, res) => {
            console.log('proxy error', err);
            res.writeHead(500, {
              'Content-Type': 'text/plain',
            });
            res.end('Proxy error: ' + err.message);
          });
          proxy.on('proxyReq', (proxyReq, req, _res) => {
            proxyReq.setHeader('Authorization', 'Bearer MEsrdn-6wf7bP-nT8mVJ5YCorYGb1D3m');
            console.log('Sending Request to the Target:', req.method, req.url);
          });
          proxy.on('proxyRes', (proxyRes, req, _res) => {
            console.log('Received Response from the Target:', proxyRes.statusCode, req.url);
          });
        },
      },
      '/data': {
        target: 'https://localhost:8443',
        changeOrigin: true,
        secure: false,
      }
    },
  },
  build: {
      outDir: path.resolve(__dirname, '../web/js/dist'),
      emptyOutDir: true,
      rollupOptions: {
        input: path.resolve(__dirname, './index.html'),
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
    }
})