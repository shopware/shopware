/**
 * @sw-package framework
 */

import ShopwareSetupPlugin from './index';

describe('build/vite-plugins/shopware-setup', () => {
    it('returns a pre-transform Vite plugin', () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin();

        expect(plugin.name).toBe('shopware-vite-plugin-shopware-setup');
        expect(plugin.enforce).toBe('pre');
        expect(plugin).toHaveProperty('transform');
    });

    it('delegates Shopware setup Vue files to the shared transform', () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin();
        const source = `<script setup sw-component="sw-my-component">
const count = 1;
</script>`;

        const result = plugin.transform(source, '/example/component.vue');

        expect(result).toHaveProperty('code');
        expect(result.code).toContain("__swCreateScriptSetupExtendableComponent()('sw-my-component'");
    });

    it('ignores files without Shopware setup blocks', () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin();

        expect(plugin.transform('<script>const count = 1;</script>', '/example/component.vue')).toBeNull();
        expect(plugin.transform('const count = 1;', '/example/component.ts')).toBeNull();
    });
});
