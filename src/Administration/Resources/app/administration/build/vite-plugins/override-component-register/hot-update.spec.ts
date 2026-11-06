/**
 * @sw-package framework
 */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import overrideComponentRegisterPlugin from './index';

type CallablePlugin = {
    configResolved(): void;
    transform(code: string, id: string): { code: string } | null;
    hotUpdate(options: { type: 'create' | 'delete' | 'update'; file: string }): [] | undefined;
};

function setup(scriptHmr = true) {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'sw-override-hmr-'));
    const entry = path.join(root, 'main.ts');
    const file = path.join(root, 'sw-example.override.vue');
    const plugin = overrideComponentRegisterPlugin({ root, pluginEntryFile: entry, scriptHmr }) as unknown as CallablePlugin;
    const module = { id: entry };
    const context = {
        environment: {
            name: 'client',
            moduleGraph: {
                getModuleById: jest.fn(() => module),
                invalidateModule: jest.fn(),
            },
            hot: { send: jest.fn() },
        },
    };
    plugin.configResolved();
    return { root, entry, file, plugin, context, module };
}

describe('native override discovery during script HMR', () => {
    const roots: string[] = [];
    afterEach(() => roots.forEach((root) => fs.rmSync(root, { recursive: true, force: true })));

    it('refreshes the generated entry before reloading added and removed overrides', () => {
        const { root, entry, file, plugin, context, module } = setup();
        roots.push(root);
        fs.writeFileSync(file, '<script setup>swDefineOverride({});</script>');
        expect(plugin.hotUpdate.call(context, { type: 'create', file })).toEqual([]);
        expect(plugin.transform('// entry', entry)?.code).toContain('sw-example.override.vue');
        expect(context.environment.moduleGraph.invalidateModule).toHaveBeenCalledWith(module);
        expect(context.environment.hot.send).toHaveBeenCalledTimes(1);
        fs.unlinkSync(file);
        expect(plugin.hotUpdate.call(context, { type: 'delete', file })).toEqual([]);
        expect(plugin.transform('// entry', entry)).toBeNull();
        expect(context.environment.hot.send).toHaveBeenCalledTimes(2);
    });

    it.each([false, true])('keeps ordinary script edits on their component boundary (enabled: %s)', (enabled) => {
        const { root, file, plugin, context } = setup(enabled);
        roots.push(root);
        fs.writeFileSync(file, '<script setup>swDefineOverride({});</script>');
        expect(plugin.hotUpdate.call(context, { type: 'update', file })).toBeUndefined();
        expect(context.environment.hot.send).not.toHaveBeenCalled();
    });

    it('preserves existing discovery semantics with script HMR disabled', () => {
        const { root, file, plugin, context } = setup(false);
        roots.push(root);
        fs.writeFileSync(file, '<script setup>swDefineOverride({});</script>');
        expect(plugin.hotUpdate.call(context, { type: 'create', file })).toBeUndefined();
        expect(context.environment.hot.send).not.toHaveBeenCalled();
    });
});
