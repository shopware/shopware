/**
 * @sw-package framework
 */

import path from 'node:path';
import ShopwareSetupPlugin from './index';

const pluginOptions = {
    administrationRoot: process.cwd(),
};

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

        const result = await plugin.transform(source, '/example/sw-my-component.vue');

        expect(result).toHaveProperty('code');
        expect(result.code).toContain('Shopware.Component.attachOverrides(');
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

        const result = await plugin.transform(source, '/example/sw-my-component.override.vue');

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

        await plugin.transform(source, '/a/sw-my-component.vue');

        await expect(plugin.transform(source, '/b/sw-my-component.vue')).rejects.toThrow(
            'Duplicate native setup base component name "sw-my-component"',
        );
    });

    it('allows an override to reuse its base component name', async () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);

        await plugin.transform(
            `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`,
            '/base/sw-my-component.vue',
        );

        await expect(
            plugin.transform(
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

        await plugin.transform(source, '/example/sw-my-component.vue');

        await expect(plugin.transform(source, '/example/sw-my-component.vue')).resolves.toHaveProperty('code');
    });

    it('releases a component name when its file is deleted so a move does not look like a duplicate', async () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);
        const source = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;

        await plugin.transform(source, '/old/sw-my-component.vue');

        // Moving a file arrives as a delete of the old path plus a transform of the new one. Without the
        // release the old path would still hold the name and the move would be reported as a duplicate
        // for the rest of the dev session.
        plugin.watchChange('/old/sw-my-component.vue', { event: 'delete' });

        await expect(plugin.transform(source, '/new/sw-my-component.vue')).resolves.toHaveProperty('code');
    });

    it('keeps reporting a genuine duplicate after an unrelated file is deleted', async () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);
        const source = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;

        await plugin.transform(source, '/a/sw-my-component.vue');
        plugin.watchChange('/somewhere/else/sw-other-component.vue', { event: 'delete' });

        await expect(plugin.transform(source, '/b/sw-my-component.vue')).rejects.toThrow(
            'Duplicate native setup base component name "sw-my-component"',
        );
    });

    it('rejects Vue files without a script setup block', async () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);

        await expect(plugin.transform('<script>const count = 1;</script>', '/example/sw-component.vue')).rejects.toThrow(
            'A Shopware setup component needs a <script setup> block.',
        );
    });

    it('ignores files without Shopware setup blocks', async () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);

        await expect(plugin.transform('const count = 1;', '/example/component.ts')).resolves.toBeNull();
    });

    it('loads the shared transform once for the whole process', async () => {
        // The memo is module state, and every test above already loaded the real transform through it, so
        // this needs its own module instance to observe the first load at all.
        let IsolatedShopwareSetupPlugin;

        jest.isolateModules(() => {
            IsolatedShopwareSetupPlugin = require('./index').default;
        });

        // `src` rather than a made-up path: the loader resolves the transform relative to the root, and
        // `src/build/vue-setup-transform` does not exist - while staying inside the package keeps the
        // require resolvable, which a path outside it would not be.
        /** @type {import('vite').Plugin} */
        const plugin = IsolatedShopwareSetupPlugin({ administrationRoot: path.join(process.cwd(), 'src') });
        const source = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;

        // A bad `administrationRoot` is a config error, not a per-file one. The loader caches its promise,
        // so both files reject with the *same* error object - which is what proves the module import
        // happened once rather than once per `.vue` file.
        const first = await plugin.transform(source, '/example/sw-first.vue').catch((error) => error);
        const second = await plugin.transform(source, '/example/sw-second.vue').catch((error) => error);

        expect(first.message).toContain('build/vue-setup-transform/index.js');
        expect(second).toBe(first);
    });
});
