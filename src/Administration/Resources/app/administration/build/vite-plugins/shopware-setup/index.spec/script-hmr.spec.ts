/**
 * @sw-package framework
 */

import fs from 'node:fs/promises';
import { createPlugin, createVueFile, type HotUpdateModule, resolveAndLoadVueFile } from './helpers';

const source = ({ block = 'sw_hot_body', label = 'Count', count = 1 } = {}) => `<template>
<sw-block name="${block}">
    <p>${label} {{ count }}</p>
</sw-block>
</template>
<script setup>
const count = ${count};

swDefinePublic({ count });
</script>`;

describe('build/vite-plugins/shopware-setup script hot updates', () => {
    function createHotUpdateContext(knownVirtualIds: string[]) {
        return {
            environment: {
                name: 'client',
                moduleGraph: {
                    getModuleById: jest.fn((id: string) => (knownVirtualIds.includes(id) ? { id } : undefined)),
                    invalidateModule: jest.fn(),
                },
                logger: { error: jest.fn() },
                hot: { send: jest.fn() },
            },
        };
    }

    async function loadedSetupFile(options = { scriptHmr: true }) {
        const plugin = createPlugin({ administrationRoot: process.cwd(), ...options });
        const vueFile = await createVueFile(source(), 'sw-hot-component.vue');
        const { loaded, resolvedId } = await resolveAndLoadVueFile(plugin, vueFile);

        return { plugin, vueFile, virtualId: resolvedId as string, loaded };
    }

    async function hotUpdate(setup: Awaited<ReturnType<typeof loadedSetupFile>>, nextSource: string) {
        const context = createHotUpdateContext([setup.virtualId]);
        await fs.writeFile(setup.vueFile, nextSource);
        const result = await setup.plugin.hotUpdate.call(context, {
            file: setup.vueFile,
            modules: [],
            type: 'update',
            read: () => Promise.resolve(nextSource),
        });

        return { result, send: context.environment.hot.send };
    }

    it('gives a template edit the script version of the loaded module and a script edit a new one', async () => {
        const setup = await loadedSetupFile();
        const version = setup.loaded?.meta?.shopwareSetupHmr?.version;

        await fs.writeFile(setup.vueFile, source({ label: 'Total' }));
        const templateEdit = await setup.plugin.load.call({ addWatchFile: jest.fn() }, setup.virtualId);
        await fs.writeFile(setup.vueFile, source({ count: 2 }));
        const scriptEdit = await setup.plugin.load.call({ addWatchFile: jest.fn() }, setup.virtualId);

        expect(version).toEqual(expect.any(String));
        expect(templateEdit?.meta?.shopwareSetupHmr?.version).toBe(version);
        expect(scriptEdit?.meta?.shopwareSetupHmr?.version).not.toBe(version);
    });

    it('hot updates a script edit', async () => {
        const setup = await loadedSetupFile();

        const { result, send } = await hotUpdate(setup, source({ count: 2 }));

        expect(result).toEqual([{ id: setup.virtualId }]);
        expect(send).not.toHaveBeenCalled();
    });

    it('reloads the page when an edit renames a block', async () => {
        const setup = await loadedSetupFile();

        const { result, send } = await hotUpdate(setup, source({ block: 'sw_hot_content' }));

        expect(result).toEqual([]);
        expect(send).toHaveBeenCalledWith({ type: 'full-reload', path: '*' });
    });

    it('reloads the page when a file imported by a setup SFC changes', async () => {
        const setup = await loadedSetupFile();
        const context = createHotUpdateContext([]);
        const setupModule: HotUpdateModule = { id: setup.virtualId, importers: new Set() };
        const helper: HotUpdateModule = {
            id: '/example/use-count.ts',
            importers: new Set([{ id: '/example/use-total.ts', importers: new Set([setupModule]) }]),
        };

        const result = await setup.plugin.hotUpdate.call(context, {
            file: helper.id,
            modules: [helper],
            type: 'update',
            read: () => Promise.resolve(''),
        });

        expect(result).toEqual([]);
        expect(context.environment.hot.send).toHaveBeenCalledWith({ type: 'full-reload', path: '*' });
    });

    it('keeps the default hot update without script HMR', async () => {
        const setup = await loadedSetupFile({ scriptHmr: false });
        const context = createHotUpdateContext([]);
        const helper: HotUpdateModule = {
            id: '/example/use-count.ts',
            importers: new Set([{ id: setup.virtualId, importers: new Set() }]),
        };

        const helperResult = await setup.plugin.hotUpdate.call(context, {
            file: helper.id,
            modules: [helper],
            type: 'update',
            read: () => Promise.resolve(''),
        });
        const { result, send } = await hotUpdate(setup, source({ block: 'sw_hot_content' }));

        expect(setup.loaded?.meta).toBeUndefined();
        expect(helperResult).toBeUndefined();
        expect(result).toEqual([{ id: setup.virtualId }]);
        expect(send).not.toHaveBeenCalled();
    });
});
