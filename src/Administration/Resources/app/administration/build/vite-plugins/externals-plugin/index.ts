import type { Alias, Plugin } from 'vite';
import { ensureDir, ensureFile, emptyDirSync, writeFile } from 'fs-extra';
import path from 'path';

/**
 * @package framework
 * @private
 *
 * This plugin will add an alias for /^vue$/ pointing to a temp file that exports the global Vue instance.
 * It's only used for Shopware plugins. This solves the problem of having multiple Vue instances in the same project.
 *
 * Inspired by: https://github.com/crcong/vite-plugin-externals/
 */
export default function viteExternalsPlugin(): Plugin {
    return {
        name: 'shopware-vite-plugin-vue-globals',

        // Add a vue alias to the config pointing to a temp file
        async config(config) {
            const aliasResult: Alias[] = [];
            const configAlias = config.resolve?.alias ?? {};

            // Is alias object?
            if (Object.prototype.toString.call(configAlias) === '[object Object]') {
                Object.keys(configAlias).forEach((aliasKey) => {
                    aliasResult.push({ find: aliasKey, replacement: (configAlias as Record<string, string>)[aliasKey] });
                });
            } else if (Array.isArray(configAlias)) {
                aliasResult.push(...configAlias);
            }

            // Create cache directory
            const cachePath = path.join(process.cwd(), 'node_modules', '.shopware-vite-plugin-vue-globals');
            await ensureDir(cachePath);
            await emptyDirSync(cachePath);

            // Add new alias for Vue
            const vueJsCachePath = path.join(cachePath, `vue.js`);
            aliasResult.push({ find: /^vue$/, replacement: vueJsCachePath });

            // Write temp vue.js file
            await ensureFile(vueJsCachePath);
            await writeFile(vueJsCachePath, `module.exports = window['Shopware']['Vue'];`);

            return {
                resolve: {
                    alias: aliasResult,
                },
            };
        },
    };
}
