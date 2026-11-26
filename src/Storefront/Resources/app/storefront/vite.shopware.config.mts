import path from 'node:path';
import { defineConfig } from 'vite';
import { storefrontSbomPlugin } from './build/vite/storefront-sbom.mjs';

/**
 * Vite build config for the Shopware runtime module.
 *
 * Produces a single ES module at Resources/public/storefront/shopware/shopware.js that:
 * - Exports ShopwareComponent and Shopware as named ES module exports.
 * - Assigns both to window as a side effect for backward compatibility.
 */
export default defineConfig({
    plugins: [storefrontSbomPlugin({
        bundleName: 'Storefront',
        bundleRoot: path.resolve(import.meta.dirname, '../../..'),
        target: 'shopware',
    })],
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
