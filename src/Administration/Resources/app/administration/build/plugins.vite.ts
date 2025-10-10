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
import { loadExtensions, getViteServerPorts, isInsideDockerContainer } from './vite-plugins/utils';
import type { ExtensionDefinition } from './vite-plugins/utils';
import injectHtml from './vite-plugins/inject-html';

const VITE_MODE = process.env.VITE_MODE || 'development';
const isDev = VITE_MODE === 'development';
const adminSrcPath = process.env.ADMIN_ROOT
    ? path.join(process.env.ADMIN_ROOT, 'Resources', 'app', 'administration', 'src')
    : path.join(path.dirname(__dirname), 'src');
const host = process.env.VITE_HOST || 'localhost';

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

        cacheDir: path.resolve(extension.path, '..', '.tmp/vite'),

        plugins: [
            TwigPlugin(),
            AssetPlugin(!isDev, path.resolve(extension.basePath, 'Resources/app/administration')),
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
    let hasFailedBuilds = false;

    if (isDev) {
        const extensionPorts = getViteServerPorts();

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
                swPluginDevJsonData[extension.technicalName].html = `/_internal_ext/${extension.technicalName}/index.html`;
            }

            if (extension.isPlugin) {
                swPluginDevJsonData[extension.technicalName].js = `/_internal_ext/${extension.technicalName}/${fileName}`;
                swPluginDevJsonData[extension.technicalName].hmrSrc =
                    `/_internal_ext/${extension.technicalName}/@vite/client`;
            }
        });

        fs.writeFileSync(
            path.resolve(__dirname, '../../../public/administration/sw-plugin-dev.json'),
            JSON.stringify(swPluginDevJsonData),
        );

        // Start dev servers
        for (let i = 0; i < extensionEntries.length; i++) {
            const extension = extensionEntries[i];
            const extensionInfoDebug = debug(`vite:${extension.isPlugin ? 'plugin' : 'app'}:${extension.technicalName}`);

            let server;

            if (extension.isApp) {
                // For apps
                server = await createServer({
                    root: extension.path,
                    base: `/_internal_ext/${extension.technicalName}/`,
                    server: {
                        host: '127.0.0.1',
                        port: extensionPorts[extension.technicalName],
                        cors: true,
                    },
                });

                console.log(colors.green(`# App "${extension.name}": Injected successfully`));
            } else {
                // For plugins
                server = await createServer({
                    ...getBaseConfig(extension),
                    base: `/_internal_ext/${extension.technicalName}/`,
                    server: {
                        host: '127.0.0.1',
                        port: extensionPorts[extension.technicalName],
                        cors: true,
                    },
                });

                console.log(colors.green(`# Plugin "${extension.name}": Injected successfully`));
            }

            await server.listen();
            server.printUrls();
        }
    } else {
        // Build mode
        for (const extension of extensionEntries) {
            try {
                if (extension.isApp) {
                    console.log(colors.green(`# Building app "${extension.name}"`));
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
                    console.log(colors.green(`# Building plugin "${extension.name}"`));
                    // For plugins
                    await build(getBaseConfig(extension));
                }
            } catch (error) {
                hasFailedBuilds = true;
                console.error(
                    colors.red(
                        // @ts-expect-error
                        `# Failed to build ${extension.isPlugin ? 'plugin' : 'app'} "${extension.name}": ${error?.message}`,
                    ),
                );
            }
        }
    }

    // Exit with code 1 if any builds failed
    if (hasFailedBuilds) {
        console.error(colors.red('One or more builds failed. Check the logs above for details.'));
        process.exit(1);
    }
};

main().catch(console.error);
