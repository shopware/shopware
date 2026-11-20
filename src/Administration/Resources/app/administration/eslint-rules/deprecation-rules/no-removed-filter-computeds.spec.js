/**
 * @sw-package framework
 */

const fs = require('fs');
const os = require('os');
const path = require('path');
const { RuleTester } = require('eslint');
const rule = require('./no-removed-filter-computeds');

const DATE_FILTER_TODO = "sw-order-list no longer provides dateFilter. Add `dateFilter() { return Shopware.Filter.getByName('date'); }` to the computed of your override.";
const ASSET_FILTER_TODO = "sw-flow-list no longer provides assetFilter. Add `assetFilter() { return Shopware.Filter.getByName('asset'); }` to the computed of your override.";

const lines = (...rows) => rows.join('\n');
const template = (...rows) => lines('<template>', ...rows, '</template>');

// Overrides read the templates they import from disk.
const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'sw-filter-computeds-'));
const writeFile = (file, content) => {
    fs.mkdirSync(path.dirname(path.join(directory, file)), { recursive: true });
    fs.writeFileSync(path.join(directory, file), content);

    return path.join(directory, file);
};

writeFile('order-list/sw-order-list.html.twig', '{% block sw_order_list_grid_columns %}{{ dateFilter(item.orderDateTime) }}{% endblock %}');
const orderListOverride = path.join(directory, 'order-list/index.js');
const importedTemplate = writeFile('imported/sw-order-list.html.twig', '');
writeFile('imported/sw-order-list.js', "import template from './sw-order-list.html.twig';\nShopware.Component.override('sw-order-list', { template });");
const standaloneTemplate = writeFile('sw-order-list/sw-order-list.html.twig', '');
const flowListTemplate = writeFile('flow/acme-flow-list.html.twig', '');
writeFile('flow/index.js', "Shopware.Component.override('sw-flow-list', {});");

const scriptTester = new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } });
const typeScriptTester = new RuleTester({ languageOptions: { parser: require('@typescript-eslint/parser') } });
const templateTester = new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022, sourceType: 'module' } });

