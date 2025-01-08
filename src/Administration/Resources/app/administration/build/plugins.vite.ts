/**
 * This file is the entry point for the Vite build process for plugins.
 * Depending on the environment variable VITE_MODE, it will either start a dev server
 * for each plugin or build the plugins for production.
 *
 * The environment variable VITE_MODE is automatically set by the npm commands in the package.json.
 * You can just run `composer build:js:admin` or `composer watch:admin` respectively.
 *
 * @package framework
 */

import { createServer, build } from 'vite';
import path from 'path';
import fs from 'fs';
import chalk from 'chalk';
import vue from '@vitejs/plugin-vue';
import svgLoader from 'vite-svg-loader';
import symfonyPlugin from 'vite-plugin-symfony';

// Shopware imports
import TwigPlugin from './vite-plugins/twigjs-plugin';
import AssetPlugin from './vite-plugins/asset-plugin';
import AssetPathPlugin from './vite-plugins/asset-path-plugin';
import ExternalsPlugin from './vite-plugins/externals-plugin';
import OverrideComponentRegisterPlugin from './vite-plugins/override-component-register';
import { loadPlugins, findAvailablePorts } from './vite-plugins/utils';
import type { PluginDefinition } from './vite-plugins/utils';

const VITE_MODE = process.env.VITE_MODE || 'development';
const isDev = VITE_MODE === 'development';

const pluginEntries = loadPlugins();

// Common configuration shared between dev and build
const getBaseConfig = (plugin: PluginDefinition) => ({
    root: plugin.path,

    plugins: [
        TwigPlugin(),
        AssetPlugin(!isDev, __dirname),
        AssetPathPlugin(),
        svgLoader(),
        OverrideComponentRegisterPlugin({
            root: plugin.path,
            pluginEntryFile: plugin.filePath,
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
                replacement: `${process.env.PROJECT_ROOT}/src/Administration/Resources/app/administration/src/app/assets/scss/$1.scss`,
            },
            {
                find: /^~(.*)$/,
                replacement: '$1',
            },
        ],
    },

    ...(isDev
        ? {}
        : {
              base: `/bundles/${plugin.technicalFolderName}/administration/`,
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
        outDir: path.resolve(plugin.basePath, 'Resources/public/administration'),
        emptyOutDir: true,
        manifest: true,
        sourcemap: true,
        rollupOptions: {
            input: {
                [plugin.technicalName]: plugin.filePath,
            },
            output: {
                entryFileNames: 'assets/[name]-[hash].js',
            },
        },
    },
});

// Main function to handle both dev and build modes
const main = async () => {
    if (isDev) {
        const availablePorts = await findAvailablePorts(5333, pluginEntries.length);

        // Create sw-plugin-dev.json for development mode
        const swPluginDevJsonData = {
            metadata: 'shopware',
        } as {
            metadata: string;
        } & Record<
            string,
            {
                js: string;
                hmrSrc: string;
            }
        >;

        pluginEntries.forEach((plugin, index) => {
            const fileName = plugin.filePath.split('/').pop();
            swPluginDevJsonData[plugin.technicalName] = {
                js: `http://localhost:${availablePorts[index]}/${fileName}`,
                hmrSrc: `http://localhost:${availablePorts[index]}/@vite/client`,
            };
        });

        fs.writeFileSync(
            path.resolve(__dirname, '../../../public/administration/sw-plugin-dev.json'),
            JSON.stringify(swPluginDevJsonData),
        );

        // Start dev servers
        for (let i = 0; i < pluginEntries.length; i++) {
            const plugin = pluginEntries[i];
            const port = availablePorts[i];

            const server = await createServer({
                ...getBaseConfig(plugin),
                server: { port },
            });

            console.log(chalk.green(`# Plugin "${plugin.name}": Injected successfully`));
            await server.listen();
            server.printUrls();
        }
    } else {
        // Build mode
        for (const plugin of pluginEntries) {
            await build(getBaseConfig(plugin));
        }
    }
};

main().catch(console.error);
