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
        const source = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;

        const result = await transformVueSource(plugin, source, '/example/sw-my-component.vue');

        expect(result).toHaveProperty('code');
        expect(result.code).toContain('Shopware.Component.createExtendableSetup(');
        expect(result.code).toContain("name: 'sw-my-component'");
        expect(result.map).toBeNull();
    });

    it('delegates override blocks in .override.vue files', async () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);
        const source = `<script setup>
const count = 1;

swDefineOverride({});
</script>`;

        const result = await transformVueSource(plugin, source, '/example/sw-my-component.override.vue');

        expect(result).toHaveProperty('code');
        expect(result.code).toContain("Shopware.Component.overrideComponentSetup()('sw-my-component'");
    });

    it('rejects two base components that resolve to the same name', async () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);
        const source = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;

        await transformVueSource(plugin, source, '/a/sw-my-component.vue');

        await expect(transformVueSource(plugin, source, '/b/sw-my-component.vue')).rejects.toThrow(
            'Duplicate native setup base component name "sw-my-component"',
        );
    });

    it('allows an override to reuse its base component name', async () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);

        await transformVueSource(
            plugin,
            `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`,
            '/base/sw-my-component.vue',
        );

        await expect(
            transformVueSource(
                plugin,
                `<script setup>
swDefineOverride({});
</script>`,
                '/override/sw-my-component.override.vue',
            ),
        ).resolves.toHaveProperty('code');
    });

    it('re-transforms the same base file without a false duplicate', async () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);
        const source = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;

        await transformVueSource(plugin, source, '/example/sw-my-component.vue');

        await expect(transformVueSource(plugin, source, '/example/sw-my-component.vue')).resolves.toHaveProperty(
            'code',
        );
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
