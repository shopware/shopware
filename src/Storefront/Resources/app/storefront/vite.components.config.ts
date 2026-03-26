import path from 'node:path';
import { defineConfig, type UserConfig } from 'vite';
import { glob } from 'tinyglobby';
import { componentMapPlugin } from './build/component-map-plugin';

const componentRoot = path.resolve(import.meta.dirname, '../../views/components');

export default defineConfig(async (): Promise<UserConfig> => {
    const files = await glob('**/*.{js,ts}', {
        cwd: componentRoot,
        ignore: ['**/*.test.{js,ts}', '**/*.stories.*'],
    });

    const entries = Object.fromEntries(
        files.map(file => [
            // Key mirrors the source path without extension, preserving directory structure.
            file.replace(/\.(js|ts)$/, ''),
            path.join(componentRoot, file),
        ]),
    );

    return {
        build: {
            outDir: 'dist-es/components',
            emptyOutDir: true,
            manifest: true,
            sourcemap: process.env.NODE_ENV !== 'production',
            rollupOptions: {
                input: entries,
                // Keep all exports on entry chunks even though nothing inside the build imports
                // them — they are consumed at runtime via dynamic import() by the Shopware
                // component registry.
                preserveEntrySignatures: 'exports-only',
                // 'shopware' is a singleton resolved via import map at runtime — never bundle it.
                external: ['shopware'],
                output: {
                    format: 'es',
                    // Preserve directory structure: Sw/Product/Listing.js
                    entryFileNames: '[name].js',
                    // All vendor chunks go into a flat vendor/ directory with a content hash.
                    chunkFileNames: 'vendor/[name]-[hash].js',
                },
            },
        },
        plugins: [
            componentMapPlugin(),
        ],
    };
});
