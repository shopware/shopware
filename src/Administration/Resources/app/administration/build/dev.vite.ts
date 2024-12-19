import { createServer } from 'vite';
import path from 'path';
import fs from 'fs';
import chalk from 'chalk';
import vue from '@vitejs/plugin-vue';
import svgLoader from 'vite-svg-loader';
import { createHtmlPlugin } from 'vite-plugin-html';
import symfonyPlugin from 'vite-plugin-symfony';
import TwigPlugin from './vite-plugins/twigjs-plugin';
import AssetPlugin from './vite-plugins/asset-plugin';
import { viteExternalsPlugin } from 'vite-plugin-externals'
import AssetPathPlugin from './vite-plugins/asset-path-plugin';
import loadPlugins from './helper/load-plugins';
import findAvailablePorts from './helper/ports';

const pluginEntries = loadPlugins();

(async () => {
    const availablePorts = await findAvailablePorts(5333, pluginEntries.length);

    // Map availablePorts to existing plugins and write to sw-plugin-dev.json
    let index = 0;
    const swPluginDevJsonData = {
        'metadata': 'shopware',
    };
    pluginEntries.forEach(async (plugin) => {
        // get the entry file name e.g main.js
        const fileName = plugin.filePath.split('/').pop();

        // For each bundle we need to load the vite client and the entry script
        swPluginDevJsonData[plugin.technicalName] = {
            js: `http://localhost:${availablePorts[index]}/${fileName}`,
            hmrSrc: `http://localhost:${availablePorts[index]}/@vite/client`,
        };

        index++;
    });
    fs.writeFileSync(path.resolve(__dirname, '../../../public/administration/sw-plugin-dev.json'), JSON.stringify(swPluginDevJsonData));

    pluginEntries.forEach(async (plugin) => {
        const port = availablePorts.shift();

        const server = await createServer({
            root: plugin.path,

            server: {
                port,
            },

            // IIFE to return different plugins for dev and  prod
            plugins: (() => {
                // Plugins used for both dev and prod
                return [
                    // Shopware plugins: build/vite-plugins
                    TwigPlugin(),
                    AssetPlugin(false, __dirname),
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
                    viteExternalsPlugin({
                        vue: ['Shopware', 'Vue'],
                    }),
                ];
            })(),

            resolve: {
                alias: [
                    // TODO: can this be removed?
                    {
                        find: /^vue$/,
                        replacement: `${process.env.PROJECT_ROOT}/src/Administration/Resources/app/administration/node_modules/@vue/compat/dist/vue.esm-bundler.js`,
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

        console.log(chalk.green(`# Plugin "${plugin.name}": Injected successfully`));
        await server.listen();
        server.printUrls();
    });
})();
