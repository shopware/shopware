/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-custom-field-set-loaders');
const data = require('./no-custom-field-set-loaders.data');

const message = (component, name) => data.members.find((entry) => entry.component === component && entry.name === name).message;
const scriptTodo = (text) => `// TODO Codemod(6.8, custom-field-set-loaders): ${text}`;

describe('no-custom-field-set-loaders data', () => {
    it('covers the eleven components', () => {
        expect(new Set(data.members.map((entry) => entry.component)).size).toBe(11);
    });

    it('names the entity of the component', () => {
        expect(message('sw-manufacturer-detail', 'customFieldSetCriteria')).toBe(
            "sw-manufacturer-detail.customFieldSetCriteria is removed. Use Shopware.Service('customFieldDataProviderService').getCustomFieldSets('product_manufacturer').",
        );
        expect(message('sw-customer-detail-addresses', 'customFieldSetRepository')).toContain("getCustomFieldSets('customer_address')");
    });
});

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-custom-field-set-loaders', rule, {
    valid: [
        { name: 'ignores components that keep the computeds', code: "Component.override('sw-settings-custom-field-set-detail', { computed: { customFieldSetRepository() { return this.customFieldSetRepository; } } });" },
        { name: 'ignores generic names without a target', code: 'this.customFieldSetRepository.search(this.customFieldSetCriteria);' },
        { name: 'ignores the new customFieldRepository of sw-product-stream-detail', code: "Component.override('sw-product-stream-detail', { computed: { customFieldRepository() {} } });" },
    ],
    invalid: [
        {
            name: 'marks the computeds with the entity name of the component',
            code: [
                "Component.override('sw-manufacturer-detail', {",
                '    computed: {',
                '        customFieldSetCriteria() {',
                "            return this.$super('customFieldSetCriteria');",
                '        },',
                '    },',
                '});',
            ].join('\n'),
            output: [
                "Component.override('sw-manufacturer-detail', {",
                '    computed: {',
                `        ${scriptTodo(message('sw-manufacturer-detail', 'customFieldSetCriteria'))}`,
                '        customFieldSetCriteria() {',
                "            return this.$super('customFieldSetCriteria');",
                '        },',
                '    },',
                '});',
            ].join('\n'),
            errors: [{ message: message('sw-manufacturer-detail', 'customFieldSetCriteria') }],
        },
        {
            name: 'marks the misnamed customFieldRepository of sw-sales-channel-detail',
            code: "Component.override('sw-sales-channel-detail', { methods: { a() { return this.customFieldRepository; } } });",
            output: [scriptTodo(message('sw-sales-channel-detail', 'customFieldRepository')), "Component.override('sw-sales-channel-detail', { methods: { a() { return this.customFieldRepository; } } });"].join('\n'),
            errors: [{ message: message('sw-sales-channel-detail', 'customFieldRepository') }],
        },
        {
            name: 'marks customFieldSetLandingPageCriteria without a known target',
            code: 'const criteria = this.customFieldSetLandingPageCriteria;',
            output: [scriptTodo(message('sw-category-detail', 'customFieldSetLandingPageCriteria')), 'const criteria = this.customFieldSetLandingPageCriteria;'].join('\n'),
            errors: [{ message: message('sw-category-detail', 'customFieldSetLandingPageCriteria') }],
        },
    ],
});
