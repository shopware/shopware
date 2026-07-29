import path from 'node:path';
import fs from 'node:fs';
import { glob } from 'tinyglobby';
import { mergeConfig, defineConfig } from 'vitest/config';
import { buildComponentEntries } from './build/vite/component-entries';
import { componentMapPlugin } from './build/vite/component-map-plugin';
import { extensionModuleResolverPlugin } from './build/vite/extension-module-resolver-plugin';

const configDir = import.meta.dirname;

// Allow vitest's Vite dev server to serve files from the views/ tree that lives
// two levels above the app/storefront package root.
const resourcesRoot = path.resolve(configDir, '../..');
const projectRoot = process.env.PROJECT_ROOT
    ? path.resolve(process.env.PROJECT_ROOT)
    : path.resolve(configDir, '../../../../../');

type BundleEntry = {
    basePath?: string;
};

const COMPONENTS_PATH = 'Resources/views/components';

/**
 * Collect the views/components root for every bundle that has one in
 * var/plugins.json. Falls back to the core Storefront path only when the
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
            return path.relative(configDir, absRoot);
        });
}

const componentRoots = resolveComponentRoots();

/**
 * Enumerate the concrete `*.test.{js,ts}` paths inside every component root
 * without crossing symlinks.
 *
 * `Resources/views/components/` contains a `node_modules` symlink pointing at
 * `Resources/app/storefront/node_modules` so the IDE can resolve npm
 * dependencies from component sources. Letting Vitest glob with the default
 * symlink-following behaviour would traverse that symlink and pick up every
 * `*.test.ts` inside the package's `node_modules` tree (type fixtures shipped
 * by `@testing-library/jest-dom`, etc.), all of which fail to collect.
 *
 * Explicitly disabling `followSymbolicLinks` and materialising the file list
 * ourselves guarantees the test runner only sees real component tests, no
 * matter what tinyglobby decides to do with `ignore` patterns under symlinks.
 */
async function resolveComponentTestFiles(): Promise<string[]> {
    if (componentRoots.length === 0) return [];

    const patterns = componentRoots.map(root => `${root}/**/*.test.{js,ts}`);

    return glob(patterns, {
        cwd: configDir,
        followSymbolicLinks: false,
        absolute: true,
    });
}

export default defineConfig(async () => {
    const entries = await buildComponentEntries();
    const componentTestFiles = await resolveComponentTestFiles();

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
                    shopware: path.resolve(configDir, '__mocks__/shopware.ts'),
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
                // Component tests are pre-resolved as absolute paths (see
                // `resolveComponentTestFiles` above) so the symlinked
                // `views/components/node_modules` tree is never traversed.
                // Build/vite tooling tests live inside this package, never
                // behind a symlink, so a glob pattern is fine there — they
                // set their own node environment via the file-level
                // `@vitest-environment node` directive.
                include: [
                    ...componentTestFiles,
                    'src/component-system/**/*.test.ts',
                    'build/vite/**/*.test.ts',
                    'build/vite/**/*.test.js',
                ],
            },
        },
    );
});
