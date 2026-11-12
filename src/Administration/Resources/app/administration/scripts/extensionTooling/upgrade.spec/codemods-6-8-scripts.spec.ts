/**
 * @sw-package framework
 *
 * Acceptance spec of the 6.8 script rewrite codemods: one fixture plugin with a realistic extension file per case
 * and one real `--write` run. Like `codemods.spec.ts`, it checks the counts, a few lines per case and the spots
 * the codemods mark. The rule specs pin the exact output.
 */

import path from 'path';
import { upgradeExtensions } from '../upgrade';
import type { ExtensionUpgrade } from '../upgrade';
import { cleanupTempProject, writeFile } from '../test-helpers';
import { PLUGIN, SOURCE, commitAll, createUpgradeProject, readSource } from './fixtures';

jest.setTimeout(120_000);

const text = (...rows: string[]) => `${rows.join('\n')}\n`;

const FIXTURES: Record<string, string> = {
    'module/acme-example/index.js': text(
        "import de from './snippet/de.json';",
        "import en from './snippet/en.json';",
        '',
        "Shopware.Module.register('acme-example', {",
        "    type: 'plugin',",
        "    name: 'acme-example',",
        "    title: 'acme-example.general.mainMenuItemGeneral',",
        '    snippets: {',
        "        'de-DE': de,",
        "        'en-GB': en,",
        '    },',
        '    routes: {',
        "        list: { component: 'acme-example-list', path: 'list' },",
        '    },',
        '});',
    ),
    'module/acme-example/snippet/de.json': text('{ "acme-example": { "general": { "mainMenuItemGeneral": "Beispiel" } } }'),
    'module/acme-example/snippet/en.json': text('{ "acme-example": { "general": { "mainMenuItemGeneral": "Example" } } }'),
    'module/acme-legacy/index.js': text(
        "import de from './snippet/de';",
        '',
        "Shopware.Module.register('acme-legacy', {",
        "    type: 'plugin',",
        "    name: 'acme-legacy',",
        '    snippets: {',
        "        'de-DE': de,",
        '    },',
        '});',
    ),
    'module/acme-legacy/snippet/de.js': text("export default { 'acme-legacy': { title: 'Altlast' } };"),
    'extension/sw-product-list/index.js': text(
        "import template from './sw-product-list.html.twig';",
        '',
        "Shopware.Component.override('sw-product-list', {",
        '    template,',
        '',
        '    methods: {',
        '        getProductColumns() {',
        '            return [',
        "                ...this.$super('getProductColumns'),",
        "                { property: 'createdAt', label: 'acme-upgrade.productList.columnCreatedAt' },",
        '            ];',
        '        },',
        '    },',
        '});',
    ),
    'extension/sw-product-list/sw-product-list.html.twig': text(
        '{% block sw_product_list_grid_columns %}',
        '    {% parent %}',
        '',
        '    <template #column-createdAt="{ item }">',
        "        {{ dateFilter(item.createdAt, { hour: '2-digit', minute: '2-digit' }) }}",
        '    </template>',
        '{% endblock %}',
    ),
    'extension/sw-flow-list/index.ts': text(
        "import template from './sw-flow-list.html.twig';",
        '',
        "Shopware.Component.override('sw-flow-list', {",
        '    template,',
        '});',
    ),
    'extension/sw-flow-list/sw-flow-list.html.twig': text(
        '{% block sw_flow_list_empty_state %}',
        '    <img',
        '        class="acme-flow-list__empty-state"',
        '        :src="assetFilter(\'/acmeupgrade/static/img/flow-empty-state.svg\')"',
        '        alt=""',
        '    >',
        '{% endblock %}',
    ),
    'extension/sw-order-list/index.js': text(
        "import template from './sw-order-list.html.twig';",
        '',
        "Shopware.Component.override('sw-order-list', {",
        '    template,',
        '',
        '    methods: {',
        '        isShipped(order) {',
        "            return this.getDeliveryState(order)?.technicalName === 'shipped';",
        '        },',
        '    },',
        '});',
    ),
    'extension/sw-order-list/sw-order-list.html.twig': text(
        '{% block sw_order_list_grid_columns_delivery_address %}',
        '<template #column-primaryOrderDelivery.shippingOrderAddress.street="{ item }">',
        '    <template v-if="getDeliveryAddress(item)">',
        '        {{ getDeliveryAddress(item).street }},',
        '        {{ getDeliveryAddress(item).city }}',
        '        <span v-if="item.deliveries.length > 1">',
        "            {{ $t('acme-upgrade.orderList.moreDeliveries') }}",
        '        </span>',
        '    </template>',
        '</template>',
        '{% endblock %}',
    ),
    'component/acme-tax-display/index.js': text(
        "import template from './acme-tax-display.html.twig';",
        '',
        "Shopware.Component.register('acme-tax-display', {",
        '    template,',
        '});',
    ),
    'component/acme-tax-display/acme-tax-display.html.twig': text(
        '{% block acme_tax_display %}',
        '<div',
        '    class="acme-tax-display"',
        '    :title="$t(\'sw-settings-customer-group.detail.fieldDisplayGrossLabel\')"',
        '>',
        "    {{ $t('global.sw-condition.condition.cartTaxDisplay.gross') }}",
        '</div>',
        '{% endblock %}',
    ),
    'module/sw-cms/blocks/acme-teaser/preview/index.js': text(
        "import template from './sw-cms-preview-acme-teaser.html.twig';",
        '',
        "Shopware.Component.register('sw-cms-preview-acme-teaser', {",
        '    template,',
        '',
        '    computed: {',
        '        assetFilter() {',
        "            return Shopware.Filter.getByName('asset');",
        '        },',
        '    },',
        '});',
    ),
    'module/sw-cms/blocks/acme-teaser/preview/sw-cms-preview-acme-teaser.html.twig': text(
        '{% block sw_cms_block_acme_teaser_preview %}',
        '<div class="sw-cms-preview-acme-teaser">',
        '    <img :src="assetFilter(\'/administration/administration/static/img/cms/preview_mountain_large.jpg\')" alt="">',
        '</div>',
        '{% endblock %}',
    ),
};

