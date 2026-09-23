/**
 * @sw-package framework
 */

import { createPlugin, createVueFile, type HotUpdateOptions, spyOnTransform } from './helpers';

describe('build/vite-plugins/shopware-setup hot updates', () => {
    const brokenSource = `<script setup>
const broken = { a: 1 b: 2 };

swDefinePublic({});
</script>`;

    function createHotUpdateContext(knownVirtualIds: string[], environmentName = 'client') {
        return {
            environment: {
                name: environmentName,
                moduleGraph: {
                    getModuleById: jest.fn((id: string) => (knownVirtualIds.includes(id) ? { id } : undefined)),
                },
                logger: { error: jest.fn<void, [message: unknown]>() },
            },
        };
    }

    function createHotUpdateOptions(
        file: string,
        {
            source = '',
            modules = [],
            type = 'update',
        }: Partial<Omit<HotUpdateOptions, 'file' | 'read'>> & {
            source?: string;
        } = {},
    ): HotUpdateOptions {
        return { file, modules, type, read: jest.fn(() => Promise.resolve(source)) };
    }

    it('maps a changed .vue file to its virtual module so the dev server invalidates it', async () => {
        const plugin = createPlugin();
        const virtualId = '/example/sw-my-component.vue.shopware-setup.vue';
        const context = createHotUpdateContext([virtualId]);
        const otherModule = { id: '/example/other-module.ts' };

        // Vite keys hot updates by changed file, and the real file never becomes a module - without
        // this mapping an edit invalidated nothing (issue #19469).
        const result = await plugin.hotUpdate.call(
            context,
            createHotUpdateOptions('/example/sw-my-component.vue', { modules: [otherModule] }),
        );

        // Appended to the modules Vite already considers affected, not substituted.
        expect(result).toEqual([otherModule, { id: virtualId }]);
    });

    it('leaves a .vue file alone that was never redirected to a virtual module', async () => {
        const plugin = createPlugin();
        const context = createHotUpdateContext([]);

        // A plain SFC stays a real module; @vitejs/plugin-vue handles its hot update natively.
        const result = await plugin.hotUpdate.call(context, createHotUpdateOptions('/example/PlainComponent.vue'));

        expect(result).toBeUndefined();
    });

    it('does not map a virtual module id onto itself', async () => {
        const plugin = createPlugin();
        const context = createHotUpdateContext([]);
        const options = createHotUpdateOptions('/example/sw-my-component.vue.shopware-setup.vue');

        // The mapping's fixed point: the virtual id must not be mapped onto itself again.
        const result = await plugin.hotUpdate.call(context, options);

        expect(result).toBeUndefined();
        expect(context.environment.moduleGraph.getModuleById).not.toHaveBeenCalled();
        expect(options.read).not.toHaveBeenCalled();
    });

    it('reports a transform failure at its file location without skipping virtual-module invalidation', async () => {
        const plugin = createPlugin();
        const file = '/example/sw-broken-component.vue';
        const virtualId = `${file}.shopware-setup.vue`;
        const context = createHotUpdateContext([virtualId]);

        // Without the report, a save with no browser attached printed nothing (issue #19562). Throwing
        // would not do: Vite sends a hook error to the client overlay, never to the terminal.
        const result = await plugin.hotUpdate.call(context, createHotUpdateOptions(file, { source: brokenSource }));

        expect(result).toEqual([{ id: virtualId }]);
        expect(context.environment.logger.error).toHaveBeenCalledTimes(1);
        expect(context.environment.logger.error).toHaveBeenCalledWith(
            expect.stringMatching(
                /^\[shopware-setup\] \/example\/sw-broken-component\.vue:2:23\n.*Unable to parse Shopware setup script/s,
            ),
        );
    });

    it('reports a failure without a source offset by file name only', async () => {
        const plugin = createPlugin();
        const context = createHotUpdateContext([]);
        const transformSpy = spyOnTransform().mockImplementation(() => {
            throw new Error('Transform unavailable');
        });

        await plugin.hotUpdate.call(context, createHotUpdateOptions('/example/sw-my-component.vue'));

        expect(context.environment.logger.error).toHaveBeenCalledWith(
            '[shopware-setup] /example/sw-my-component.vue\nTransform unavailable',
        );

        transformSpy.mockRestore();
    });

    it('reports a failure once, from the client environment only', async () => {
        const plugin = createPlugin();
        const context = createHotUpdateContext([], 'ssr');
        const options = createHotUpdateOptions('/example/sw-broken-component.vue', { source: brokenSource });

        // Vite's dev server also runs hotUpdate for its ssr environment, which would log every error twice.
        await plugin.hotUpdate.call(context, options);

        expect(context.environment.logger.error).not.toHaveBeenCalled();
        expect(options.read).not.toHaveBeenCalled();
    });

    it('does not read or report a deleted file', async () => {
        const plugin = createPlugin();
        const context = createHotUpdateContext([]);
        const options = createHotUpdateOptions('/example/sw-deleted-component.vue', { type: 'delete' });

        await plugin.hotUpdate.call(context, options);

        expect(context.environment.logger.error).not.toHaveBeenCalled();
        expect(options.read).not.toHaveBeenCalled();
    });

    it('reuses the hotUpdate transform in the load it triggers instead of transforming twice', async () => {
        const plugin = createPlugin();
        const source = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;
        const vueFile = await createVueFile(source, 'sw-hot-component.vue');
        const transformSpy = spyOnTransform();

        await plugin.hotUpdate.call(createHotUpdateContext([]), createHotUpdateOptions(vueFile, { source }));
        const loaded = await plugin.load.call({ addWatchFile: jest.fn() }, `${vueFile}.shopware-setup.vue`);

        expect(loaded).toHaveProperty('code');
        expect(transformSpy.mock.calls.filter(([, fileName]) => fileName === vueFile)).toHaveLength(1);

        transformSpy.mockRestore();
    });
});
