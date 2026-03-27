import path from 'node:path';
import { defineConfig, type UserConfig } from 'vite';
import { glob } from 'tinyglobby';
import { componentMapPlugin } from './build/component-map-plugin';
import { devImportMapPlugin } from './build/dev-import-map-plugin';
import { extensionModuleResolverPlugin } from './build/extension-module-resolver-plugin';

export const componentRoot = path.resolve(import.meta.dirname, '../../views/components');

// Allow the dev server to serve files from the Resources/ tree and the
// project root (needed for /@fs/ URLs to extension component sources).
const resourcesRoot = path.resolve(import.meta.dirname, '../..'); // Resources/
const projectRoot = path.resolve(import.meta.dirname, '../../../../../'); // repo root

export async function buildComponentEntries(): Promise<Record<string, string>> {
    const files = await glob('**/*.{js,ts}', {
        cwd: componentRoot,
        ignore: ['**/*.test.{js,ts}', '**/*.stories.*'],
    });

    return Object.fromEntries(
        files.map(file => [
            // Key mirrors the source path without extension, preserving directory structure.
            file.replace(/\.(js|ts)$/, ''),
            path.join(componentRoot, file),
        ]),
    );
}

export default defineConfig(async ({ command }): Promise<UserConfig> => {
    const entries = await buildComponentEntries();
    const isServe = command === 'serve';
    return {
        build: {
            outDir: 'dist-es/components',
            emptyOutDir: true,
            manifest: true,
            sourcemap: process.env.NODE_ENV !== 'production',
            rolldownOptions: {
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
            devImportMapPlugin(projectRoot),
            extensionModuleResolverPlugin(projectRoot),
        ],
        resolve: {
            alias: isServe ? {
                // In dev server mode resolve 'shopware' to the actual source file
                // so Vite can transform /@fs/ component files that import from it.
                // In production builds 'shopware' stays external (resolved via
                // the runtime import map).
                shopware: path.resolve(import.meta.dirname, 'src/shopware.ts'),
            } : {},
        },
        server: {
            port: Number(process.env.STOREFRONT_COMPONENTS_VITE_PORT ?? 5175),
            cors: true,
            fs: {
                // Allow Vite to serve component sources from any bundle under
                // the project root via the /@fs/ prefix.
                allow: [resourcesRoot, projectRoot],
            },
        },
    };
});