const SCRIPT_CODEMODS = [
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

/** The spots the report lists as manual work, as `<file below src>:<line>`, sorted. */
function manualSpots(extension: ExtensionUpgrade, id: string): string[] {
    return report(extension, id)
        .manual.map(({ file, line }) => `${file.replace('src/Resources/app/administration/src/', '')}:${line}`)
        .sort();
}

describe('scripts/extensionTooling/upgrade 6.8 script codemods', () => {
    let projectRoot: string;
    let administrationRoot: string;
    let written: ExtensionUpgrade;

    beforeAll(async () => {
        ({ projectRoot, administrationRoot } = createUpgradeProject('sw-tooling-upgrade-scripts-'));

        Object.entries(FIXTURES).forEach(([file, content]) => writeFile(path.join(projectRoot, SOURCE, file), content));
        commitAll(path.join(projectRoot, PLUGIN));

        [written] = (await upgradeExtensions({ projectRoot, administrationRoot, write: true })).extensions;

        if (written.errors.length > 0) {
            throw new Error(`The upgrade failed: ${written.errors.join('; ')}`);
        }
    });

    afterAll(() => {
        cleanupTempProject(projectRoot);
    });

    it('counts the changes of each codemod and lists the spots it marks as manual work', () => {
        expect(
            Object.fromEntries(
                SCRIPT_CODEMODS.map((id) => [
                    id,
                    { changes: report(written, id).changes, manual: manualSpots(written, id) },
                ]),
            ),
        ).toEqual({
            'filter-computeds': { changes: 2, manual: [] },
            'order-list-helpers': { changes: 5, manual: ['extension/sw-order-list/sw-order-list.html.twig:6'] },
            'module-snippets': { changes: 2, manual: ['module/acme-legacy/index.js:7'] },
            'asset-paths': { changes: 1, manual: [] },
            'snippet-keys': { changes: 2, manual: [] },
        });
    });

    it('module-snippets: removes the snippets property and its imports when the server loads the JSON files', () => {
        const source = readSource(projectRoot, 'module/acme-example/index.js');

        expect(source).not.toContain('snippets');
        expect(source).not.toContain('import');
        expect(source).toContain("list: { component: 'acme-example-list', path: 'list' },");
    });

    it('module-snippets: marks the snippets property when the server does not load the imported files', () => {
        const source = readSource(projectRoot, 'module/acme-legacy/index.js');

        expect(source).toContain('    // TODO Codemod(6.8, module-snippets): ');
        expect(source).toContain("import de from './snippet/de';");
        expect(source).toContain("        'de-DE': de,");
    });

    it('filter-computeds: adds the dateFilter computed to an override whose imported template reads it', () => {
        expect(readSource(projectRoot, 'extension/sw-product-list/index.js')).toContain(
            "return Shopware.Filter.getByName('date');",
        );
        expect(readSource(projectRoot, 'extension/sw-product-list/sw-product-list.html.twig')).toBe(
            FIXTURES['extension/sw-product-list/sw-product-list.html.twig'],
        );
    });

    it('filter-computeds: adds the assetFilter computed to a TypeScript override whose imported template reads it', () => {
        expect(readSource(projectRoot, 'extension/sw-flow-list/index.ts')).toContain(
            "return Shopware.Filter.getByName('asset');",
        );
    });

    it('order-list-helpers: rewrites the removed helpers in script and template and marks the order.deliveries read', () => {
        const template = readSource(projectRoot, 'extension/sw-order-list/sw-order-list.html.twig');

        expect(readSource(projectRoot, 'extension/sw-order-list/index.js')).toContain(
            "return order.primaryOrderDelivery?.stateMachineState?.technicalName === 'shipped';",
        );
        expect(template).toContain('<template v-if="item.primaryOrderDelivery?.shippingOrderAddress">');
        expect(template).toContain('{{ item.primaryOrderDelivery?.shippingOrderAddress?.street }},');
        expect(template).not.toContain('getDeliveryAddress');
        expect(template).toContain('{# TODO Codemod(6.8, order-list-helpers): ');
    });

    it('snippet-keys: replaces removed snippet keys with their successors', () => {
        const template = readSource(projectRoot, 'component/acme-tax-display/acme-tax-display.html.twig');

        expect(template).toContain("$t('sw-settings-customer-group.detail.taxDisplay.label')");
        expect(template).toContain("$t('global.sw-condition-generic.cartTaxDisplay.taxDisplay.options.gross')");
    });

    it('asset-paths: points a removed .jpg image to its .webp version', () => {
        const template = readSource(
            projectRoot,
            'module/sw-cms/blocks/acme-teaser/preview/sw-cms-preview-acme-teaser.html.twig',
        );

        expect(template).toContain('/administration/administration/static/img/cms/preview_mountain_large.webp');
        expect(template).not.toContain('.jpg');
    });

    it('changes no file and reports no change on a second --write', async () => {
        commitAll(path.join(projectRoot, PLUGIN));

        const files = Object.keys(FIXTURES);
        const before = files.map((file) => readSource(projectRoot, file));
        const { extensions } = await upgradeExtensions({ projectRoot, administrationRoot, write: true });

        expect(files.map((file) => readSource(projectRoot, file))).toEqual(before);
        expect(SCRIPT_CODEMODS.map((id) => [id, report(extensions[0], id).changes])).toEqual(
            SCRIPT_CODEMODS.map((id) => [id, 0]),
        );
    });
});
