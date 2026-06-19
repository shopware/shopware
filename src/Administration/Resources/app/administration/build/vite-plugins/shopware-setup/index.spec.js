/**
 * @sw-package framework
 */

import ShopwareSetupPlugin from './index';

const pluginOptions = {
    administrationRoot: process.cwd(),
};

async function transformVueSource(plugin, source, fileName = '/example/component.vue') {
    return plugin.transform(source, fileName);
}

describe('build/vite-plugins/shopware-setup', () => {
    it('returns a pre-transform Vite plugin', () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);

        expect(plugin.name).toBe('shopware-vite-plugin-shopware-setup');
        expect(plugin.enforce).toBe('pre');
        expect(plugin).toHaveProperty('transform');
    });

    it('delegates Shopware setup Vue files to the shared transform', async () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);
        const source = `<script setup sw-component="sw-my-component">
const count = 1;
</script>`;

        const result = await transformVueSource(plugin, source);

        expect(result).toHaveProperty('code');
        expect(result.code).toContain('Shopware.Component.createExtendableSetup(');
        expect(result.code).toContain("name: 'sw-my-component'");
        expect(result.map).toBeNull();
    });

    it('delegates sw-override blocks in .override.vue files', async () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);
        const source = `<script setup sw-override="sw-my-component">
const count = 1;

swDefineOverride({});
</script>`;

        const result = await transformVueSource(plugin, source, '/example/component.override.vue');

        expect(result).toHaveProperty('code');
        expect(result.code).toContain("Shopware.Component.overrideComponentSetup()('sw-my-component'");
    });

    it('ignores Vue files without Shopware setup blocks', async () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);

        await expect(transformVueSource(plugin, '<script>const count = 1;</script>')).resolves.toBeNull();
    });

    it('ignores files without Shopware setup blocks', async () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);

        await expect(plugin.transform('const count = 1;', '/example/component.ts')).resolves.toBeNull();
    });
});
