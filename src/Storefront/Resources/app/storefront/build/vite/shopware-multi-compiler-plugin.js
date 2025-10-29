/**
 * @sw-package framework
 *
 * Vite plugin that handles multi-compiler setup for plugins/themes
 * Similar to webpack's multi-compiler mode
 */
import path from 'node:path';
import chalk from 'chalk';
import { build } from 'vite';

export default function shopwareMultiCompilerPlugin(options = {}) {
    const {
        pluginEntries,
        isDev,
    } = options;

    let hasBuilt = false;
    let isDevServer = false;

    // Extracted build logic that can be called from multiple hooks
    const buildExtensions = async () => {

        // eslint-disable-next-line no-console
        console.log(chalk.blue('\n📦 Building plugin/theme bundles...\n'));

        // Build plugins sequentially or in parallel
        const parallelism = process.env.STOREFRONT_BUILD_PARALLELISM
            ? parseInt(process.env.STOREFRONT_BUILD_PARALLELISM, 10)
            : 4; // Default to 4 parallel builds

        const buildPlugin = async (plugin) => {
            // Build to the extension's own dist directory
            const outputPath = path.resolve(plugin.path, '../dist/storefront');

            const pluginConfig = {
                configFile: false,
                root: path.dirname(plugin.filePath),
                base: '/',
                mode: isDev ? 'development' : 'production',

                experimental: {
                    renderBuiltUrl(filename) {
                        // Generate code that uses the runtime base path for chunk loading
                        return {
                            runtime: `(() => {
                                    const base = (typeof window !== 'undefined' && window.__vite_plugin_config__ && window.__vite_plugin_config__.base) || '/';
                                    const filename = ${JSON.stringify(filename)};
                                    let normalizedFilename = filename.startsWith('/') ? filename.substring(1) : filename;
                                    if (normalizedFilename.startsWith('js/') && base.match(/\\/js\\/?$/)) {
                                        normalizedFilename = normalizedFilename.substring(3);
                                    }
                                    const normalizedBase = base.endsWith('/') ? base : base + '/';
                                    return normalizedBase + normalizedFilename;
                                })()`,
                        };
                    },
                },

                build: {
                    outDir: outputPath,
                    emptyOutDir: true,
                    sourcemap: isDev ? true : false,
                    reportCompressedSize: false,  // Skip gzip calculation for speed
                    lib: false,
                    rollupOptions: {
                        input: {
                            [plugin.technicalName]: plugin.filePath,
                        },
                        output: {
                            format: 'es', // Native ES modules
                            entryFileNames: `js/${plugin.technicalName}/[name].js`,
                            chunkFileNames: `js/${plugin.technicalName}/[name].js`,
                            assetFileNames: 'css/[name].css',
                            // Ensure inlineDynamicImports is false to allow code splitting
                            inlineDynamicImports: false,
                        },
                        external: [], // Don't externalize anything, bundle everything
                    },
                    minify: !isDev ? 'terser' : false,
                    terserOptions: !isDev ? {
                        compress: true,
                    } : undefined,
                },

                resolve: {
                    alias: {
                        src: path.resolve(__dirname, '../../src'),
                        assets: path.resolve(__dirname, '../../assets'),
                        vendor: path.resolve(__dirname, '../../vendor'),
                    },
                    extensions: ['.ts', '.tsx', '.js', '.jsx', '.json'],
                },

                optimizeDeps: {
                    include: ['bootstrap', '@popperjs/core'],
                },
            };

            try {
                await build(pluginConfig);
                // eslint-disable-next-line no-console
                console.log(chalk.green(`✓ Built plugin: ${plugin.name}`));
            } catch (error) {
                console.error(chalk.red(`✗ Error building plugin ${plugin.name}:`), error);
                throw error;
            }
        };

        // Build plugins with controlled parallelism
        if (parallelism === Infinity || parallelism >= pluginEntries.length) {
            // Build all in parallel
            await Promise.all(pluginEntries.map(buildPlugin));
        } else {
            // Build with limited parallelism
            for (let i = 0; i < pluginEntries.length; i += parallelism) {
                const batch = pluginEntries.slice(i, i + parallelism);
                await Promise.all(batch.map(buildPlugin));
            }
        }

        // eslint-disable-next-line no-console
        console.log(chalk.green('\n✓ All plugin/theme bundles built successfully\n'));
    };

    return {
        name: 'shopware-multi-compiler',

        configResolved(config) {
            // Detect if we're in dev server mode
            isDevServer = config.command === 'serve';
        },

        async buildStart() {
            // Run during dev server startup
            if (isDevServer && !hasBuilt) {
                hasBuilt = true;
                await buildExtensions();
            }
        },

        async closeBundle() {
            // Only run during build mode (not dev server)
            if (hasBuilt || isDevServer) {
                return;
            }

            hasBuilt = true;
            await buildExtensions();
        },
    };
}

