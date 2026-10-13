/**
 * @package framework
 */

import type { UserConfig, UserConfigFnPromise } from 'vite';
import svgLoader from 'vite-svg-loader';
import * as path from 'path';
import * as fs from 'fs';
import colors from 'picocolors';
import {
    getDevServerHttpsConfig,
    getMainViteServerConfig,
    isInsideDockerContainer,
    loadExtensions,
    readFeatureFlags,
} from './build/vite-plugins/utils';
import TwigPlugin from './build/vite-plugins/twigjs-plugin';
import AssetPlugin from './build/vite-plugins/asset-plugin';
import AssetPathPlugin from './build/vite-plugins/asset-path-plugin';
import ImageDeprecationPlugin from './build/vite-plugins/image-deprecation';
import AssetCssPostprocessPlugin from './build/vite-plugins/asset-css-postprocess-plugin';
import ShopwareSetupPlugin from './build/vite-plugins/shopware-setup';
import VirtualShopwareModulesPlugin from './build/vite-plugins/virtual-shopware-modules';
import NodeEnvDefinePlugin from './build/vite-plugins/node-env-define';
import AdminIndexHtmlPlugin from './build/vite-plugins/admin-index-html';
import ExtglobCompatPlugin from './build/vite-plugins/extglob-compat';
import { isBundledDevActive, loadViteToolchain } from './build/vite-plugins/vite-toolchain';

console.log(colors.yellow('# Compiling Administration with Vite configuration'));

const pageLoadingScreenPath = path.join(__dirname, '..', '..', 'shared', 'page-loading-screen');
const pageLoadingScreen = {
    script: fs.readFileSync(path.join(pageLoadingScreenPath, 'page-loading-screen.js')),
    style: fs.readFileSync(path.join(pageLoadingScreenPath, 'page-loading-screen.css')),
    markup: fs.readFileSync(path.join(pageLoadingScreenPath, 'page-loading-screen.html')),
};

