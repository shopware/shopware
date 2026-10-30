/**
 * @sw-package framework
 */
import fs from 'node:fs/promises';
import { createPlugin, createVueFile, type HotUpdateOptions } from './helpers';

const initial =
    '<script setup lang="ts">const count = 1; swDefinePublic({ count });</script><template><p>Before</p></template>';

async function loadedComponent(source = initial) {
    const plugin = createPlugin({ administrationRoot: process.cwd(), scriptHmr: true });
    plugin.configResolved({ command: 'serve', build: { sourcemap: false } });
    const file = await createVueFile(source, 'sw-native-script.vue');
    const virtualId = `${file}.shopware-setup.vue`;
    const loaded = await plugin.load.call({ addWatchFile: jest.fn() }, virtualId);
    const virtual = { id: virtualId, url: '/native.vue', importers: new Set<HotUpdateOptions['modules'][number]>() };
    const context = {
        environment: {
            name: 'client',
            moduleGraph: { getModuleById: jest.fn(() => virtual), invalidateModule: jest.fn() },
            logger: { error: jest.fn() },
            hot: { send: jest.fn() },
            transformRequest: jest.fn().mockResolvedValue({ code: 'export const delta = 2' }),
        },
    };
    return { plugin, file, virtual, context, loaded };
}

function update(file: string, source: string, modules: HotUpdateOptions['modules'] = []): HotUpdateOptions {
    return { file, read: () => Promise.resolve(source), modules, type: 'update' };
}

