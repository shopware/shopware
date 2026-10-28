/**
 * This file is the entry point for the Vite build process for plugins.
 * Depending on the environment variable VITE_MODE, it will either start a dev server
 * for each plugin or build the plugins for production.
 *
 * The environment variable VITE_MODE is automatically set by the npm commands in the package.json.
 * You can just run `composer build:js:admin` or `composer watch:admin` respectively.
 *
 * @sw-package framework
 */

import type { UserConfig } from 'vite';
import path from 'path';
import fs from 'fs';
import colors from 'picocolors';
import svgLoader from 'vite-svg-loader';
import debug from 'debug';

// Shopware imports
import TwigPlugin from './vite-plugins/twigjs-plugin';
import TwigHmrPlugin from './vite-plugins/twig-hmr';
import AssetPlugin from './vite-plugins/asset-plugin';
import AssetPathPlugin from './vite-plugins/asset-path-plugin';
import ExternalsPlugin from './vite-plugins/externals-plugin';
import AssetCssPostprocessPlugin from './vite-plugins/asset-css-postprocess-plugin';
import OverrideComponentRegisterPlugin from './vite-plugins/override-component-register';
import ShopwareSetupPlugin from './vite-plugins/shopware-setup';
import ShopwareSetupHmrPlugin from './vite-plugins/shopware-setup-hmr';
import VirtualShopwareModulesPlugin from './vite-plugins/virtual-shopware-modules';
import { createPluginDevJson, loadExtensions, getViteServerPorts, runForEachExtension } from './vite-plugins/utils';
import type { ExtensionDefinition } from './vite-plugins/utils';
import injectHtml from './vite-plugins/inject-html';
import ExtglobCompatPlugin from './vite-plugins/extglob-compat';
import { isBundledDevActive, loadViteToolchain } from './vite-plugins/vite-toolchain';
import type { ViteToolchain } from './vite-plugins/vite-toolchain';

const VITE_MODE = process.env.VITE_MODE || 'development';
const isDev = VITE_MODE === 'development';
const adminSrcPath = process.env.ADMIN_ROOT
    ? path.join(process.env.ADMIN_ROOT, 'Resources', 'app', 'administration', 'src')
    : path.join(path.dirname(__dirname), 'src');

const extensionEntries = loadExtensions();

// Common configuration shared between dev and build
const getBaseConfig = (
    toolchain: ViteToolchain,
    extension: ExtensionDefinition,
    isProd: boolean,
    useBundledDev = false,
): UserConfig => {
    const extensionInfoDebug = debug(`vite:${extension.isPlugin ? 'plugin' : 'app'}:${extension.technicalName}`);
    const configInfoDebug = debug('vite:config');
    const useSourceMap =
        (!isProd && process.env.SHOPWARE_ADMIN_SKIP_SOURCEMAP_GENERATION !== '1') ||
        (isProd && process.env.GENERATE_SOURCEMAPS === 'true');

    const logger = toolchain.vite.createLogger();

    logger.info = (msg) => {
        if (msg.includes('vite:config')) {
            configInfoDebug(msg);
            return;
        }

        extensionInfoDebug(msg);
    };

    return {
        root: extension.path,

        logLevel: isProd ? 'warn' : 'info',

        customLogger: logger,

        cacheDir: path.resolve(extension.path, '..', '.tmp/vite'),

        plugins: [
            TwigPlugin(),
            ...(toolchain.isVite8 && isDev ? [TwigHmrPlugin()] : []),
            AssetPlugin(!isDev, path.resolve(extension.basePath, 'Resources/app/administration')),
            AssetPathPlugin(extension.technicalFolderName),
            AssetCssPostprocessPlugin(`/bundles/${extension.technicalFolderName}/administration/assets/`),
            svgLoader(),
            OverrideComponentRegisterPlugin({
                root: extension.path,
                pluginEntryFile: extension.filePath,
            }),
            ShopwareSetupPlugin({
                administrationRoot: path.dirname(__dirname),
            }),
            VirtualShopwareModulesPlugin({
                administrationRoot: path.dirname(__dirname),
                consumer: 'extension',
            }),
            toolchain.vue({
                template: {
                    compilerOptions: {
                        compatConfig: {
                            MODE: 2,
                        },
                    },
                },
            }),
            ...(toolchain.isVite8 && isDev ? [ShopwareSetupHmrPlugin()] : []),
            ...(toolchain.isVite8 ? [ExtglobCompatPlugin({ warnOnRewrite: true })] : []),
            ExternalsPlugin(),

            // Prod plugins
            ...(isDev ? [] : [toolchain.symfonyPlugin()]),
        ],

        resolve: {
            alias: [
                {
                    find: /^src\//,
                    replacement: '/src/',
                },
                {
                    find: /^~scss\/(.*)/,
                    replacement: `${adminSrcPath}/app/assets/scss/$1.scss`,
                },
                {
                    find: /^~(.*)$/,
                    replacement: '$1',
                },
            ],
            preserveSymlinks: true,
        },

        ...(isDev
            ? {}
            : {
                  base: `/bundles/${extension.technicalFolderName}/administration/`,
                  optimizeDeps: {
                      include: [
                          'vue-router',
                          'vuex',
                          'vue-i18n',
                          'flatpickr',
                          'flatpickr/**/*',
                          'date-fns-tz',
                      ],
                      holdUntilCrawlEnd: true,
                      ...(toolchain.isVite8
                          ? {
                                rolldownOptions: {
                                    transform: {
                                        define: {
                                            global: 'globalThis',
                                        },
                                    },
                                },
                            }
                          : {
                                esbuildOptions: {
                                    define: {
                                        global: 'globalThis',
                                    },
                                },
                            }),
                  },
              }),

        css: {
            lightningcss: {
                errorRecovery: true,
            },
        },

        // Typed with Vite 6, which does not know the bundled dev mode
        ...(useBundledDev ? { experimental: { bundledDev: true } as UserConfig['experimental'] } : {}),

        build: {
            outDir: path.resolve(extension.basePath, 'Resources/public/administration'),
            emptyOutDir: true,
            manifest: true,
            sourcemap: useSourceMap,
            rollupOptions: {
                input: {
                    [extension.technicalName]: extension.filePath,
                },
                output: {
                    entryFileNames: 'assets/[name]-[hash].js',
                },
                // Rolldown requests lazy chunks from the absolute `/@vite/lazy` URL, which ignores `base` and hits the main admin server
                ...(useBundledDev ? { experimental: { devMode: { lazy: false } } } : {}),
            },
        },
    };
};

