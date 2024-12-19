import { build } from 'vite';
import path from 'path';
import fs from 'fs';
import chalk from 'chalk';
import vue from '@vitejs/plugin-vue';
import svgLoader from 'vite-svg-loader';
import { createHtmlPlugin } from 'vite-plugin-html';
import symfonyPlugin from 'vite-plugin-symfony';
import TwigPlugin from './vite-plugins/twigjs-plugin';
import AssetPlugin from './vite-plugins/asset-plugin';
import AssetPathPlugin from './vite-plugins/asset-path-plugin';
import loadPlugins from './helper/load-plugins';


const pluginEntries = loadPlugins();

pluginEntries.forEach(async (plugin) => {
    await build({
        root: plugin.path,

        base: `/bundles/${plugin.technicalFolderName}/administration/`,

        plugins: [
            vue({
                template: {
                    compilerOptions: {
                        compatConfig: {
                            MODE: 2,
                        },
                    },
                },
            }),
            svgLoader(),

            // Shopware plugins: build/vite-plugins
            TwigPlugin(),
            AssetPlugin(true, __dirname),
            AssetPathPlugin(),
            symfonyPlugin(),
        ],

        resolve: {
            alias: [
                {
                    find: /vue$/,
                    replacement: '@vue/compat/dist/vue.esm-bundler.js',
                },
                {
                    find: /^src\//,
                    replacement: '/src/',
                },
                {
                    // this is required for the SCSS modules
                    find: /^~scss\/(.*)/,
                    replacement: `${process.env.PROJECT_ROOT}/src/Administration/Resources/app/administration/src/app/assets/scss/$1.scss`,
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

        build: {
            // The outdir is set to the <project_root>/public/bundles/administration so that
            // the entrypoints.json of the symfony plugin can be read in the index.html.twig template
            outDir: path.resolve(plugin.basePath, 'Resources/public/administration'),
            emptyOutDir: true,

            // generate .vite/manifest.json in outDir
            manifest: true,
            sourcemap: true,
            rollupOptions: {
                // overwrite default .html entry
                input: {
                    [plugin.technicalName]: plugin.filePath,
                },
                output: {
                    entryFileNames: 'assets/[name]-[hash].js',
                },
            },
        },
    });
});
