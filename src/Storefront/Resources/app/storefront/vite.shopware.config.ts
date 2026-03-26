import { defineConfig } from 'vite';

/**
 * Vite build config for the Shopware runtime module.
 *
 * Produces a single ES module at dist/shopware/shopware.js that:
 * - Exports ShopwareComponent and Shopware as named ES module exports.
 * - Assigns both to window as a side effect for backward compatibility.
 *
 * This directory is exclusively owned by this build — emptyOutDir: true is safe.
 */
export default defineConfig({
    build: {
        outDir: 'dist-es/shopware',
        emptyOutDir: true,
        lib: {
            entry: './src/shopware.ts',
            formats: ['es'],
            fileName: () => 'shopware.js',
        },
    },
});