// Main function to handle both dev and build modes
const main = async () => {
    const toolchain = await loadViteToolchain();
    const logger = toolchain.vite.createLogger();

    if (isDev) {
        const extensionPorts = getViteServerPorts();
        const useBundledDev = isBundledDevActive(isDev);

        if (useBundledDev) {
            logger.info(colors.yellow('# Experimental bundled dev mode activated for plugins'));
        }

        // Write outside of public/administration on purpose: a parallel production
        // build empties that directory (emptyOutDir), which would delete this file
        // out from under the running watcher.
        fs.writeFileSync(
            path.resolve(__dirname, '../../../sw-plugin-dev.json'),
            JSON.stringify(createPluginDevJson(extensionEntries, useBundledDev)),
        );

        await runForEachExtension(extensionEntries, 'start', async (extension) => {
            let server;

            if (extension.isApp) {
                server = await toolchain.vite.createServer({
                    root: extension.path,
                    base: `/_internal_ext/${extension.technicalName}/`,
                    server: {
                        host: '127.0.0.1',
                        port: extensionPorts[extension.technicalName],
                        cors: true,
                    },
                    plugins: toolchain.isVite8 ? [ExtglobCompatPlugin({ warnOnRewrite: true })] : [],
                });

                logger.info(colors.green(`# App "${extension.name}": Injected successfully`));
            } else {
                server = await toolchain.vite.createServer({
                    ...getBaseConfig(toolchain, extension, false, useBundledDev),
                    base: `/_internal_ext/${extension.technicalName}/`,
                    server: {
                        host: '127.0.0.1',
                        port: extensionPorts[extension.technicalName],
                        cors: true,
                    },
                });

                logger.info(colors.green(`# Plugin "${extension.name}": Injected successfully`));
            }

            await server.listen();
            server.printUrls();
        });

        return;
    }

    const hasFailedBuilds = await runForEachExtension(extensionEntries, 'build', async (extension) => {
        if (extension.isApp) {
            logger.info(colors.green(`# Building app "${extension.name}"`));
            await toolchain.vite.build({
                root: extension.path,
                base: '',
                build: {
                    outDir: path.resolve(extension.basePath, 'Resources/public/meteor-app'),
                },
                plugins: [
                    injectHtml([
                        {
                            tag: 'base',
                            attrs: {
                                href: '__$ASSET_BASE_PATH$__',
                            },
                            injectTo: 'head-prepend',
                        },
                    ]),
                    ...(toolchain.isVite8 ? [ExtglobCompatPlugin({ warnOnRewrite: true })] : []),
                ],
            });
        } else {
            logger.info(colors.green(`# Building plugin "${extension.name}"`));
            await toolchain.vite.build(getBaseConfig(toolchain, extension, true));
        }
    });

    if (hasFailedBuilds) {
        console.error(colors.red('One or more builds failed. Check the logs above for details.'));
        process.exit(1);
    }
};

main().catch((error) => {
    console.error(error);

    // In dev mode a non-zero exit would also stop the watcher of the Administration
    if (!isDev) {
        process.exit(1);
    }
});
