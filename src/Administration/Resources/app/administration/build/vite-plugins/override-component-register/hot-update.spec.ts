/**
 * @sw-package framework
 */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import overrideComponentRegisterPlugin from './index';

type CallablePlugin = {
    configResolved(config: { command: 'serve' }): void;
    transform(code: string, id: string): { code: string } | null;
    hotUpdate(options: { type: 'create' | 'delete' | 'update'; file: string }): [] | undefined;
};

describe('build/vite-plugins/override-component-register hot updates', () => {
    const roots: string[] = [];

    function setup({ loadedEntry = true } = {}) {
        const root = fs.mkdtempSync(path.join(os.tmpdir(), 'sw-override-discovery-'));
        roots.push(root);
        const entry = path.join(root, 'main.ts');
        const file = path.join(root, 'sw-example.override.vue');
        const plugin = overrideComponentRegisterPlugin({ root, pluginEntryFile: entry }) as unknown as CallablePlugin;
        const entryModule = { id: entry };
        const context = {
            environment: {
                name: 'client',
                moduleGraph: {
                    getModuleById: jest.fn(() => (loadedEntry ? entryModule : undefined)),
                    invalidateModule: jest.fn(),
                },
                hot: { send: jest.fn() },
            },
        };
        plugin.configResolved({ command: 'serve' });

        const hotUpdate = (type: 'create' | 'delete' | 'update') => plugin.hotUpdate.call(context, { type, file });
        const entryCode = () => plugin.transform('// entry', entry)?.code ?? '// entry';

        return { file, entryModule, context, hotUpdate, entryCode };
    }

    afterEach(() => {
        roots.splice(0).forEach((root) => fs.rmSync(root, { recursive: true, force: true }));
    });

    it('reloads the page with an entry that imports an added override file', () => {
        const { file, entryModule, context, hotUpdate, entryCode } = setup();
        fs.writeFileSync(file, '<script setup>swDefineOverride({});</script>');

        expect(hotUpdate('create')).toEqual([]);
        expect(context.environment.moduleGraph.invalidateModule).toHaveBeenCalledWith(entryModule);
        expect(context.environment.hot.send).toHaveBeenCalledWith({ type: 'full-reload', path: '*' });
        expect(entryCode()).toContain("from './sw-example.override.vue'");
    });

    it('reloads the page with an entry that no longer imports a deleted override file', () => {
        const { file, context, hotUpdate, entryCode } = setup();
        fs.writeFileSync(file, '<script setup>swDefineOverride({});</script>');
        hotUpdate('create');
        fs.unlinkSync(file);

        expect(hotUpdate('delete')).toEqual([]);
        expect(context.environment.hot.send).toHaveBeenCalledTimes(2);
        expect(entryCode()).not.toContain('sw-example.override.vue');
    });

    it('leaves an edit of an override file to its own hot update', () => {
        const { file, context, hotUpdate } = setup();
        fs.writeFileSync(file, '<script setup>swDefineOverride({});</script>');
        hotUpdate('create');

        expect(hotUpdate('update')).toBeUndefined();
        expect(context.environment.hot.send).toHaveBeenCalledTimes(1);
    });

    it('reloads the page once an override file created empty gets its content', () => {
        const { file, context, hotUpdate, entryCode } = setup();
        fs.writeFileSync(file, '');

        expect(hotUpdate('create')).toBeUndefined();
        expect(entryCode()).not.toContain('sw-example.override.vue');

        fs.writeFileSync(file, '<script setup>swDefineOverride({});</script>');

        expect(hotUpdate('update')).toEqual([]);
        expect(context.environment.hot.send).toHaveBeenCalledTimes(1);
        expect(context.environment.hot.send).toHaveBeenCalledWith({ type: 'full-reload', path: '*' });
        expect(entryCode()).toContain("from './sw-example.override.vue'");
    });

    it('imports an override file added before the entry was loaded without reloading the page', () => {
        const { file, context, hotUpdate, entryCode } = setup({ loadedEntry: false });
        fs.writeFileSync(file, '<script setup>swDefineOverride({});</script>');

        expect(hotUpdate('create')).toBeUndefined();
        expect(context.environment.hot.send).not.toHaveBeenCalled();
        expect(entryCode()).toContain("from './sw-example.override.vue'");
    });
});
