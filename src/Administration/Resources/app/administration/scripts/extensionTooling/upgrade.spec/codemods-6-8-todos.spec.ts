/**
 * @sw-package framework
 *
 * The comment-only 6.8 codemods end to end: a dry run counts the spots, `--write` marks them above the code,
 * and a second run writes nothing new but keeps listing them as manual work.
 */

import fs from 'fs';
import path from 'path';
import { upgradeExtensions } from '../upgrade';
import type { ExtensionUpgrade } from '../upgrade';
import { cleanupTempProject, writeFile } from '../test-helpers';
import { PLUGIN, SOURCE, commitAll, createUpgradeProject, readSource } from './fixtures';

jest.setTimeout(120_000);

const ADMINISTRATION = `${PLUGIN}/src/Resources/app/administration`;
const MARKED: Record<string, string> = {
    'vuex-state': 'component/acme-flow/index.js',
    'axios-v1': 'component/acme-flow/index.js',
    'worker-notification': 'main-worker.js',
    'order-detail-store': 'extension/sw-order-detail-general/index.js',
    'product-states': 'extension/sw-product-detail-base/index.js',
    'system-config-schema': 'component/acme-flow/index.js',
};

function report(extension: ExtensionUpgrade, id: string) {
    const found = extension.codemods.find((candidate) => candidate.codemod.id === id);

    if (!found) {
        throw new Error(`No report for ${id}`);
    }

    return found;
}

describe('scripts/extensionTooling/upgrade 6.8 comment-only codemods', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        ({ projectRoot, administrationRoot } = createUpgradeProject('sw-tooling-upgrade-todos-'));

        writeFile(path.join(projectRoot, SOURCE, 'component/acme-flow/index.js'), [
            'export default {',
            '    methods: {',
            '        async load() {',
            "            const flow = Shopware.State.get('swFlowState').flow;",
            '            const source = this.httpClient.CancelToken.source();',
            "            const cards = await this.systemConfigApiService.getConfig('AcmeUpgrade.config');",
            '',
            '            return { flow, source, cards };',
            '        },',
            '    },',
            '};',
        ]);
        writeFile(path.join(projectRoot, SOURCE, 'main-worker.js'), [
            "Shopware.WorkerNotification.register('AcmeIndexingMessage', {",
            "    name: 'Acme\\\\IndexingMessage',",
            '    fn: (next) => next(),',
            '});',
        ]);
        writeFile(path.join(projectRoot, SOURCE, 'extension/sw-order-detail-general/index.js'), [
            "Shopware.Component.override('sw-order-detail-general', {",
            "    inject: ['swOrderDetailOnLoadingChange'],",
            '',
            '    computed: {',
            '        busy() {',
            '            return this.isLoading;',
            '        },',
            '    },',
            '});',
        ]);
        writeFile(path.join(projectRoot, SOURCE, 'extension/sw-product-detail-base/index.js'), [
            "Shopware.Component.override('sw-product-detail-base', {",
            '    computed: {',
            '        isDownload() {',
            "            return this.productStates.includes('is-download');",
            '        },',
            '    },',
            '});',
        ]);
        writeFile(path.join(projectRoot, ADMINISTRATION, 'vite.config.mjs'), [
            "import { defineConfig } from 'vite';",
            '',
            'export default defineConfig({});',
        ]);
        commitAll(path.join(projectRoot, PLUGIN));
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    it('counts the spots in a dry run without writing', async () => {
        const before = readSource(projectRoot, 'component/acme-flow/index.js');
        const [extension] = (await upgradeExtensions({ projectRoot, administrationRoot })).extensions;

        for (const id of [...Object.keys(MARKED), 'vite-8']) {
            expect(report(extension, id).changes).toBeGreaterThanOrEqual(1);
        }

        expect(readSource(projectRoot, 'component/acme-flow/index.js')).toBe(before);
    });

    it('writes the TODOs above the spots and lists them as manual work', async () => {
        const [extension] = (await upgradeExtensions({ projectRoot, administrationRoot, write: true })).extensions;

        expect(extension.errors).toEqual([]);

        for (const [id, file] of Object.entries(MARKED)) {
            expect(readSource(projectRoot, file)).toContain(`// TODO Codemod(6.8, ${id}): `);
            expect(report(extension, id).manual.length).toBeGreaterThanOrEqual(1);
        }

        expect(fs.readFileSync(path.join(projectRoot, ADMINISTRATION, 'vite.config.mjs'), 'utf8')).toMatch(
            /^\/\/ TODO Codemod\(6\.8, vite-8\): /,
        );
        expect(readSource(projectRoot, 'extension/sw-order-detail-general/index.js')).toMatch(
            /\/\/ TODO Codemod\(6\.8, order-detail-store\): [^\n]+\n {4}inject: \['swOrderDetailOnLoadingChange'\],/,
        );
    });

    it('writes nothing new on a second run', async () => {
        await upgradeExtensions({ projectRoot, administrationRoot, write: true });
        commitAll(path.join(projectRoot, PLUGIN));

        const files = [...new Set(Object.values(MARKED))];
        const before = files.map((file) => readSource(projectRoot, file));
        const [extension] = (await upgradeExtensions({ projectRoot, administrationRoot, write: true })).extensions;

        expect(files.map((file) => readSource(projectRoot, file))).toEqual(before);

        for (const id of [...Object.keys(MARKED), 'vite-8']) {
            expect(report(extension, id).changes).toBe(0);
            expect(report(extension, id).manual.length).toBeGreaterThanOrEqual(1);
        }
    });
});
