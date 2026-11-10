/**
 * @sw-package framework
 *
 * The 6.8 script rewrite codemods end to end: what a dry run counts, what `--write` produces and that a second run
 * changes nothing.
 */

import path from 'path';
import { upgradeExtensions } from '../upgrade';
import type { ExtensionUpgrade } from '../upgrade';
import { cleanupTempProject, writeFile } from '../test-helpers';
import { PLUGIN, SOURCE, commitAll, createUpgradeProject, readSource } from './fixtures';

jest.setTimeout(120_000);

const ORDER_LIST_SCRIPT = 'extension/sw-order-list/index.js';
const ORDER_LIST_TEMPLATE = 'extension/sw-order-list/sw-order-list.html.twig';
const MODULE = 'module/acme-example/index.js';
const FILES = [ORDER_LIST_SCRIPT, ORDER_LIST_TEMPLATE, MODULE];
const IDS = [
    'filter-computeds',
    'order-list-helpers',
    'module-snippets',
    'asset-paths',
    'snippet-keys',
];

function report(extension: ExtensionUpgrade, id: string) {
    const found = extension.codemods.find((candidate) => candidate.codemod.id === id);

    if (!found) {
        throw new Error(`No report for ${id}`);
    }

    return found;
}

describe('scripts/extensionTooling/upgrade 6.8 script codemods', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        ({ projectRoot, administrationRoot } = createUpgradeProject('sw-tooling-upgrade-scripts-'));

        writeFile(path.join(projectRoot, SOURCE, ORDER_LIST_SCRIPT), [
            "import template from './sw-order-list.html.twig';",
            '',
            "Shopware.Component.override('sw-order-list', {",
            '    template,',
            '});',
        ]);
        writeFile(path.join(projectRoot, SOURCE, ORDER_LIST_TEMPLATE), [
            '{% block sw_order_list_grid_columns_delivery_state %}',
            '    <img :src="assetFilter(\'/administration/administration/static/img/cms/preview_camera_large.jpg\')">',
            '    <span>{{ dateFilter(item.orderDateTime) }}</span>',
            '    <span>{{ getDeliveryState(item).translated.name }}</span>',
            '    <span>{{ item.deliveries[0].shippingOrderAddress.city }}</span>',
            "    <span>{{ $t('sw-settings-customer-group.detail.fieldDisplayGrossLabel') }}</span>",
            '{% endblock %}',
        ]);
        writeFile(path.join(projectRoot, SOURCE, MODULE), [
            "import de from './snippet/de.json';",
            "import en from './snippet/en.json';",
            '',
            "Shopware.Module.register('acme-example', {",
            "    type: 'plugin',",
            '    snippets: {',
            "        'de-DE': de,",
            "        'en-GB': en,",
            '    },',
            '});',
        ]);
        writeFile(path.join(projectRoot, SOURCE, 'module/acme-example/snippet/de.json'), '{}\n');
        writeFile(path.join(projectRoot, SOURCE, 'module/acme-example/snippet/en.json'), '{}\n');
        commitAll(path.join(projectRoot, PLUGIN));
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    it('counts the changes and TODOs in a dry run without writing', async () => {
        const before = FILES.map((file) => readSource(projectRoot, file));
        const { extensions } = await upgradeExtensions({ projectRoot, administrationRoot });
        const [extension] = extensions;

        expect(report(extension, 'filter-computeds').changes).toBe(1);
        // One rewrite and one codemod comment for `item.deliveries`
        expect(report(extension, 'order-list-helpers').changes).toBe(2);
        expect(report(extension, 'module-snippets').changes).toBe(1);
        expect(report(extension, 'asset-paths').changes).toBe(1);
        expect(report(extension, 'snippet-keys').changes).toBe(1);
        expect(FILES.map((file) => readSource(projectRoot, file))).toEqual(before);
    });

    it('writes the rewrites and leaves the TODO spots as manual work', async () => {
        const { extensions } = await upgradeExtensions({ projectRoot, administrationRoot, write: true });
        const [extension] = extensions;
        const script = readSource(projectRoot, ORDER_LIST_SCRIPT);
        const twig = readSource(projectRoot, ORDER_LIST_TEMPLATE);

        expect(extension.errors).toEqual([]);
        expect(script).toContain(
            [
                '    template,',
                '',
                '    computed: {',
                '        dateFilter() {',
                "            return Shopware.Filter.getByName('date');",
                '        },',
                '    },',
            ].join('\n'),
        );
        expect(twig).toContain('preview_camera_large.webp');
        expect(twig).toContain('{{ item.primaryOrderDelivery?.stateMachineState?.translated.name }}');
        expect(twig).toContain(
            '    {# TODO Codemod(6.8, order-list-helpers): sw-order-list no longer loads order.deliveries in 6.8.',
        );
        expect(twig).toContain("$t('sw-settings-customer-group.detail.taxDisplay.label')");
        expect(twig).toContain('{{ dateFilter(item.orderDateTime) }}');
        expect(readSource(projectRoot, MODULE)).toBe(
            [
                "Shopware.Module.register('acme-example', {",
                "    type: 'plugin',",
                '});',
                '',
            ].join('\n'),
        );
        expect(report(extension, 'order-list-helpers').manual).toEqual([
            expect.objectContaining({ file: `src/Resources/app/administration/src/${ORDER_LIST_TEMPLATE}` }),
        ]);
    });

    it('changes nothing on a second run', async () => {
        await upgradeExtensions({ projectRoot, administrationRoot, write: true });
        commitAll(path.join(projectRoot, PLUGIN));

        const before = FILES.map((file) => readSource(projectRoot, file));
        const { extensions } = await upgradeExtensions({ projectRoot, administrationRoot, write: true });
        const [extension] = extensions;

        expect(FILES.map((file) => readSource(projectRoot, file))).toEqual(before);

        for (const id of IDS) {
            expect(report(extension, id).changes).toBe(0);
        }

        expect(report(extension, 'order-list-helpers').manual).toHaveLength(1);
    });
});
