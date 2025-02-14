/* eslint-disable no-await-in-loop */
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

import { createServer, build, defineConfig, createLogger } from 'vite';
import path from 'path';
import fs from 'fs';
import colors from 'picocolors';
import vue from '@vitejs/plugin-vue';
import svgLoader from 'vite-svg-loader';
import symfonyPlugin from 'vite-plugin-symfony';
import debug from 'debug';

// Shopware imports
import TwigPlugin from './vite-plugins/twigjs-plugin';
import AssetPlugin from './vite-plugins/asset-plugin';
import AssetPathPlugin from './vite-plugins/asset-path-plugin';
import ExternalsPlugin from './vite-plugins/externals-plugin';
import OverrideComponentRegisterPlugin from './vite-plugins/override-component-register';
import { loadExtensions, findAvailablePorts } from './vite-plugins/utils';
import type { ExtensionDefinition } from './vite-plugins/utils';
import injectHtml from './vite-plugins/inject-html';

const VITE_MODE = process.env.VITE_MODE || 'development';
const isDev = VITE_MODE === 'development';

// This env variable is provided by the symfony recipes
const hasAdminRootEnv = !!process.env.ADMIN_ROOT;

const extensionEntries = loadExtensions();

// Common configuration shared between dev and build
const getBaseConfig = (extension: ExtensionDefinition, isProd = false) => {
    const extensionInfoDebug = debug(`vite:${extension.isPlugin ? 'plugin' : 'app'}:${extension.technicalName}`);
    const configInfoDebug = debug('vite:config');
    const useSourceMap = !isProd && process.env.SHOPWARE_ADMIN_SKIP_SOURCEMAP_GENERATION !== '1';

    const logger = createLogger();

    logger.info = (msg) => {
        if (msg.includes('vite:config')) {
            configInfoDebug(msg);
            return;
        }

        extensionInfoDebug(msg);
    };

    return defineConfig({
        root: extension.path,

        logLevel: isProd ? 'warn' : 'info',

        customLogger: logger,

        plugins: [
            TwigPlugin(),
            AssetPlugin(!isDev, __dirname),
            AssetPathPlugin(extension.technicalFolderName),
            svgLoader(),
            OverrideComponentRegisterPlugin({
                root: extension.path,
                pluginEntryFile: extension.filePath,
            }),
            vue({
                template: {
                    compilerOptions: {
                        compatConfig: {
                            MODE: 2,
                        },
                    },
                },
            }),
            ExternalsPlugin(),

            // Prod plugins
            ...(isDev
                ? []
                : [
                      symfonyPlugin(),
                  ]),
        ],

        resolve: {
            alias: [
                {
                    find: /^src\//,
                    replacement: '/src/',
                },

                // In the symfony recipes, shopware lies in the vendor folder, therefore we can't use the PROJECT_ROOT
                ...(hasAdminRootEnv
                    ? [
                          {
                              find: /^~scss\/(.*)/,
                              replacement: `${process.env.ADMIN_ROOT}/Resources/app/administration/src/app/assets/scss/$1.scss`,
                          },
                      ]
                    : [
                          {
                              find: /^~scss\/(.*)/,
                              replacement: `${process.env.PROJECT_ROOT}/src/Administration/Resources/app/administration/src/app/assets/scss/$1.scss`,
                          },
                      ]),
                {
                    find: /^~(.*)$/,
                    replacement: '$1',
                },
            ],
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
                      esbuildOptions: {
                          define: {
                              global: 'globalThis',
                          },
                      },
                  },
              }),

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
            },
        },
    });
};

// Main function to handle both dev and build modes
const main = async () => {
    if (isDev) {
        const availablePorts = await findAvailablePorts(5333, extensionEntries.length);

        // Create sw-plugin-dev.json for development mode
        const swPluginDevJsonData = {
            metadata: 'shopware',
        } as {
            metadata: string;
        } & Record<
            string,
            {
                js?: string;
                hmrSrc?: string;
                html?: string;
            }
        >;

        extensionEntries.forEach((extension, index) => {
            const fileName = extension.filePath.split('/').pop();

            if (!swPluginDevJsonData[extension.technicalName]) {
                swPluginDevJsonData[extension.technicalName] = {};
            }

            if (extension.isApp) {
                swPluginDevJsonData[extension.technicalName].html = `http://localhost:${availablePorts[index]}/index.html`;
            }

            if (extension.isPlugin) {
                swPluginDevJsonData[extension.technicalName].js = `http://localhost:${availablePorts[index]}/${fileName}`;
                swPluginDevJsonData[extension.technicalName].hmrSrc =
                    `http://localhost:${availablePorts[index]}/@vite/client`;
            }
        });

        fs.writeFileSync(
            path.resolve(__dirname, '../../../public/administration/sw-plugin-dev.json'),
            JSON.stringify(swPluginDevJsonData),
        );

        // Start dev servers
        for (let i = 0; i < extensionEntries.length; i++) {
            const extension = extensionEntries[i];
            const port = availablePorts[i];
            const extensionInfoDebug = debug(`vite:${extension.isPlugin ? 'plugin' : 'app'}:${extension.technicalName}`);

            let server;

            if (extension.isApp) {
                // For apps
                server = await createServer({
                    root: extension.path,
                    server: { port },
                });

                extensionInfoDebug(colors.green(`# App "${extension.name}": Injected successfully`));
            } else {
                // For plugins
                server = await createServer({
                    ...getBaseConfig(extension),
                    server: { port },
                });

                extensionInfoDebug(colors.green(`# Plugin "${extension.name}": Injected successfully`));
            }

            await server.listen();
            server.printUrls();
        }
    } else {
        // Build mode
        for (const extension of extensionEntries) {
            const extensionInfoDebug = debug(`vite:${extension.isPlugin ? 'plugin' : 'app'}:${extension.technicalName}`);

            if (extension.isApp) {
                extensionInfoDebug(colors.green(`# Building app "${extension.name}"`));
                // For apps
                await build({
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
                    ],
                });
            } else {
                extensionInfoDebug(colors.green(`# Building plugin "${extension.name}"`));
                // For plugins
                await build(getBaseConfig(extension));
            }
        }
    }
};

main().catch(console.error);
