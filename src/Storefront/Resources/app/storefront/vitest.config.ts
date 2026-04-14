import path from 'node:path';
import fs from 'node:fs';
import { mergeConfig, defineConfig } from 'vitest/config';
import { buildComponentEntries } from './vite.components.config';
import { componentMapPlugin } from './build/component-map-plugin';
import { extensionModuleResolverPlugin } from './build/extension-module-resolver-plugin';

// Allow vitest's Vite dev server to serve files from the views/ tree that lives
// two levels above the app/storefront package root.
const resourcesRoot = path.resolve(__dirname, '../..');

// Project root is five levels up (storefront → app → Resources → Storefront → src → root).
const projectRoot = path.resolve(__dirname, '../../../../../');

type BundleEntry = {
    basePath?: string;
};

const COMPONENTS_PATH = 'Resources/views/components';

/**
 * Collect the views/components root for every bundle that has one in
 * var/plugins.json.  Falls back to the core Storefront path only when the
 * plugins manifest is absent (e.g. during a fresh checkout before bundle:dump).
 */
function resolveComponentRoots(): string[] {
    const pluginsJson = path.join(projectRoot, 'var/plugins.json');

    if (!fs.existsSync(pluginsJson)) {
        // Fallback: core only (relative path from config file directory).
        return ['../../views/components'];
    }

    const plugins = JSON.parse(fs.readFileSync(pluginsJson, 'utf-8')) as Record<string, BundleEntry>;

    return Object.values(plugins)
        .filter(bundle => {
            const absRoot = path.join(projectRoot, bundle.basePath ?? '', COMPONENTS_PATH);
            return fs.existsSync(absRoot);
        })
        .map(bundle => {
            const absRoot = path.join(projectRoot, bundle.basePath ?? '', COMPONENTS_PATH);
            // Vitest resolves include globs relative to the config file directory,
            // so we must use a relative path here.
            return path.relative(__dirname, absRoot);
        });
}

const componentRoots = resolveComponentRoots();

export default defineConfig(async () => {
    const entries = await buildComponentEntries();

    return mergeConfig(
        {
            build: {
                rolldownOptions: {
                    input: entries,
                },
            },
            plugins: [
                componentMapPlugin(),
                extensionModuleResolverPlugin(projectRoot),
            ],
            resolve: {
                // In tests, resolve the 'shopware' bare specifier to the manual mock so
                // both import-style and window-global-style components work without any
                // real shopware.js on disk.  The mock also assigns to globalThis so
                // legacy components that do `({ Shopware } = window)` still work.
                alias: {
                    shopware: path.resolve(__dirname, '__mocks__/shopware.ts'),
                },
            },
            server: {
                fs: {
                    // Allow Vite to serve files from every component root, which may be
                    // outside the app/storefront package directory.
                    allow: [resourcesRoot, projectRoot],
                },
            },
        },
        {
            test: {
                environment: 'happy-dom',
                // Include test files from every bundle that has a component path.
                include: componentRoots.map(root => `${root}/**/*.test.{js,ts}`),
            },
        },
    );
});
