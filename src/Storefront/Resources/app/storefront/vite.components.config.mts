import path from 'node:path';
import { defineConfig, type UserConfig } from 'vite';
import { buildComponentEntries, buildComponentStyleEntries } from './build/vite/component-entries';
import { componentMapPlugin } from './build/vite/component-map-plugin';
import { devImportMapPlugin } from './build/vite/dev-import-map-plugin';
import { devServerNoticePlugin } from './build/vite/dev-server-notice-plugin';
import { extensionModuleResolverPlugin } from './build/vite/extension-module-resolver-plugin';
import { plainCssShimPlugin } from './build/vite/plain-css-shim-plugin';
import { scopedSubpathExportsPlugin } from './build/vite/scoped-subpath-exports-plugin';
import { themeScssWatcherPlugin } from './build/vite/theme-scss-watcher-plugin';

// Allow the dev server to serve files from the Resources/ tree and the
// project root (needed for /@fs/ URLs to extension component sources).
const resourcesRoot = path.resolve(import.meta.dirname, '../..'); // Resources/
const projectRoot = process.env.PROJECT_ROOT
    ? path.resolve(process.env.PROJECT_ROOT)
    : path.resolve(import.meta.dirname, '../../../../../');

/**
 * SCSS load paths made available to every component stylesheet.
 *
 * vendor/  — exposes Bootstrap SCSS so component files can write e.g.
 *            `@use 'bootstrap/scss/variables' as *` to access $font-size-lg etc.
 * src/scss/ — exposes Shopware skin abstracts so component files can write
 *             `@use 'skin/shopware/abstract/variables/bootstrap' as *` etc.
 *
 * Theme-specific SCSS variables ($sw-color-brand-primary etc.) are intentionally
 * NOT injected here.  Components must use CSS custom properties (var(--sw-*))
 * for runtime-customisable values.
 */
const scssLoadPaths = [
    path.resolve(import.meta.dirname, 'vendor'),
    path.resolve(import.meta.dirname, 'src/scss'),
];

export default defineConfig(async ({ command }): Promise<UserConfig> => {
    const jsEntries = await buildComponentEntries();
    const { scssEntries, plainCssEntries, plainCssShims } = await buildComponentStyleEntries();
    const entries = { ...jsEntries, ...scssEntries, ...plainCssEntries };
    const isServe = command === 'serve';
    return {
        // Public URL base for built assets. Component chunks are served via
        // Shopware's standard bundle asset pipeline from
        // `/bundles/storefront/storefront/components/<chunkPath>`. Rolldown's preload-helper uses this
        // prefix when it emits `<link rel="modulepreload">` tags for the
        // transitive dependencies of a dynamic `import()`. Without it the
        // preload links would resolve to `/vendor/<chunk>.js` and 404, which
        // then rejects the dynamic import via `vite:preloadError`.
        //
        // The manifest.json `file` entries stay relative to `outDir`. Static
        // imports between chunks are emitted as relative URLs and are also
        // unaffected by `base`. In dev mode (command === 'serve') we keep the
        // root base so `/@fs/` URLs from devImportMapPlugin stay clean.
        base: isServe ? '/' : '/bundles/storefront/storefront/components/',
        css: {
            preprocessorOptions: {
                scss: {
                    loadPaths: scssLoadPaths,
                },
            },
        },
        build: {
            outDir: '../../public/storefront/components',
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
                    // Preserve directory structure with a content hash for cache busting.
                    entryFileNames: '[name]-[hash].js',
                    // All vendor chunks go into a flat vendor/ directory with a content hash.
                    chunkFileNames: 'vendor/[name]-[hash].js',
                    // SCSS entry keys include the .scss extension (e.g. 'Sw/Button/Primary.scss')
                    // to avoid collisions with same-named JS entries. Rolldown appends .css to
                    // produce the asset name ('Sw/Button/Primary.scss.css'), so we strip the
                    // embedded .scss before composing the final filename. Plain-CSS entries go
                    // through the virtual-module shim (see plainCssShimPlugin) and don't need
                    // any suffix massaging — Vite derives a clean asset name from the entry key.
                    assetFileNames: (info) => {
                        const firstName = info.names[0] ?? 'asset.css';
                        if (firstName.endsWith('.scss.css')) {
                            return `${firstName.replace(/\.scss\.css$/, '')}-[hash][extname]`;
                        }
                        return '[name]-[hash][extname]';
                    },
                },
            },
        },
        plugins: [
            componentMapPlugin(),
            devImportMapPlugin(projectRoot, scssLoadPaths),
            devServerNoticePlugin(),
            scopedSubpathExportsPlugin(path.resolve(import.meta.dirname, 'node_modules')),
            extensionModuleResolverPlugin(projectRoot),
            plainCssShimPlugin(plainCssShims),
            themeScssWatcherPlugin(projectRoot),
        ],
        resolve: {
            alias: {
                // Mirror webpack's resolve.alias so that main.js and plugin entries
                // that use bare 'src/…', 'scss/…', 'assets/…', 'vendor/…' imports
                // resolve correctly when served by the Vite dev server.
                src:    path.resolve(import.meta.dirname, 'src'),
                assets: path.resolve(import.meta.dirname, 'assets'),
                scss:   path.resolve(import.meta.dirname, 'src/scss'),
                vendor: path.resolve(import.meta.dirname, 'vendor'),
                // In dev server mode resolve 'shopware' to the actual source file
                // so Vite can transform /@fs/ component files that import from it.
                // In production builds 'shopware' stays external (resolved via
                // the runtime import map).
                ...(isServe ? { shopware: path.resolve(import.meta.dirname, 'src/shopware.ts') } : {}),
            },
        },
        server: {
            port: Number(process.env.STOREFRONT_VITE_PORT ?? 5175),
            cors: true,
            fs: {
                // Allow Vite to serve component sources from any bundle under
                // the project root via the /@fs/ prefix.
                allow: [resourcesRoot, projectRoot],
            },
        },
    };
});
