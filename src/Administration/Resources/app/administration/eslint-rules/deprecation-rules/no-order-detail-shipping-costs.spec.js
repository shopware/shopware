/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-order-detail-shipping-costs');
const data = require('./no-order-detail-shipping-costs.data');

const message = (name) => data.members.find((entry) => entry.name === name).message;
const scriptTodo = (text) => `// TODO Codemod(6.8, order-shipping-costs): ${text}`;

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-order-detail-shipping-costs', rule, {
    valid: [
        {
            name: 'ignores the shipping cost members of sw-order-detail-general, which stay',
            code: "Component.override('sw-order-detail-general', { methods: { onShippingChargeEdited() { this.shippingCosts = 0; } } });",
        },
        { name: 'ignores currency in other components', code: "Component.override('sw-order-list', { computed: { currency() { return this.currency; } } });" },
        { name: 'ignores the names outside overrides', code: 'this.onShippingChargeEdited(5);' },
    ],
    invalid: [
        {
            name: 'marks the members inside overrides of sw-order-detail-details',
            code: [
                "Component.override('sw-order-detail-details', {",
                '    methods: {',
                '        onShippingChargeEdited(amount) {',
                "            this.$super('onShippingChargeEdited', amount);",
                '        },',
                '    },',
                '});',
            ].join('\n'),
            output: [
                "Component.override('sw-order-detail-details', {",
                '    methods: {',
                `        ${scriptTodo(message('onShippingChargeEdited'))}`,
                '        onShippingChargeEdited(amount) {',
                "            this.$super('onShippingChargeEdited', amount);",
                '        },',
                '    },',
                '});',
            ].join('\n'),
            errors: [{ message: message('onShippingChargeEdited') }],
        },
    ],
});
