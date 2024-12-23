/**
 * @package framework
 */
import ExternalsPlugin from './index';

describe('build/vite-plugins/externals-plugin', () => {
    it('should be a function with 0 arguments', () => {
        expect(typeof ExternalsPlugin).toBe('function');

        // check that the function has 0 arguments
        expect(ExternalsPlugin.length).toBe(0);
    });

    it('should return an object with a name and config property', () => {
        const plugin = ExternalsPlugin();

        // Identify plugin by name
        expect(plugin).toHaveProperty('name');
        expect(plugin.name).toBe('shopware-vite-plugin-vue-globals');

        // Check if the plugin has a transform method
        expect(plugin).toHaveProperty('config');
    });

    it('should add vue alias for config without own alias', async () => {
        const plugin = ExternalsPlugin();
        const config = { resolve: {} };

        const result = await plugin.config(config);

        const aliasResult = result.resolve.alias;
        expect(aliasResult.length).toBe(1);

        const vueAlias = aliasResult[0];
        expect(vueAlias.find).toStrictEqual(/^vue$/);
        expect(vueAlias.replacement.endsWith('node_modules/.shopware-vite-plugin-vue-globals/vue.js')).toBe(true);
    });

    it('should add vue alias for config with own alias', async () => {
        const plugin = ExternalsPlugin();
        const config = {
            resolve: {
                alias: [
                    {
                        find: /@/,
                        replacement: './src/',
                    },
                ],
            },
        };

        const result = await plugin.config(config);

        const aliasResult = result.resolve.alias;
        expect(aliasResult.length).toBe(2);

        const srcAlias = aliasResult[0];
        expect(srcAlias.find).toStrictEqual(/@/);
        expect(srcAlias.replacement).toBe('./src/');

        const vue = aliasResult[1];
        expect(vue.find).toStrictEqual(/^vue$/);
        expect(vue.replacement.endsWith('node_modules/.shopware-vite-plugin-vue-globals/vue.js')).toBe(true);
    });
});
