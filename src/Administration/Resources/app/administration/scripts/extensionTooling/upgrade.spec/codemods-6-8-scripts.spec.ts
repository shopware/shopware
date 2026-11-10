/**
 * @sw-package framework
 *
 * Acceptance spec of the 6.8 script rewrite codemods: one fixture plugin with a realistic extension file per case,
 * one real `--write` run, and the whole resulting file per case.
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

describe('scripts/extensionTooling/upgrade 6.8 script codemods', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeAll(async () => {
        ({ projectRoot, administrationRoot } = createUpgradeProject('sw-tooling-upgrade-scripts-'));

        Object.entries(FIXTURES).forEach(([file, content]) => writeFile(path.join(projectRoot, SOURCE, file), content));
        commitAll(path.join(projectRoot, PLUGIN));

        await upgradeExtensions({ projectRoot, administrationRoot, write: true });
    });

    afterAll(() => {
        cleanupTempProject(projectRoot);
    });

    it('module-snippets: removes the snippets property and its imports when the server loads the JSON files', () => {
        expect(readSource(projectRoot, 'module/acme-example/index.js')).toBe(
            text(
                "Shopware.Module.register('acme-example', {",
                "    type: 'plugin',",
                "    name: 'acme-example',",
                "    title: 'acme-example.general.mainMenuItemGeneral',",
                '    routes: {',
                "        list: { component: 'acme-example-list', path: 'list' },",
                '    },',
                '});',
            ),
        );
    });

    it('module-snippets: marks the snippets property when the server does not load the imported files', () => {
        expect(readSource(projectRoot, 'module/acme-legacy/index.js')).toBe(
            text(
                "import de from './snippet/de';",
                '',
                "Shopware.Module.register('acme-legacy', {",
                "    type: 'plugin',",
                "    name: 'acme-legacy',",
                '    // TODO Codemod(6.8, module-snippets): Shopware.Module.register() no longer accepts snippets in 6.8 ' +
                    'and throws with the v6.8.0.0 feature flag. Move the snippets to JSON files below the administration ' +
                    'src folder, named after the language (de.json) or the locale (de-DE.json), then remove this property.',
                '    snippets: {',
                "        'de-DE': de,",
                '    },',
                '});',
            ),
        );
    });

    it('filter-computeds: adds the dateFilter computed to an override whose imported template reads it', () => {
        expect(readSource(projectRoot, 'extension/sw-product-list/index.js')).toBe(
            text(
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
                '',
                '    computed: {',
                '        dateFilter() {',
                "            return Shopware.Filter.getByName('date');",
                '        },',
                '    },',
                '});',
            ),
        );
        expect(readSource(projectRoot, 'extension/sw-product-list/sw-product-list.html.twig')).toBe(
            FIXTURES['extension/sw-product-list/sw-product-list.html.twig'],
        );
    });

    it('filter-computeds: adds the assetFilter computed to a TypeScript override whose imported template reads it', () => {
        expect(readSource(projectRoot, 'extension/sw-flow-list/index.ts')).toBe(
            text(
                "import template from './sw-flow-list.html.twig';",
                '',
                "Shopware.Component.override('sw-flow-list', {",
                '    template,',
                '',
                '    computed: {',
                '        assetFilter() {',
                "            return Shopware.Filter.getByName('asset');",
                '        },',
                '    },',
                '});',
            ),
        );
    });

    it('order-list-helpers: rewrites the removed helpers in script and template and marks the order.deliveries read', () => {
        expect(readSource(projectRoot, 'extension/sw-order-list/index.js')).toBe(
            text(
                "import template from './sw-order-list.html.twig';",
                '',
                "Shopware.Component.override('sw-order-list', {",
                '    template,',
                '',
                '    methods: {',
                '        isShipped(order) {',
                "            return order.primaryOrderDelivery?.stateMachineState?.technicalName === 'shipped';",
                '        },',
                '    },',
                '});',
            ),
        );
        expect(readSource(projectRoot, 'extension/sw-order-list/sw-order-list.html.twig')).toBe(
            text(
                '{% block sw_order_list_grid_columns_delivery_address %}',
                '<template #column-primaryOrderDelivery.shippingOrderAddress.street="{ item }">',
                '    <template v-if="item.primaryOrderDelivery?.shippingOrderAddress">',
                '        {{ item.primaryOrderDelivery?.shippingOrderAddress?.street }},',
                '        {{ item.primaryOrderDelivery?.shippingOrderAddress?.city }}',
                '        {# TODO Codemod(6.8, order-list-helpers): sw-order-list no longer loads order.deliveries in 6.8. ' +
                    'Use order.primaryOrderDelivery, or add the deliveries association to orderCriteria in this override. #}',
                '        <span v-if="item.deliveries.length > 1">',
                "            {{ $t('acme-upgrade.orderList.moreDeliveries') }}",
                '        </span>',
                '    </template>',
                '</template>',
                '{% endblock %}',
            ),
        );
    });

    it('snippet-keys: replaces removed snippet keys with their successors', () => {
        expect(readSource(projectRoot, 'component/acme-tax-display/acme-tax-display.html.twig')).toBe(
            text(
                '{% block acme_tax_display %}',
                '<div',
                '    class="acme-tax-display"',
                '    :title="$t(\'sw-settings-customer-group.detail.taxDisplay.label\')"',
                '>',
                "    {{ $t('global.sw-condition-generic.cartTaxDisplay.taxDisplay.options.gross') }}",
                '</div>',
                '{% endblock %}',
            ),
        );
    });

    it('asset-paths: points a removed .jpg image to its .webp version', () => {
        expect(
            readSource(projectRoot, 'module/sw-cms/blocks/acme-teaser/preview/sw-cms-preview-acme-teaser.html.twig'),
        ).toBe(
            text(
                '{% block sw_cms_block_acme_teaser_preview %}',
                '<div class="sw-cms-preview-acme-teaser">',
                '    <img :src="assetFilter(\'/administration/administration/static/img/cms/preview_mountain_large.webp\')" alt="">',
                '</div>',
                '{% endblock %}',
            ),
        );
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