describe('no-removed-filter-computeds', () => {
    afterAll(() => {
        fs.rmSync(directory, { recursive: true, force: true });
    });

    scriptTester.run('no-removed-filter-computeds (scripts)', rule, {
        valid: [
            {
                name: 'keeps an override that defines the computed itself',
                filename: orderListOverride,
                code: lines(
                    "import template from './sw-order-list.html.twig';",
                    '',
                    "Shopware.Component.override('sw-order-list', {",
                    '    template,',
                    '    computed: {',
                    '        dateFilter() {',
                    "            return Shopware.Filter.getByName('date');",
                    '        },',
                    '    },',
                    '});',
                ),
            },
            {
                name: 'ignores overrides that do not read the filters',
                code: "Shopware.Component.override('sw-order-list', { methods: { orderNumber(order) { return order.orderNumber; } } });",
            },
            {
                name: 'ignores components that keep the computed',
                code: "Shopware.Component.override('sw-customer-base-info', { methods: { format(value) { return this.dateFilter(value); } } });",
            },
            {
                name: 'ignores the assetFilter computeds that stay until 6.9',
                code: "Shopware.Component.override('sw-extension-my-extensions-listing', { methods: { image() { return this.assetFilter('x.svg'); } } });",
            },
        ],
        invalid: [
            {
                name: 'adds the dateFilter computed when the imported template reads it',
                filename: orderListOverride,
                code: lines(
                    "import template from './sw-order-list.html.twig';",
                    '',
                    'Shopware.Component.override(\'sw-order-list\', {',
                    '    template,',
                    '});',
                ),
                output: lines(
                    "import template from './sw-order-list.html.twig';",
                    '',
                    'Shopware.Component.override(\'sw-order-list\', {',
                    '    template,',
                    '',
                    '    computed: {',
                    '        dateFilter() {',
                    "            return Shopware.Filter.getByName('date');",
                    '        },',
                    '    },',
                    '});',
                ),
                errors: [{ message: 'sw-order-list no longer provides dateFilter in 6.8. Define it in the computed of this override.' }],
            },
            {
                name: 'adds both filters to an existing computed when the script reads them',
                code: lines(
                    "const { Component } = Shopware;",
                    "Component.override('sw-promotion-v2-individual-codes-behavior', {",
                    '    computed: {',
                    '        label() {',
                    "            return this.dateFilter(this.date) + this.assetFilter('x.svg');",
                    '        },',
                    '    },',
                    '});',
                ),
                output: lines(
                    "const { Component } = Shopware;",
                    "Component.override('sw-promotion-v2-individual-codes-behavior', {",
                    '    computed: {',
                    '        label() {',
                    "            return this.dateFilter(this.date) + this.assetFilter('x.svg');",
                    '        },',
                    '',
                    '        dateFilter() {',
                    "            return Shopware.Filter.getByName('date');",
                    '        },',
                    '',
                    '        assetFilter() {',
                    "            return Shopware.Filter.getByName('asset');",
                    '        },',
                    '    },',
                    '});',
                ),
                errors: [{
                    message: 'sw-promotion-v2-individual-codes-behavior no longer provides dateFilter and assetFilter in 6.8. Define them in the computed of this override.',
                }],
            },
            {
                name: 'covers an extension with an inline template and destructured reads',
                code: lines(
                    "Shopware.Component.extend('acme-product-list', 'sw-product-list', {",
                    "    template: '<span>{{ dateFilter(product.createdAt) }}</span>',",
                    '    methods: {',
                    '        format() {',
                    '            const { dateFilter } = this;',
                    '            return dateFilter;',
                    '        },',
                    '    }',
                    '});',
                ),
                output: lines(
                    "Shopware.Component.extend('acme-product-list', 'sw-product-list', {",
                    "    template: '<span>{{ dateFilter(product.createdAt) }}</span>',",
                    '    methods: {',
                    '        format() {',
                    '            const { dateFilter } = this;',
                    '            return dateFilter;',
                    '        },',
                    '    },',
                    '',
                    '    computed: {',
                    '        dateFilter() {',
                    "            return Shopware.Filter.getByName('date');",
                    '        },',
                    '    }',
                    '});',
                ),
                errors: [{ message: 'sw-product-list no longer provides dateFilter in 6.8. Define it in the computed of this override.' }],
            },
            {
                name: 'marks an override whose computed is no object literal',
                code: lines(
                    "Shopware.Component.override('sw-order-list', {",
                    '    computed: sharedComputed,',
                    '    methods: { format(order) { return this.dateFilter(order.orderDateTime); } },',
                    '});',
                ),
                output: lines(
                    "Shopware.Component.override('sw-order-list', {",
                    `    // TODO Codemod(6.8, filter-computeds): ${DATE_FILTER_TODO}`,
                    '    computed: sharedComputed,',
                    '    methods: { format(order) { return this.dateFilter(order.orderDateTime); } },',
                    '});',
                ),
                errors: [{ message: DATE_FILTER_TODO }],
            },
        ],
    });

    typeScriptTester.run('no-removed-filter-computeds (TypeScript)', rule, {
        valid: [],
        invalid: [
            {
                name: 'adds the assetFilter computed to a TypeScript override without trailing comma',
                filename: 'index.ts',
                code: lines(
                    "Shopware.Component.override('sw-flow-list', {",
                    '    methods: {',
                    '        icon(): string {',
                    "            return this.assetFilter('x.svg') as string;",
                    '        }',
                    '    }',
                    '});',
                ),
                output: lines(
                    "Shopware.Component.override('sw-flow-list', {",
                    '    methods: {',
                    '        icon(): string {',
                    "            return this.assetFilter('x.svg') as string;",
                    '        }',
                    '    },',
                    '',
                    '    computed: {',
                    '        assetFilter() {',
                    "            return Shopware.Filter.getByName('asset');",
                    '        },',
                    '    }',
                    '});',
                ),
                errors: [{ message: 'sw-flow-list no longer provides assetFilter in 6.8. Define it in the computed of this override.' }],
            },
        ],
    });

    templateTester.run('no-removed-filter-computeds (templates)', rule, {
        valid: [
            {
                name: 'leaves a template that a script imports to that script',
                filename: importedTemplate,
                code: template('{{ dateFilter(item.orderDateTime) }}'),
            },
            {
                name: 'ignores templates of other components',
                filename: '/plugin/src/acme/acme-list.html.twig',
                code: template('<span :title="dateFilter(item.createdAt)">{{ dateFilter(item.createdAt) }}</span>'),
            },
        ],
        invalid: [
            {
                name: 'marks the first read of each filter in an .html.twig override without script',
                filename: standaloneTemplate,
                code: template(
                    '{% block sw_order_list_grid_columns %}',
                    '    <span>{{ dateFilter(item.orderDateTime, { month: \'2-digit\' }) }}</span>',
                    '    <span>{{ dateFilter(item.updatedAt) }}</span>',
                    '{% endblock %}',
                ),
                output: template(
                    '{% block sw_order_list_grid_columns %}',
                    `    {# TODO Codemod(6.8, filter-computeds): ${DATE_FILTER_TODO} #}`,
                    '    <span>{{ dateFilter(item.orderDateTime, { month: \'2-digit\' }) }}</span>',
                    '    <span>{{ dateFilter(item.updatedAt) }}</span>',
                    '{% endblock %}',
                ),
                errors: [{ message: DATE_FILTER_TODO }],
            },
            {
                name: 'marks the element of an attribute that reads the filter',
                filename: flowListTemplate,
                code: template(
                    '{% block sw_flow_list_grid %}',
                    '    <img',
                    '        alt=""',
                    '        :src="assetFilter(\'/administration/administration/static/img/empty-states/settings-empty-state.svg\')"',
                    '    >',
                    '{% endblock %}',
                ),
                output: template(
                    '{% block sw_flow_list_grid %}',
                    `    {# TODO Codemod(6.8, filter-computeds): ${ASSET_FILTER_TODO} #}`,
                    '    <img',
                    '        alt=""',
                    '        :src="assetFilter(\'/administration/administration/static/img/empty-states/settings-empty-state.svg\')"',
                    '    >',
                    '{% endblock %}',
                ),
                errors: [{ message: ASSET_FILTER_TODO }],
            },
            {
                name: 'adds the computed for the template of a .vue file',
                filename: '/plugin/src/extension/sw-order-list/sw-order-list.vue',
                code: lines(
                    '<template>',
                    '    <span>{{ dateFilter(item.orderDateTime) }}</span>',
                    '</template>',
                    '',
                    '<script>',
                    'export default {',
                    '    methods: {},',
                    '};',
                    '</script>',
                ),
                output: lines(
                    '<template>',
                    '    <span>{{ dateFilter(item.orderDateTime) }}</span>',
                    '</template>',
                    '',
                    '<script>',
                    'export default {',
                    '    methods: {},',
                    '',
                    '    computed: {',
                    '        dateFilter() {',
                    "            return Shopware.Filter.getByName('date');",
                    '        },',
                    '    },',
                    '};',
                    '</script>',
                ),
                errors: [{ message: 'sw-order-list no longer provides dateFilter in 6.8. Define it in the computed of this override.' }],
            },
        ],
    });
});
