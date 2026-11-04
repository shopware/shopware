/**
 * @sw-package framework
 */

import fs from 'node:fs/promises';
import { createPlugin, createVueFile, resolveAndLoadVueFile } from './helpers';

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
    async function loadedSetupFile(options = { scriptHmr: true }) {
        const plugin = createPlugin({ administrationRoot: process.cwd(), ...options });
        const vueFile = await createVueFile(source(), 'sw-hot-component.vue');
        const { loaded, resolvedId } = await resolveAndLoadVueFile(plugin, vueFile);

        return { plugin, vueFile, virtualId: resolvedId as string, meta: loaded?.meta?.shopwareSetupHmr };
    }

    async function edit(setup: Awaited<ReturnType<typeof loadedSetupFile>>, nextSource: string) {
        await fs.writeFile(setup.vueFile, nextSource);
        const loaded = await setup.plugin.load.call({ addWatchFile: jest.fn() }, setup.virtualId);

        return loaded?.meta?.shopwareSetupHmr;
    }

    it('names the authored file in the module meta', async () => {
        const setup = await loadedSetupFile();

        expect(setup.meta).toEqual(
            expect.objectContaining({ componentName: 'sw-hot-component', mode: 'base', file: setup.vueFile }),
        );
    });

    it('gives a template edit the versions of the loaded module', async () => {
        const setup = await loadedSetupFile();

        const templateEdit = await edit(setup, source({ label: 'Total' }));

        expect(templateEdit?.version).toBe(setup.meta?.version);
        expect(templateEdit?.structure).toBe(setup.meta?.structure);
    });

    it('gives a script edit a new script version and keeps the structure', async () => {
        const setup = await loadedSetupFile();

        const scriptEdit = await edit(setup, source({ count: 2 }));

        expect(scriptEdit?.version).not.toBe(setup.meta?.version);
        expect(scriptEdit?.structure).toBe(setup.meta?.structure);
    });

    it('gives an edit that renames a block a new structure', async () => {
        const setup = await loadedSetupFile();

        const blockEdit = await edit(setup, source({ block: 'sw_hot_content' }));

        expect(blockEdit?.structure).not.toBe(setup.meta?.structure);
    });

    it('gives an override template edit that starts reading a script local a new script version', async () => {
        const override = (template: string) => `<template>
<sw-block extends="sw_hot_body">
    ${template}
</sw-block>
</template>
<script setup>
const badge = 'new';

swDefineOverride({});
</script>`;
        const plugin = createPlugin({ administrationRoot: process.cwd(), scriptHmr: true });
        const vueFile = await createVueFile(override('<p>static</p>'), 'sw-hot-component.override.vue');
        const { loaded, resolvedId } = await resolveAndLoadVueFile(plugin, vueFile);

        await fs.writeFile(vueFile, override('<p>{{ badge }}</p>'));
        const templateEdit = await plugin.load.call({ addWatchFile: jest.fn() }, resolvedId as string);

        expect(templateEdit?.meta?.shopwareSetupHmr?.version).not.toBe(loaded?.meta?.shopwareSetupHmr?.version);
    });

    it('adds no module meta without script HMR', async () => {
        const setup = await loadedSetupFile({ scriptHmr: false });

        expect(setup.meta).toBeUndefined();
    });
});