describe('native setup script HMR routing', () => {
    it.each([
        ['script', initial.replace('count = 1', 'count = 2')],
        ['template', initial.replace('Before', 'After')],
    ])('keeps %s changes within the virtual component boundary', async (_kind, source) => {
        const { plugin, file, virtual, context, loaded } = await loadedComponent();
        const previousVersion = loaded?.meta?.shopwareSetupHmr?.version;
        expect(previousVersion).toBeDefined();
        expect(await plugin.hotUpdate.call(context, update(file, source))).toEqual([virtual]);
        expect(context.environment.hot.send).not.toHaveBeenCalled();
        await fs.writeFile(file, source);
        const next = await plugin.load.call({ addWatchFile: jest.fn() }, virtual.id);
        expect(next?.meta?.shopwareSetupHmr?.version === previousVersion).toBe(_kind === 'template');
    });

    it.each([false, true])('propagates a compiled helper to native boundaries (transitive: %s)', async (transitive) => {
        const { plugin, virtual, context, loaded } = await loadedComponent();
        const intermediate = { id: '/derived.ts', importers: new Set([virtual]) };
        const dependency = {
            id: '/helper.ts',
            url: '/helper.ts',
            importers: new Set([transitive ? intermediate : virtual]),
        };
        const version = loaded?.meta?.shopwareSetupHmr?.version;
        const result = await plugin.hotUpdate.call(context, update(dependency.id, '', [dependency]));
        expect(result).toEqual([dependency, virtual]);
        expect(context.environment.transformRequest).toHaveBeenCalledWith('/helper.ts');
        expect(context.environment.hot.send).not.toHaveBeenCalled();
        const next = await plugin.load.call({ addWatchFile: jest.fn() }, virtual.id);
        expect(next?.meta?.shopwareSetupHmr?.version).not.toBe(version);
    });

    it('updates both native importers of a shared helper through queried Vue submodules', async () => {
        const { plugin, virtual, context, loaded } = await loadedComponent();
        const secondFile = await createVueFile(initial, 'sw-second-native.vue');
        const second = {
            id: `${secondFile}.shopware-setup.vue`,
            url: '/second.vue',
            importers: new Set<HotUpdateOptions['modules'][number]>(),
        };
        const secondLoaded = await plugin.load.call({ addWatchFile: jest.fn() }, second.id);
        const script = { id: `${virtual.id}?vue&type=script`, url: '/native-script', importers: new Set([virtual]) };
        const dependency = { id: '/shared.ts', url: '/shared.ts', importers: new Set([script, second]) };
        const result = await plugin.hotUpdate.call(context, update(dependency.id, '', [dependency]));
        expect(result).toEqual(expect.arrayContaining([dependency, virtual, second]));
        expect(result).toHaveLength(3);
        expect((await plugin.load.call({ addWatchFile: jest.fn() }, virtual.id))?.meta?.shopwareSetupHmr?.version).not.toBe(
            loaded?.meta?.shopwareSetupHmr?.version,
        );
        expect((await plugin.load.call({ addWatchFile: jest.fn() }, second.id))?.meta?.shopwareSetupHmr?.version).not.toBe(
            secondLoaded?.meta?.shopwareSetupHmr?.version,
        );
    });

    it('preserves bootstrap importers rather than masking their full-reload propagation', async () => {
        const { plugin, virtual, context } = await loadedComponent();
        const bootstrap = { id: '/main.ts', importers: new Set<HotUpdateOptions['modules'][number]>() };
        const dependency = { id: '/shared.ts', url: '/shared.ts', importers: new Set([virtual, bootstrap]) };
        expect(await plugin.hotUpdate.call(context, update(dependency.id, '', [dependency]))).toEqual([dependency, virtual]);
        expect(dependency.importers.has(bootstrap)).toBe(true);
    });

    it('keeps the current module version intact on helper compile failure', async () => {
        const { plugin, virtual, context, loaded } = await loadedComponent();
        const dependency = { id: '/helper.ts', url: '/helper.ts', importers: new Set([virtual]) };
        context.environment.transformRequest.mockRejectedValueOnce(new Error('Invalid source'));
        await expect(plugin.hotUpdate.call(context, update(dependency.id, '', [dependency]))).rejects.toThrow(
            'Invalid source',
        );
        const next = await plugin.load.call({ addWatchFile: jest.fn() }, virtual.id);
        expect(next?.meta?.shopwareSetupHmr?.version).toBe(loaded?.meta?.shopwareSetupHmr?.version);
        expect(context.environment.hot.send).not.toHaveBeenCalled();
    });

    it.each([
        initial.replace('<p>Before</p>', '<sw-block name="new-block">Before</sw-block>'),
        initial.replace(
            '<script setup',
            '<script data-sfc-migration-module>export const globalRegistration = 1;</script><script setup',
        ),
    ])('reloads for structural or module-scope changes', async (source) => {
        const { plugin, file, context } = await loadedComponent();
        expect(await plugin.hotUpdate.call(context, update(file, source))).toEqual([]);
        expect(context.environment.hot.send).toHaveBeenCalledWith({ type: 'full-reload', path: '*' });
    });

    it('reloads when a loaded component is deleted', async () => {
        const { plugin, file, context } = await loadedComponent();
        expect(await plugin.hotUpdate.call(context, { ...update(file, ''), type: 'delete' })).toEqual([]);
        expect(context.environment.hot.send).toHaveBeenCalledWith({ type: 'full-reload', path: '*' });
    });

    it('changes the script version when a template needs a new private override binding', async () => {
        const plugin = createPlugin({ administrationRoot: process.cwd(), scriptHmr: true });
        plugin.configResolved({ command: 'serve', build: { sourcemap: false } });
        const source = `<script setup>const label = 'private'; swDefineOverride({});</script>
            <template><sw-block extends="content"><p>Before</p></sw-block></template>`;
        const file = await createVueFile(source, 'sw-private.override.vue');
        const id = `${file}.shopware-setup.vue`;
        const before = await plugin.load.call({ addWatchFile: jest.fn() }, id);
        await fs.writeFile(file, source.replace('<p>Before</p>', '<p>{{ label }}</p>'));
        const after = await plugin.load.call({ addWatchFile: jest.fn() }, id);
        expect(before?.meta?.shopwareSetupHmr?.version).toBeDefined();
        expect(after?.meta?.shopwareSetupHmr?.version).not.toBe(before?.meta?.shopwareSetupHmr?.version);
    });

    it('does not emit HMR ownership in production builds', async () => {
        const { plugin, virtual } = await loadedComponent();
        plugin.configResolved({ command: 'build', build: { sourcemap: false } });
        const loaded = await plugin.load.call({ addWatchFile: jest.fn() }, virtual.id);
        expect(loaded?.meta?.shopwareSetupHmr).toBeUndefined();
    });
});
