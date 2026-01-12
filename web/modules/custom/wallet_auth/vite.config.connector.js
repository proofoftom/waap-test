import { defineConfig } from 'vite';
import { nodePolyfills } from 'vite-plugin-node-polyfills';

export default defineConfig({
  plugins: [
    nodePolyfills({
      // Whether to polyfill specific globals.
      globals: {
        Buffer: true, // can also be 'build', 'dev', or false
        global: true,
        process: true,
      },
      // Whether to polyfill `process` on other globals.
      process: true,
    }),
  ],
  build: {
    lib: {
      entry: './src/js/wallet-auth-connector.js',
      name: 'WalletAuthConnector',
      formats: ['iife'],
      fileName: (format) => `wallet-auth-connector.js`,
    },
    outDir: 'js/dist',
    emptyOutDir: false,
  },
});