export default (async ({ command }) => {
    process.env.PROJECT_ROOT = process.env.PROJECT_ROOT || path.join(__dirname, '/../../../../../');

    const toolchain = await loadViteToolchain();

    process.env = { ...process.env, ...toolchain.vite.loadEnv('', process.cwd()) };
    process.env.SERVICE_REGISTRY_URL = process.env.SERVICE_REGISTRY_URL ?? 'https://registry.services.shopware.io';

    if (!process.env.APP_URL) {
        console.log(colors.yellowBright('APP_URL is not defined. Dev-Mode will not work.'));
    }

    const viteExtensionServerMapping = getMainViteServerConfig();

    const isProd = command === 'build';
    const isDev = !isProd;
    const base = isProd ? '/bundles/administration/administration' : undefined;
    const useSourceMap =
        (isDev && process.env.SHOPWARE_ADMIN_SKIP_SOURCEMAP_GENERATION !== '1') ||
        (isProd && process.env.GENERATE_SOURCEMAPS === 'true');
    const openBrowserForWatch = process.env.DISABLE_DEVSERVER_OPEN !== '1' && !isInsideDockerContainer();
    const useBundledDev = isBundledDevActive(isDev);

    if (isProd) {
        console.log(colors.yellow('# Production mode activated 🚀'));
    }

    if (toolchain.isVite8) {
        console.log(colors.yellow('# Vite 8 activated by the ADMIN_VITE_8 feature flag'));
    }

    if (useBundledDev) {
        console.log(colors.yellow('# Experimental bundled dev mode activated'));
    }

    // We only load extensions here to display the successful injection
    const extensions = loadExtensions();
    extensions.forEach((extension) => {
        if (extension.isApp) {
            console.log(colors.green(`# App "${extension.name}": Injected successfully`));
        } else {
            console.log(colors.green(`# Plugin "${extension.name}": Injected successfully`));
        }
    });

    // print new line
    console.log('');
    console.log(colors.green('Building main administration...'));

    return {
        base,

        logLevel: isProd ? 'warn' : 'info',

        ...(toolchain.isVite8
            ? {
                  // Typed with Vite 6, which does not know the bundled dev mode
                  experimental: {
                      bundledDev: useBundledDev,
                  } as UserConfig['experimental'],
              }
            : {}),

        server: {
            open: openBrowserForWatch,
            host: process.env.HOST ? process.env.HOST : 'localhost',
            port: viteExtensionServerMapping.port,
            https: getDevServerHttpsConfig(),
            proxy: {
                '/api': {
                    target: process.env.APP_URL,
                    changeOrigin: true,
                    secure: false,
                },
                ...viteExtensionServerMapping.proxy,
            },
            allowedHosts: true,
        },

        // IIFE to return different plugins for dev and  prod
        plugins: (() => {
            // Plugins used for both dev and prod
            const sharedPlugins = [
                // Shopware plugins: build/vite-plugins
                TwigPlugin(),
                AssetPlugin(isProd, __dirname, extensions),
                AssetPathPlugin(),
                ImageDeprecationPlugin(__dirname),
                AssetCssPostprocessPlugin('/bundles/administration/administration/assets/'),
                ShopwareSetupPlugin({
                    administrationRoot: __dirname,
                }),
                VirtualShopwareModulesPlugin({
                    administrationRoot: __dirname,
                    consumer: 'host',
                }),

                // Twig.JS loads node modules, so we need to polyfill them
                toolchain.nodePolyfills({
                    // In the unbundled dev mode of Vite 8, the globals are only provided via the pre-bundled
                    // dependencies. Injecting them into every source module costs a lot of time on each server start.
                    globals:
                        toolchain.isVite8 && isDev && !useBundledDev
                            ? {
                                  Buffer: 'dev',
                                  global: 'dev',
                                  process: 'dev',
                              }
                            : undefined,
                    // To add only specific polyfills, add them here. If no option is passed, adds all polyfills
                    include: [
                        'path',
                        'events',
                    ],
                }),
                svgLoader(),
                toolchain.vue(),
                ...(toolchain.isVite8 ? [ExtglobCompatPlugin()] : []),
            ];

            if (isDev) {
                // dev plugins
                return [
                    ...sharedPlugins,

                    ...(useBundledDev ? [NodeEnvDefinePlugin()] : []),

                    AdminIndexHtmlPlugin({
                        featureFlags: JSON.stringify(readFeatureFlags()),
                        serviceRegistryUrl: process.env.SERVICE_REGISTRY_URL,
                        analyticsGatewayUrl: process.env.PRODUCT_ANALYTICS_GATEWAY_URL,
                        hideUpdateModule: [
                            '1',
                            'true',
                        ].includes(process.env.SHOPWARE_AUTO_UPDATE_HIDE_MODULE ?? ''),
                        pageLoadingScreen,
                    }),
                ];
            }

            // prod plugins
            return [
                ...sharedPlugins,

                // We only use the symfony integration for build, as serving the Shared Worker doesn't work
                toolchain.symfonyPlugin(),
            ];
        })(),

        resolve: {
            alias: [
                {
                    find: /^vue$/,
                    replacement: 'vue/dist/vue.esm-bundler.js',
                },
                {
                    find: /^src\//,
                    replacement: '/src/',
                },
                {
                    find: /^test\//,
                    replacement: '/test/',
                },
                {
                    // this is required for the SCSS modules
                    find: /^~scss\/(.*)/,
                    replacement: '/src/app/assets/scss/$1.scss',
                },
                {
                    // this is required for the SCSS modules
                    find: /^~(.*)$/,
                    replacement: '$1',
                },
            ],
        },

        css: {
            // Lightning CSS minifies the CSS since Vite 8. It fails on invalid rules, which esbuild and browsers just drop.
            lightningcss: {
                errorRecovery: true,
            },
        },

        optimizeDeps: {
            include: [
                'vue-router',
                'vuex',
                'vue-i18n',
                'flatpickr',
                'flatpickr/**/*',
                'date-fns-tz',
            ],
            // DIVE ships Vite-only import queries (`?raw`, `?url`) in its published build.
            // esbuild cannot resolve those while pre-bundling, so the dependency has to stay
            // in Vite's own pipeline.
            exclude: ['@shopware-ag/dive'],
            // This avoids full-page reload but the browser can't process more requests in parallel
            holdUntilCrawlEnd: true,
            ...(toolchain.isVite8
                ? {
                      rolldownOptions: {
                          transform: {
                              // Node.js global to browser globalThis
                              define: {
                                  global: 'globalThis',
                              },
                          },
                      },
                  }
                : {
                      esbuildOptions: {
                          // Node.js global to browser globalThis
                          define: {
                              global: 'globalThis',
                          },
                      },
                  }),
        },

        worker: {
            format: 'es',
            rollupOptions: {
                output: {
                    format: 'iife',
                },
            },
        },

        build: {
            // The outdir is set to the <project_root>/public/bundles/administration so that
            // the entrypoints.json of the symfony plugin can be read in the index.html.twig template
            outDir: isProd
                ? path.resolve(__dirname, '../../public/administration')
                : path.resolve(process.env.PROJECT_ROOT as string, 'public/bundles/administration/administration'),
            emptyOutDir: true,

            // generate .vite/manifest.json in outDir
            manifest: true,
            sourcemap: useSourceMap,
            rollupOptions: {
                input: {
                    administration: useBundledDev ? 'index.html' : 'src/index.ts',
                },
                output: {
                    entryFileNames: 'assets/[name]-[hash].js',
                },
            },
            chunkSizeWarningLimit: 5000,
        },
    };
}) satisfies UserConfigFnPromise;
