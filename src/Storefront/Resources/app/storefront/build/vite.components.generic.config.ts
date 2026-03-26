/**
 * Generic Vite component build config used by build/build-components.js for any bundle
 * that does not supply its own vite.components.config.ts.
 *
 * Environment variables injected by build-components.js:
 *   COMPONENT_ROOT      absolute path to the bundle's views/components/ directory
 *   OUT_DIR             absolute path to the bundle's dist-es/components/ output directory
 *   COMPONENT_NAMESPACE bundle name / Twig namespace (e.g. 'Storefront', 'ComponentTestApp')
 *
 * Output path strategy:
 *   Core ('Storefront'): entry files keep their natural path, e.g. Sw/Filter/Sorting.js.
 *                        Core component names already carry the Sw/ prefix by convention.
 *   Extensions:          entry files are prefixed with the namespace so the dist-es/components/
 *                        tree can be copied flat into the theme without any path rewriting.
 *                        E.g. Wusel/Counter.js → ComponentTestApp/Wusel/Counter.js
 *                        Vendor chunks:         ComponentTestApp/vendor/debounce-abc123.js
 *
 * Module resolution note:
 *   Component sources live in Resources/views/components/ while npm deps are installed into
 *   Resources/app/storefront/node_modules/.  extensionNodeModulesPlugin() bridges the gap by
 *   resolving bare specifiers via Node's createRequire from the storefront app directory.
 */

import path from 'node:path';
import { createRequire } from 'node:module';
import { defineConfig, type UserConfig } from 'vite';
import { glob } from 'tinyglobby';
import { componentMapPlugin } from './component-map-plugin';

const componentRoot = process.env.COMPONENT_ROOT;
const outDir = process.env.OUT_DIR;
const namespace = process.env.COMPONENT_NAMESPACE ?? 'Storefront';

if (!componentRoot || !outDir) {
    throw new Error(
        '[vite.components.generic.config] COMPONENT_ROOT and OUT_DIR env vars must be set.',
    );
}

const isExtension = namespace !== 'Storefront';

// Derive the extension's storefront app dir: OUT_DIR = …/storefront/dist-es/components
const storefrontAppDir = path.resolve(outDir, '../..');
const resolveFromExtension = createRequire(path.join(storefrontAppDir, 'package.json'));

/**
 * Resolves bare specifiers from the extension's own node_modules directory.
 *
 * Component sources live in Resources/views/components/ while npm deps are
 * installed into Resources/app/storefront/node_modules/.  These are sibling
 * directory branches so the standard upward node_modules crawl never finds the
 * extension's packages.  This plugin bridges the gap by using Node's
 * createRequire to resolve bare specifiers as if require() were called from
 * the storefront app directory.
 */
function extensionNodeModulesPlugin() {
    return {
        name: 'extension-node-modules-resolver',
        enforce: 'pre' as const,
        resolveId(source: string): string | null {
            if (source.startsWith('.') || path.isAbsolute(source)) {
                return null;
            }
            try {
                return resolveFromExtension.resolve(source);
            } catch {
                return null;
            }
        },
    };
}

export default defineConfig(async (): Promise<UserConfig> => {
    const files = await glob('**/*.{js,ts}', {
        cwd: componentRoot,
        ignore: ['**/*.test.{js,ts}', '**/*.stories.*'],
    });

    // For extensions the Rollup entry name becomes {Namespace}/{componentName}
    // (e.g. ComponentTestApp/Wusel/Counter) so that entryFileNames '[name].js'
    // produces the namespace-prefixed output path without further rewriting.
    const entries = Object.fromEntries(
        files.map(file => {
            const name = file.replace(/\.(js|ts)$/, '');
            const entryName = isExtension ? `${namespace}/${name}` : name;
            return [entryName, path.join(componentRoot, file)];
        }),
    );

    return {
        build: {
            outDir,
            emptyOutDir: true,
            manifest: true,
            sourcemap: process.env.NODE_ENV !== 'production',
            rollupOptions: {
                input: entries,
                preserveEntrySignatures: 'exports-only',
                external: ['shopware'],
                output: {
                    format: 'es',
                    entryFileNames: '[name].js',
                    chunkFileNames: isExtension
                        ? `${namespace}/vendor/[name]-[hash].js`
                        : 'vendor/[name]-[hash].js',
                },
            },
        },
        plugins: [extensionNodeModulesPlugin(), componentMapPlugin()],
    };
});
