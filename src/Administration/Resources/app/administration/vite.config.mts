/**
 * @package framework
 */

import { defineConfig, loadEnv } from 'vite';
import { createHtmlPlugin } from 'vite-plugin-html';
import { nodePolyfills } from 'vite-plugin-node-polyfills';
import svgLoader from 'vite-svg-loader';
import vue from '@vitejs/plugin-vue';
import basicSsl from '@vitejs/plugin-basic-ssl';
import * as path from 'path';
import * as fs from 'fs';
import symfonyPlugin from 'vite-plugin-symfony';
import colors from 'picocolors';
import { getMainViteServerConfig, isInsideDockerContainer, loadExtensions } from './build/vite-plugins/utils';
import TwigPlugin from './build/vite-plugins/twigjs-plugin';
import AssetPlugin from './build/vite-plugins/asset-plugin';
import AssetPathPlugin from './build/vite-plugins/asset-path-plugin';
import ImageDeprecationPlugin from './build/vite-plugins/image-deprecation';
import AssetCssPostprocessPlugin from './build/vite-plugins/asset-css-postprocess-plugin';
import ShopwareSetupPlugin from './build/vite-plugins/shopware-setup';
import VirtualShopwareModulesPlugin from './build/vite-plugins/virtual-shopware-modules';
import NodeEnvDefinePlugin from './build/vite-plugins/node-env-define';

console.log(colors.yellow('# Compiling Administration with Vite configuration'));

process.env = { ...process.env, ...loadEnv('', process.cwd()) };
process.env.PROJECT_ROOT = process.env.PROJECT_ROOT || path.join(__dirname, '/../../../../../');

process.env.SERVICE_REGISTRY_URL = process.env.SERVICE_REGISTRY_URL ?? 'https://registry.services.shopware.io';

if (!process.env.APP_URL) {
    console.log(colors.yellowBright('APP_URL is not defined. Dev-Mode will not work.'));
}

const viteExtensionServerMapping = getMainViteServerConfig();

const flagsPath = path.join(process.env.PROJECT_ROOT, 'var', 'config_js_features.json');
let featureFlags = {};
if (fs.existsSync(flagsPath)) {
    featureFlags = JSON.parse(fs.readFileSync(flagsPath, 'utf-8'));
}

const pageLoadingScreenPath = path.join(__dirname, '..', '..', 'shared', 'page-loading-screen');
const pageLoadingScreen = {
    script: fs.readFileSync(path.join(pageLoadingScreenPath, 'page-loading-screen.js')),
    style: fs.readFileSync(path.join(pageLoadingScreenPath, 'page-loading-screen.css')),
    markup: fs.readFileSync(path.join(pageLoadingScreenPath, 'page-loading-screen.html')),
};

// eslint-disable-next-line
export default defineConfig(({ command }) => {
    const isProd = command === 'build';
    const isDev = !isProd;
    const base = isProd ? '/bundles/administration/administration' : undefined;
    const useSourceMap =
        (isDev && process.env.SHOPWARE_ADMIN_SKIP_SOURCEMAP_GENERATION !== '1') ||
        (isProd && process.env.GENERATE_SOURCEMAPS === 'true');
    const openBrowserForWatch = process.env.DISABLE_DEVSERVER_OPEN !== '1' && !isInsideDockerContainer();
    const useBundledDev = isDev && process.env.SHOPWARE_ADMIN_BUNDLED_DEV === '1';

    if (isProd) {
        console.log(colors.yellow('# Production mode activated 🚀'));
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

        experimental: {
            bundledDev: useBundledDev,
        },

        server: {
            open: openBrowserForWatch,
            host: process.env.HOST ? process.env.HOST : 'localhost',
            port: viteExtensionServerMapping.port,
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
                nodePolyfills({
                    // To add only specific polyfills, add them here. If no option is passed, adds all polyfills
                    include: [
                        'path',
                        'events',
                    ],
                }),
                svgLoader(),
                vue(),
            ];

            if (isDev) {
                // dev plugins
                return [
                    ...sharedPlugins,

                    NodeEnvDefinePlugin(),

                    // Browsers only use HTTP/2 over TLS, so the dev server needs a certificate
                    basicSsl({
                        name: 'localhost',
                        domains: ['localhost'],
                    }),

                    // used to serve index.html and link index.vite.ts automatically
                    createHtmlPlugin({
                        minify: false,
                        /**
                         * After writing entry here, you will not need to add script tags in `index.html`,
                         * the original tags need to be deleted
                         * @default src/main.ts
                         */
                        entry: 'src/index.ts',

                        /**
                         * Data that needs to be injected into the index.html ejs template
                         */
                        inject: {
                            data: {
                                featureFlags: JSON.stringify(featureFlags),
                                serviceRegistryUrl: process.env.SERVICE_REGISTRY_URL,
                                analyticsGatewayUrl: process.env.PRODUCT_ANALYTICS_GATEWAY_URL,
                                hideUpdateModule: [
                                    '1',
                                    'true',
                                ].includes(process.env.SHOPWARE_AUTO_UPDATE_HIDE_MODULE ?? ''),
                                pageLoadingScreen,
                            },
                        },
                    }),
                ];
            }

            // prod plugins
            return [
                ...sharedPlugins,

                // We only use the symfony integration for build, as serving the Shared Worker doesn't work
                symfonyPlugin(),
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
            rolldownOptions: {
                transform: {
                    // Node.js global to browser globalThis
                    define: {
                        global: 'globalThis',
                    },
                },
            },
        },

        worker: {
            format: 'es',
            rolldownOptions: {
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
            rolldownOptions: {
                // overwrite default .html entry, except for the bundled dev mode, which serves the index.html from the bundle
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
});
