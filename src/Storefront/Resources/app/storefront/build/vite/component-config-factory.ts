import path from 'node:path';
import { createRequire } from 'node:module';
import type { Plugin, UserConfig } from 'vite';
import { glob } from 'tinyglobby';
import { componentMapPlugin } from './component-map-plugin';
import { plainCssShimPlugin } from './plain-css-shim-plugin';
import { scopedSubpathExportsPlugin } from './scoped-subpath-exports-plugin';

const PLAIN_CSS_SHIM_PREFIX = '\0sw-plain-css:';

export type ComponentBuildConfigOptions = {
    componentRoot: string;
    outDir: string;
    namespace: string;
    storefrontAppDir: string;
    coreStorefrontAppDir?: string;
    sourcemap?: boolean;
    cssLoadPaths?: string[];
    additionalPlugins?: Plugin[];
    prependPlugins?: Plugin[];
    external?: string[];
    resolveAliases?: Record<string, string>;
};

function toAssetDirectory(name: string): string {
    return name.toLowerCase().replace(/bundle$/, '');
}

function extensionNodeModulesPlugin(storefrontAppDir: string): Plugin {
    const resolveFromExtension = createRequire(path.join(storefrontAppDir, 'package.json'));

    return {
        name: 'extension-node-modules-resolver',
        enforce: 'pre',
        resolveId(source: string): string | null {
            if (source.startsWith('.') || source.startsWith('\0') || path.isAbsolute(source)) {
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

/**
 * Builds a reusable Vite config for Storefront components.
 *
 * Use this in both Shopware's generic build config and custom extension config
 * files to avoid duplicating entry discovery, output naming, and plugin setup.
 */
export async function createComponentBuildConfig(options: ComponentBuildConfigOptions): Promise<UserConfig> {
    const {
        componentRoot,
        outDir,
        namespace,
        storefrontAppDir,
        coreStorefrontAppDir = path.resolve(import.meta.dirname, '../..'),
        sourcemap = process.env.NODE_ENV !== 'production',
        cssLoadPaths,
        additionalPlugins = [],
        prependPlugins = [],
        external = [],
        resolveAliases = {},
    } = options;

    const isExtension = namespace !== 'Storefront';
    const [jsFiles, scssFiles, cssFiles] = await Promise.all([
        glob('**/*.{js,ts}', {
            cwd: componentRoot,
            ignore: ['**/node_modules/**', '**/*.test.{js,ts}', '**/*.stories.*'],
        }),
        glob('**/*.scss', {
            cwd: componentRoot,
            ignore: ['**/node_modules/**', '**/*.stories.*'],
        }),
        glob('**/*.css', {
            cwd: componentRoot,
            ignore: ['**/node_modules/**', '**/*.stories.*'],
        }),
    ]);

    // A component may declare exactly one style source — .scss OR .css, never both.
    const scssBasenames = new Set(scssFiles.map(f => f.replace(/\.scss$/, '')));
    for (const cssFile of cssFiles) {
        const base = cssFile.replace(/\.css$/, '');
        if (scssBasenames.has(base)) {
            throw new Error(
                `[component-config-factory] Component "${base}" has both a .scss and a .css file. `
                + 'A component may declare only one style source.',
            );
        }
    }

    const makeJsEntryName = (file: string): string => {
        const name = file.replace(/\.(js|ts)$/, '');
        return isExtension ? `${namespace}/${name}` : name;
    };
    const makeStyleEntryName = (file: string): string =>
        isExtension ? `${namespace}/${file}` : file;

    // Virtual module bridge for plain CSS entries so Vite emits proper manifest entries.
    const plainCssShims = new Map<string, string>();
    const plainCssEntries: Record<string, string> = {};
    for (const cssFile of cssFiles) {
        const entryKey = makeStyleEntryName(cssFile);
        const virtualId = `${PLAIN_CSS_SHIM_PREFIX}${entryKey}`;
        plainCssShims.set(virtualId, path.join(componentRoot, cssFile));
        plainCssEntries[entryKey] = virtualId;
    }

    const entries: Record<string, string> = {
        ...Object.fromEntries(
            jsFiles.map(file => [makeJsEntryName(file), path.join(componentRoot, file)]),
        ),
        ...Object.fromEntries(
            scssFiles.map(file => [makeStyleEntryName(file), path.join(componentRoot, file)]),
        ),
        ...plainCssEntries,
    };

    const defaultScssLoadPaths = [
        path.join(storefrontAppDir, 'vendor'),
        path.join(coreStorefrontAppDir, 'vendor'),
        path.join(coreStorefrontAppDir, 'src/scss'),
    ];

    const pluginStack: Plugin[] = [
        // Keep this ahead of extensionNodeModulesPlugin so scoped subpaths resolve to ESM exports first.
        scopedSubpathExportsPlugin(
            path.join(storefrontAppDir, 'node_modules'),
            path.join(coreStorefrontAppDir, 'node_modules'),
        ),
        extensionNodeModulesPlugin(storefrontAppDir),
        componentMapPlugin(),
        plainCssShimPlugin(plainCssShims),
    ];

    const allExternals = Array.from(new Set(['shopware', ...external]));

    const bundleAssetDir = toAssetDirectory(namespace);

    return {
        base: `/bundles/${bundleAssetDir}/storefront/components/`,
        css: {
            preprocessorOptions: {
                scss: {
                    loadPaths: cssLoadPaths ?? defaultScssLoadPaths,
                },
            },
        },
        build: {
            outDir,
            emptyOutDir: true,
            manifest: true,
            sourcemap,
            rolldownOptions: {
                input: entries,
                preserveEntrySignatures: 'exports-only',
                external: allExternals,
                output: {
                    format: 'es',
                    entryFileNames: '[name]-[hash].js',
                    chunkFileNames: isExtension
                        ? `${namespace}/vendor/[name]-[hash].js`
                        : 'vendor/[name]-[hash].js',
                    assetFileNames: info => {
                        const firstName = info.names[0] ?? 'asset.css';
                        if (firstName.endsWith('.scss.css')) {
                            return `${firstName.replace(/\.scss\.css$/, '')}-[hash][extname]`;
                        }
                        return '[name]-[hash][extname]';
                    },
                },
            },
        },
        resolve: {
            alias: resolveAliases,
        },
        plugins: [
            ...prependPlugins,
            ...pluginStack,
            ...additionalPlugins,
        ],
    };
}
