import type { Alias, Plugin } from 'vite';
import fsExtra from 'fs-extra';
import fs from 'fs';
import path from 'path';

/**
 * @sw-package framework
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
            await fsExtra.ensureDir(cachePath);

            // Add new alias for Vue
            const vueJsCachePath = path.join(cachePath, `vue.js`);
            aliasResult.push({ find: /^vue$/, replacement: vueJsCachePath });

            // All extension dev servers share the file, so rewriting it would break the bundling of the other servers.
            // CommonJS keeps named imports of Vue working, e.g. in the admin SDK of PayPal.
            const vueJsContent = `module.exports = window['Shopware']?.['Vue']`;
            if (!fs.existsSync(vueJsCachePath) || fs.readFileSync(vueJsCachePath, 'utf-8') !== vueJsContent) {
                await fsExtra.writeFile(vueJsCachePath, vueJsContent);
            }

            return {
                resolve: {
                    alias: aliasResult,
                },
            };
        },
    };
}
