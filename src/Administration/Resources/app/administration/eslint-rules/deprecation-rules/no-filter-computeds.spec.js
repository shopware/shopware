/**
 * @sw-package framework
 */

const fs = require('fs');
const os = require('os');
const path = require('path');
const { RuleTester } = require('eslint');
const { dateFilter, assetFilter } = require('./no-filter-computeds');

const scriptTester = new RuleTester({
    languageOptions: { ecmaVersion: 2022, sourceType: 'module' },
});

const templateTester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

const replace = (component) => ({ messageId: 'replace', data: { component } });

scriptTester.run('no-filter-computeds (dateFilter, script)', dateFilter, {
    valid: [
        {
            name: 'ignores components that keep the computed',
            code: 'Shopware.Component.override("sw-customer-base-info", { methods: { f() { return this.dateFilter(1); } } });',
        },
        {
            name: 'ignores own components',
            code: 'Shopware.Component.register("acme-list", { methods: { f() { return this.dateFilter(1); } } });',
        },
        {
            name: 'ignores an override that defines the computed itself',
            code: [
                'Shopware.Component.override("sw-order-list", {',
                '    computed: { dateFilter() { return Shopware.Filter.getByName("date"); } },',
                '    methods: { f(value) { return this.dateFilter(value); } },',
                '});',
            ].join('\n'),
        },
        {
            name: 'ignores the other filter computed',
            code: 'Shopware.Component.override("sw-order-list", { methods: { f() { return this.assetFilter("a"); } } });',
        },
    ],
    invalid: [
        {
            name: 'rewrites a call in an override',
            code: 'Shopware.Component.override("sw-order-list", { methods: { f(value) { return this.dateFilter(value, { hour: "2-digit" }); } } });',
            output: 'Shopware.Component.override("sw-order-list", { methods: { f(value) { return Shopware.Filter.getByName(\'date\')(value, { hour: "2-digit" }); } } });',
            errors: [replace('sw-order-list')],
        },
        {
            name: 'rewrites the parent computed of an override and a component extending a listed one',
            code: [
                'Shopware.Component.override("sw-review-list", { computed: { dateFilter() { return this.$super("dateFilter"); } } });',
                'Shopware.Component.extend("acme-card", "sw-extension-card-base", { methods: { f(v) { return this.dateFilter(v); } } });',
            ].join('\n'),
            output: [
                'Shopware.Component.override("sw-review-list", { computed: { dateFilter() { return Shopware.Filter.getByName(\'date\'); } } });',
                'Shopware.Component.extend("acme-card", "sw-extension-card-base", { methods: { f(v) { return Shopware.Filter.getByName(\'date\')(v); } } });',
            ].join('\n'),
            errors: [replace('sw-review-list'), replace('sw-extension-card-base')],
        },
        {
            name: 'rewrites the default export of an override file named after the component',
            filename: '/plugin/src/extension/sw-cms-list/index.js',
            code: 'export default { methods: { f(v) { return this.dateFilter(v); } } };',
            output: 'export default { methods: { f(v) { return Shopware.Filter.getByName(\'date\')(v); } } };',
            errors: [replace('sw-cms-list')],
        },
        {
            name: 'comments a destructured computed',
            filename: '/plugin/src/extension/sw-cms-list/index.js',
            code: 'export default { methods: { f(v) {\n    const { dateFilter } = this;\n    return dateFilter(v);\n} } };',
            output: [
                'export default { methods: { f(v) {',
                '    // TODO Codemod: "sw-cms-list" no longer has the "dateFilter" computed. Use Shopware.Filter.getByName(\'date\') instead.',
                '    const { dateFilter } = this;',
                '    return dateFilter(v);',
                '} } };',
            ].join('\n'),
            errors: [{ messageId: 'todo' }],
        },
    ],
});

