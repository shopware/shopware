import path from 'node:path';
import fs from 'node:fs';
import { mergeConfig, defineConfig, type Plugin } from 'vitest/config';
import { buildComponentEntries } from './vite.components.config';
import { componentMapPlugin } from './build/component-map-plugin';

// Allow vitest's Vite dev server to serve files from the views/ tree that lives
// two levels above the app/storefront package root.
const resourcesRoot = path.resolve(__dirname, '../..');

// Project root is five levels up (storefront → app → Resources → Storefront → src → root).
const projectRoot = path.resolve(__dirname, '../../../../../');

type BundleEntry = {
    basePath?: string;
    components?: { path: string };
    storefront?: { path: string };
};

/**
 * Collect the views/components root for every bundle that declares one in
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
        .filter(bundle => bundle.components?.path)
        .map(bundle => {
            const absRoot = path.join(projectRoot, bundle.basePath ?? '', bundle.components!.path);
            // Vitest resolves include globs relative to the config file directory,
            // so we must use a relative path here.
            return path.relative(__dirname, absRoot);
        });
}

/**
 * Collect the node_modules directories for every bundle that ships its own
 * storefront npm package (e.g. custom apps/plugins with a package.json under
 * Resources/app/storefront/).  The returned paths are used by the custom
 * resolver plugin below so that dependencies declared in an extension's
 * package.json can be resolved when Vite transforms the extension's component
 * files (which live in a sibling directory and are outside Node's normal
 * upward-scan resolution).
 */
function resolveExtensionNodeModules(): string[] {
    const pluginsJson = path.join(projectRoot, 'var/plugins.json');
    if (!fs.existsSync(pluginsJson)) {
        return [];
    }

    const plugins = JSON.parse(fs.readFileSync(pluginsJson, 'utf-8')) as Record<string, BundleEntry>;

    return Object.values(plugins)
        .filter(bundle => bundle.components?.path && bundle.storefront?.path)
        .map(bundle => {
            // storefront.path is e.g. "Resources/app/storefront/src"; node_modules
            // sits one level up at "Resources/app/storefront/node_modules".
            const storefrontSrc = path.join(projectRoot, bundle.basePath ?? '', bundle.storefront!.path);
            return path.join(storefrontSrc, '..', 'node_modules');
        })
        .filter(nodeModulesPath => fs.existsSync(nodeModulesPath));
}

/**
 * Vite plugin that resolves bare-specifier imports (e.g. `import debounce
 * from 'debounce'`) against a list of additional node_modules directories.
 *
 * Vite's built-in resolver only scans directories named "node_modules" as it
 * walks up the importer's path.  Extension components live under
 * Resources/views/components/ while their dependencies are installed under the
 * sibling Resources/app/storefront/node_modules/ — a path that is never
 * visited by the upward scan.  This plugin fills that gap.
 */
function extensionModuleResolverPlugin(nodeModulesPaths: string[]): Plugin {
    return {
        name: 'extension-module-resolver',
        resolveId(id: string): string | null {
            if (!id || id.startsWith('.') || id.startsWith('/') || id.startsWith('\0')) {
                return null;
            }

            // Support scoped packages (@scope/pkg) and sub-path imports (pkg/sub).
            const parts = id.split('/');
            const pkgName = id.startsWith('@') ? parts.slice(0, 2).join('/') : (parts[0] ?? id);

            for (const nodeModulesPath of nodeModulesPaths) {
                const pkgDir = path.join(nodeModulesPath, pkgName);
                if (!fs.existsSync(pkgDir)) continue;

                const pkgJsonPath = path.join(pkgDir, 'package.json');
                if (fs.existsSync(pkgJsonPath)) {
                    const pkg = JSON.parse(fs.readFileSync(pkgJsonPath, 'utf-8')) as {
                        module?: string;
                        main?: string;
                    };
                    // Prefer ESM entry (module field) over CJS (main).
                    const entry = pkg.module ?? pkg.main ?? 'index.js';
                    const resolved = path.join(pkgDir, entry);
                    if (fs.existsSync(resolved)) return resolved;
                }

                const indexJs = path.join(pkgDir, 'index.js');
                if (fs.existsSync(indexJs)) return indexJs;
            }

            return null;
        },
    };
}

const componentRoots = resolveComponentRoots();
const extensionNodeModules = resolveExtensionNodeModules();

export default defineConfig(async () => {
    const entries = await buildComponentEntries();

    return mergeConfig(
        {
            build: {
                rollupOptions: {
                    input: entries,
                },
            },
            plugins: [
                componentMapPlugin(),
                extensionModuleResolverPlugin(extensionNodeModules),
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
