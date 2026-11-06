/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-order-list-delivery-helpers');

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

const override = (body) => `Shopware.Component.override('sw-order-list', { methods: { f(order) { ${body} } } });`;
const replace = { messageId: 'replace' };

scriptTester.run('no-order-list-delivery-helpers (script)', rule, {
    valid: [
        {
            name: 'ignores helpers of other components',
            code: 'Shopware.Component.override(\'sw-order-detail\', { methods: { f(order) { return this.getDelivery(order); } } });',
        },
        {
            name: 'ignores the associations',
            code: override('return order.primaryOrderDelivery;'),
        },
    ],
    invalid: [
        {
            name: 'rewrites every helper',
            code: override([
                'return [',
                'this.getDelivery(order),',
                'this.transaction(order),',
                'this.getDeliveryAddress(order),',
                'this.getDeliveryState(order),',
                'this.getTransactionState(order),',
                'this.getBillingAddress(order),',
                '];',
            ].join(' ')),
            output: override([
                'return [',
                'order.primaryOrderDelivery,',
                'order.primaryOrderTransaction,',
                'order.primaryOrderDelivery?.shippingOrderAddress,',
                'order.primaryOrderDelivery?.stateMachineState,',
                'order.primaryOrderTransaction?.stateMachineState,',
                'order.billingAddress,',
                '];',
            ].join(' ')),
            errors: [replace, replace, replace, replace, replace, replace],
        },
        {
            name: 'wraps an argument that is not a plain reference',
            code: override('return this.getDeliveryState(this.orders[0] || order).technicalName;'),
            output: override('return (this.orders[0] || order).primaryOrderDelivery?.stateMachineState.technicalName;'),
            errors: [replace],
        },
        {
            name: 'rewrites an override file named after the component',
            filename: '/plugin/src/extension/sw-order-list/index.js',
            code: 'export default { methods: { f(order) { return this.getBillingAddress(order); } } };',
            output: 'export default { methods: { f(order) { return order.billingAddress; } } };',
            errors: [replace],
        },
        {
            name: 'comments the tooltip helper, strict null checks and bare references',
            code: [
                'Shopware.Component.override(\'sw-order-list\', {',
                '    methods: {',
                '        f(order) {',
                '            const tooltip = this.deliveryTooltip(order.deliveries);',
                '            const missing = this.getDelivery(order) === null;',
                '            return [this.orders.map(this.transaction), tooltip, missing];',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            output: [
                'Shopware.Component.override(\'sw-order-list\', {',
                '    methods: {',
                '        f(order) {',
                '            // TODO Codemod: "sw-order-list" no longer has "deliveryTooltip()". Build the tooltip from order.primaryOrderDelivery.shippingOrderAddress yourself.',
                '            const tooltip = this.deliveryTooltip(order.deliveries);',
                '            // TODO Codemod: "sw-order-list" no longer has "getDelivery()". Use order.primaryOrderDelivery instead.',
                '            const missing = this.getDelivery(order) === null;',
                '            // TODO Codemod: "sw-order-list" no longer has "transaction()". Use order.primaryOrderTransaction instead.',
                '            return [this.orders.map(this.transaction), tooltip, missing];',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            errors: [{ messageId: 'todo' }, { messageId: 'todo' }, { messageId: 'todo' }],
        },
        {
            name: 'comments calls in an override that defines the helper itself',
            code: [
                'Shopware.Component.override(\'sw-order-list\', {',
                '    methods: {',
                '        getDelivery(order) {',
                '            return this.$super(\'getDelivery\', order);',
                '        },',
                '        f(order) {',
                '            return this.getDelivery(order);',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            output: [
                'Shopware.Component.override(\'sw-order-list\', {',
                '    methods: {',
                '        getDelivery(order) {',
                '            // TODO Codemod: "sw-order-list" no longer has "getDelivery()". Use order.primaryOrderDelivery instead.',
                '            return this.$super(\'getDelivery\', order);',
                '        },',
                '        f(order) {',
                '            // TODO Codemod: "sw-order-list" no longer has "getDelivery()". Use order.primaryOrderDelivery instead.',
                '            return this.getDelivery(order);',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            errors: [{ messageId: 'todo' }, { messageId: 'todo' }],
        },
    ],
});

templateTester.run('no-order-list-delivery-helpers (template)', rule, {
    valid: [
        {
            name: 'ignores templates of other components',
            filename: '/plugin/src/extension/sw-order-detail/sw-order-detail.html.twig',
            code: '<template><span>{{ getDeliveryState(item).name }}</span></template>',
        },
        {
            name: 'ignores member names',
            filename: '/plugin/src/extension/sw-order-list/sw-order-list.html.twig',
            code: '<template><span>{{ item.transaction }}</span></template>',
        },
    ],
    invalid: [
        {
            name: 'rewrites helper calls in a template override',
            filename: '/plugin/src/extension/sw-order-list/sw-order-list.html.twig',
            code: [
                '<template>',
                '{% block sw_order_list_grid_columns_delivery_state %}',
                '    <sw-label v-if="getDeliveryState(item)">{{ getDeliveryState(item).translated.name }}</sw-label>',
                '{% endblock %}',
                '</template>',
            ].join('\n'),
            output: [
                '<template>',
                '{% block sw_order_list_grid_columns_delivery_state %}',
                '    <sw-label v-if="item.primaryOrderDelivery?.stateMachineState">{{ item.primaryOrderDelivery?.stateMachineState.translated.name }}</sw-label>',
                '{% endblock %}',
                '</template>',
            ].join('\n'),
            errors: [replace, replace],
        },
        {
            name: 'comments the tooltip helper in a template',
            filename: '/plugin/src/extension/sw-order-list/sw-order-list.html.twig',
            code: [
                '<template>',
                '    <span v-tooltip="deliveryTooltip(item.deliveries)" />',
                '</template>',
            ].join('\n'),
            output: [
                '<template>',
                '    <!-- TODO Codemod: "sw-order-list" no longer has "deliveryTooltip()". Build the tooltip from order.primaryOrderDelivery.shippingOrderAddress yourself. -->',
                '    <span v-tooltip="deliveryTooltip(item.deliveries)" />',
                '</template>',
            ].join('\n'),
            errors: [{ messageId: 'todo' }],
        },
    ],
});