scriptTester.run('no-filter-computeds (assetFilter, script)', assetFilter, {
    valid: [
        {
            name: 'ignores components that keep the computed',
            code: 'Shopware.Component.override("sw-order-list", { methods: { f() { return this.assetFilter("a"); } } });',
        },
    ],
    invalid: [
        {
            name: 'rewrites a call in an override',
            code: 'Shopware.Component.override("sw-flow-list", { methods: { f() { return this.assetFilter("a.svg"); } } });',
            output: 'Shopware.Component.override("sw-flow-list", { methods: { f() { return Shopware.Filter.getByName(\'asset\')("a.svg"); } } });',
            errors: [replace('sw-flow-list')],
        },
    ],
});

const overrideFolder = fs.mkdtempSync(path.join(os.tmpdir(), 'sw-codemod-filter-'));
const ownComputedFolder = path.join(overrideFolder, 'sw-product-detail-cross-selling');

fs.mkdirSync(ownComputedFolder);
fs.writeFileSync(
    path.join(ownComputedFolder, 'index.js'),
    'export default { computed: { assetFilter() { return Shopware.Filter.getByName("asset"); } } };\n',
);

templateTester.run('no-filter-computeds (dateFilter, template)', dateFilter, {
    valid: [
        {
            name: 'ignores member names in Twig interpolations',
            filename: '/plugin/src/extension/sw-review-list/sw-review-list.html.twig',
            code: '<template><span>{{ item.dateFilter }}</span></template>',
        },
    ],
    invalid: [
        {
            name: 'comments a Twig interpolation that calls the removed computed',
            filename: '/plugin/src/extension/sw-review-list/sw-review-list.html.twig',
            code: [
                '<template>',
                '    <span>{{ dateFilter(item.createdAt) }}</span>',
                '</template>',
            ].join('\n'),
            output: [
                '<template>',
                '    <!-- TODO Codemod: "sw-review-list" no longer has the "dateFilter" computed. Add a "dateFilter" computed that returns Shopware.Filter.getByName(\'date\') to your override. -->',
                '    <span>{{ dateFilter(item.createdAt) }}</span>',
                '</template>',
            ].join('\n'),
            errors: [{ messageId: 'todo' }],
        },
    ],
});

describe('no-filter-computeds (assetFilter, template)', () => {
    afterAll(() => {
        fs.rmSync(overrideFolder, { recursive: true, force: true });
    });

    templateTester.run('no-filter-computeds (assetFilter, template)', assetFilter, {
        valid: [
            {
                name: 'ignores templates of other components',
                filename: '/plugin/src/extension/acme-list/acme-list.html.twig',
                code: '<template><img :src="assetFilter(\'a.svg\')" /></template>',
            },
            {
                name: 'ignores a template whose override defines the computed again',
                filename: path.join(ownComputedFolder, 'sw-product-detail-cross-selling.html.twig'),
                code: '<template><img :src="assetFilter(\'a.svg\')" /></template>',
            },
        ],
        invalid: [
            {
                name: 'comments a template that calls the removed computed',
                filename: '/plugin/src/extension/sw-product-detail-cross-selling/sw-product-detail-cross-selling.html.twig',
                code: [
                    '<template>',
                    '{% block sw_product_detail_cross_selling_empty_state %}',
                    '    <img :src="assetFilter(\'a.svg\')" />',
                    '{% endblock %}',
                    '</template>',
                ].join('\n'),
                output: [
                    '<template>',
                    '{% block sw_product_detail_cross_selling_empty_state %}',
                    '    <!-- TODO Codemod: "sw-product-detail-cross-selling" no longer has the "assetFilter" computed. Add a "assetFilter" computed that returns Shopware.Filter.getByName(\'asset\') to your override. -->',
                    '    <img :src="assetFilter(\'a.svg\')" />',
                    '{% endblock %}',
                    '</template>',
                ].join('\n'),
                errors: [{ messageId: 'todo' }],
            },
        ],
    });
});
