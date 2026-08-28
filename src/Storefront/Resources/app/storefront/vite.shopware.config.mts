import { defineConfig } from 'vite';

/**
 * Vite build config for the Shopware runtime module.
 *
 * Produces a single ES module at Resources/public/storefront/shopware/shopware.js that:
 * - Exports ShopwareComponent and Shopware as named ES module exports.
 * - Assigns both to window as a side effect for backward compatibility.
 */
export default defineConfig({
    build: {
        outDir: '../../public/storefront/shopware',
        emptyOutDir: true,
        lib: {
            entry: './src/shopware.ts',
            formats: ['es'],
            fileName: () => 'shopware.js',
        },
    },
});
