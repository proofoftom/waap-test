import { defineConfig } from 'vite';

export default defineConfig({
  build: {
    lib: {
      entry: './src/js/wallet-auth-ui.js',
      name: 'WalletAuthUI',
      formats: ['iife'],
      fileName: (format) => `wallet-auth-ui.js`,
    },
    outDir: 'js/dist',
    emptyOutDir: false,
    rollupOptions: {
      // Don't bundle jQuery, Drupal, drupalSettings - they're loaded separately
      external: ['jQuery', 'Drupal', 'drupalSettings'],
      output: {
        // Map the external modules to global variable names
        globals: {
          'jQuery': 'jQuery',
          'Drupal': 'Drupal',
          'drupalSettings': 'drupalSettings',
        },
      },
    },
  },
});
