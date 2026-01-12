import { defineConfig } from 'vite';
import uebertool from '@ueberbit/vite-plugin-drupal';

export default defineConfig({
  plugins: [
    uebertool({
      // Auto-generate libraries.yml entries
      libraries: [
        {
          name: 'wallet_auth_connector',
          entry: './src/js/wallet-auth-connector.js',
          loaded: false,
          dependencies: ['core/drupal', 'core/drupalSettings'],
        },
        {
          name: 'wallet_auth_ui',
          entry: './src/js/wallet-auth-ui.js',
          loaded: false,
          dependencies: ['wallet_auth/wallet_auth_connector', 'core/jquery'],
          css: true, // Process CSS files
        },
      ],
    }),
  ],
  build: {
    // Output to module's js/dist directory
    outDir: 'js/dist',
    emptyOutDir: true,
    // Generate UMD for browser compatibility
    lib: {
      formats: ['umd'],
    },
    rollupOptions: {
      // Externalize WaaP SDK (load via CDN or separate library)
      external: ['@human.tech/waap-sdk'],
      output: {
        globals: {
          '@human.tech/waap-sdk': 'WaaP',
        },
      },
    },
  },
});
