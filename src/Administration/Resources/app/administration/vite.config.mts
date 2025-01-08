/**
 * @package framework
 */

import { defineConfig, loadEnv } from 'vite';
import { createHtmlPlugin } from 'vite-plugin-html';
import svgLoader from 'vite-svg-loader';
import vue from '@vitejs/plugin-vue';
import * as path from 'path';
import * as fs from 'fs';
import symfonyPlugin from 'vite-plugin-symfony';
import TwigPlugin from './build/vite-plugins/twigjs-plugin';
import AssetPlugin from './build/vite-plugins/asset-plugin';
import AssetPathPlugin from './build/vite-plugins/asset-path-plugin';

console.log('╔════════════════════════════════════════════════╗');
console.log('║               EXPERIMENTAL VITE BUILD          ║');
console.log('╚════════════════════════════════════════════════╝');

process.env = { ...process.env, ...loadEnv('', process.cwd()) };
process.env.PROJECT_ROOT = process.env.PROJECT_ROOT || path.join(__dirname, '/../../../../../');

if (!process.env.APP_URL) {
    throw new Error('APP_URL is not defined');
}

const flagsPath = path.join(process.env.PROJECT_ROOT, 'var', 'config_js_features.json');
let featureFlags = {};
if (fs.existsSync(flagsPath)) {
    featureFlags = JSON.parse(fs.readFileSync(flagsPath, 'utf-8'));
}

// eslint-disable-next-line
export default defineConfig(({ command }) => {
    const isProd = command === 'build';
    const isDev = !isProd;
    const base = isProd ? '/bundles/administration/administration' : undefined;

    return {
        base,
        server: {
            proxy: {
                '/api': {
                    target: process.env.APP_URL,
                    changeOrigin: true,
                    secure: false,
                },
            },
        },

        // IIFE to return different plugins for dev and  prod
        plugins: (() => {
            // Plugins used for both dev and prod
            const sharedPlugins = [
                // Shopware plugins: build/vite-plugins
                TwigPlugin(),
                AssetPlugin(isProd, __dirname),
                AssetPathPlugin(),

                svgLoader(),
                vue({
                    template: {
                        compilerOptions: {
                            compatConfig: {
                                MODE: 2,
                            },
                        },
                    },
                }),
            ];

            if (isDev) {
                // dev plugins
                return [
                    ...sharedPlugins,

                    // used to serve index.html and link index.vite.ts automatically
                    createHtmlPlugin({
                        minify: false,
                        /**
                         * After writing entry here, you will not need to add script tags in `index.html`,
                         * the original tags need to be deleted
                         * @default src/main.ts
                         */
                        entry: 'src/index.vite.ts',

                        /**
                         * Data that needs to be injected into the index.html ejs template
                         */
                        inject: {
                            data: {
                                featureFlags: JSON.stringify(featureFlags),
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
                    replacement: '@vue/compat/dist/vue.esm-bundler.js',
                },
                {
                    find: /^src\//,
                    replacement: '/src/',
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

        optimizeDeps: {
            include: [
                'vue-router',
                'vuex',
                'vue-i18n',
                'flatpickr',
                'flatpickr/**/*',
                'date-fns-tz',
            ],
            // This avoids full-page reload but the browser can't process more requests in parallel
            holdUntilCrawlEnd: true,
            esbuildOptions: {
                // Node.js global to browser globalThis
                define: {
                    global: 'globalThis',
                },
            },
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

            // generate .vite/manifest.json in outDir
            manifest: true,
            sourcemap: true,
            rollupOptions: {
                // overwrite default .html entry
                input: {
                    administration: 'src/index.vite.ts',
                },
                output: {
                    entryFileNames: 'assets/[name]-[hash].js',
                },
            },
        },
    };
});
