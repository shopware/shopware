/**
 * @sw-package framework
 */

const fs = require('fs');
const os = require('os');
const path = require('path');
const { RuleTester } = require('eslint');
const rule = require('./no-order-list-delivery-helpers');

const message = (name, path) => `${name}() of sw-order-list is removed in 6.8. Use order.${path} instead.`;
const DELIVERY_STATE = message('getDeliveryState', 'primaryOrderDelivery?.stateMachineState');
const DELIVERY_ADDRESS = message('getDeliveryAddress', 'primaryOrderDelivery?.shippingOrderAddress');
const TRANSACTION = message('transaction', 'primaryOrderTransaction');
const GET_DELIVERY = message('getDelivery', 'primaryOrderDelivery');
const TOOLTIP = 'deliveryTooltip() of sw-order-list is removed in 6.8. The list only loads the primary delivery; '
    + 'format order.primaryOrderDelivery?.shippingOrderAddress yourself.';
const FILTER_LOADING = 'filterLoading of sw-order-list is removed in 6.8 without replacement.';
const FILTER_SELECT_CRITERIA = 'filterSelectCriteria of sw-order-list is removed in 6.8 without replacement.';
const UPDATE_CRITERIA = "updateCriteria() of sw-order-list is removed in 6.8, so this.$super('updateCriteria') has nothing to call. "
    + "Call Shopware.Mixin.getByName('listing').methods.updateCriteria.call(this, criteria) instead.";
const SUPER_DELIVERY_STATE = "getDeliveryState of sw-order-list is removed in 6.8, so this.$super('getDeliveryState') has nothing to call. "
    + 'Use order.primaryOrderDelivery?.stateMachineState instead.';
const association = (name, replacement) => `sw-order-list no longer loads order.${name} in 6.8. Use ${replacement}, `
    + `or add the ${name} association to orderCriteria in this override.`;
const DELIVERIES = association('deliveries', 'order.primaryOrderDelivery');
const TRANSACTIONS = association('transactions', 'order.primaryOrderTransaction');

const scriptTodo = (text) => `// TODO Codemod(6.8, order-list-helpers): ${text}`;
const twigTodo = (text) => `{# TODO Codemod(6.8, order-list-helpers): ${text} #}`;
const lines = (...rows) => rows.join('\n');
const template = (...rows) => lines('<template>', ...rows, '</template>');
const override = (...body) => lines("Shopware.Component.override('sw-order-list', {", ...body, '});');

// Templates take their target and the members their script declares from the scripts next to them.
const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'sw-order-list-helpers-'));
const writeFile = (file, content) => {
    fs.mkdirSync(path.dirname(path.join(directory, file)), { recursive: true });
    fs.writeFileSync(path.join(directory, file), content);

    return path.join(directory, file);
};
const declaringTemplate = writeFile('declaring/acme-order-list.html.twig', '');
writeFile('declaring/acme-order-list.js', lines(
    "import template from './acme-order-list.html.twig';",
    "Shopware.Component.override('sw-order-list', {",
    '    template,',
    '    computed: {',
    '        orderCriteria() {',
    "            return this.$super('orderCriteria').addAssociation('deliveries');",
    '        },',
    '    },',
    '    methods: {',
    '        getDeliveryAddress(order) {',
    '            return order.deliveries[0]?.shippingOrderAddress;',
    '        },',
    '    },',
    '});',
));
const orderListTemplate = '/plugin/src/extension/sw-order-list/sw-order-list.html.twig';

const scriptTester = new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } });
const templateTester = new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022, sourceType: 'module' } });

describe('no-order-list-delivery-helpers', () => {
    afterAll(() => {
        fs.rmSync(directory, { recursive: true, force: true });
    });

    scriptTester.run('no-order-list-delivery-helpers (scripts)', rule, {
        valid: [
            {
                name: 'ignores overrides of other components',
                code: "Shopware.Component.override('sw-order-detail', { methods: { state(order) { return this.transaction(order).id && order.deliveries; } } });",
            },
            {
                name: 'keeps calls of a method the override declares itself',
                code: override(
                    '    methods: {',
                    '        getDeliveryAddress(order) {',
                    '            return order.primaryOrderDelivery?.shippingOrderAddress;',
                    '        },',
                    '        showAnalyse(order) {',
                    '            return !!this.getDeliveryAddress(order);',
                    '        },',
                    '    },',
                ),
            },
            {
                name: 'keeps associations the override loads itself, and updateCriteria() of the listing mixin',
                code: override(
                    '    computed: {',
                    '        orderCriteria() {',
                    "            return this.$super('orderCriteria').addAssociation('transactions.paymentMethod');",
                    '        },',
                    '    },',
                    '    methods: {',
                    '        paymentMethods(order, criteria) {',
                    '            this.updateCriteria(criteria);',
                    '            return order.transactions.map((transaction) => transaction.paymentMethod);',
                    '        },',
                    '    },',
                ),
            },
        ],
        invalid: [
            {
                name: 'replaces the accessors with their 6.8 body',
                code: override(
                    '    methods: {',
                    '        summary(order) {',
                    '            return [',
                    '                this.getDeliveryState(order).technicalName,',
                    '                this.getDeliveryAddress(this.order)?.city,',
                    '                this.getTransactionState(order)[\'technicalName\'],',
                    '                this.transaction(order).amount,',
                    '                this.getDelivery(order),',
                    '                this.getBillingAddress(order).street,',
                    '            ];',
                    '        },',
                    '    },',
                ),
                output: override(
                    '    methods: {',
                    '        summary(order) {',
                    '            return [',
                    '                order.primaryOrderDelivery?.stateMachineState?.technicalName,',
                    '                this.order.primaryOrderDelivery?.shippingOrderAddress?.city,',
                    '                order.primaryOrderTransaction?.stateMachineState?.[\'technicalName\'],',
                    '                order.primaryOrderTransaction.amount,',
                    '                order.primaryOrderDelivery,',
                    '                order.billingAddress.street,',
                    '            ];',
                    '        },',
                    '    },',
                ),
                errors: [
                    { message: DELIVERY_STATE },
                    { message: DELIVERY_ADDRESS },
                    { message: message('getTransactionState', 'primaryOrderTransaction?.stateMachineState') },
                    { message: TRANSACTION },
                    { message: GET_DELIVERY },
                    { message: message('getBillingAddress', 'billingAddress') },
                ],
            },
            {
                name: 'marks calls it cannot rewrite and members without replacement',
                code: override(
                    '    methods: {',
                    '        summary(order) {',
                    '            const transaction = this.transaction(order.items[0]);',
                    "            this.getDeliveryAddress(order).street = 'Main Street';",
                    '            const { getDelivery } = this;',
                    '            const count = order.deliveries.length;',
                    '            const tooltip = this.deliveryTooltip(order.deliveries);',
                    '            return this.filterLoading',
                    '                || this.filterSelectCriteria;',
                    '        },',
                    '        updateCriteria(criteria) {',
                    "            return this.$super('updateCriteria', criteria);",
                    '        },',
                    '        getDeliveryState(order) {',
                    "            return this.$super('getDeliveryState', order);",
                    '        },',
                    '    },',
                ),
                output: override(
                    '    methods: {',
                    '        summary(order) {',
                    `            ${scriptTodo(TRANSACTION)}`,
                    '            const transaction = this.transaction(order.items[0]);',
                    `            ${scriptTodo(DELIVERY_ADDRESS)}`,
                    "            this.getDeliveryAddress(order).street = 'Main Street';",
                    `            ${scriptTodo(GET_DELIVERY)}`,
                    '            const { getDelivery } = this;',
                    // One comment per association and method: the second deliveries read gets none
                    `            ${scriptTodo(DELIVERIES)}`,
                    '            const count = order.deliveries.length;',
                    `            ${scriptTodo(TOOLTIP)}`,
                    '            const tooltip = this.deliveryTooltip(order.deliveries);',
                    `            ${scriptTodo(FILTER_LOADING)}`,
                    '            return this.filterLoading',
                    `                ${scriptTodo(FILTER_SELECT_CRITERIA)}`,
                    '                || this.filterSelectCriteria;',
                    '        },',
                    '        updateCriteria(criteria) {',
                    `            ${scriptTodo(UPDATE_CRITERIA)}`,
                    "            return this.$super('updateCriteria', criteria);",
                    '        },',
                    '        getDeliveryState(order) {',
                    `            ${scriptTodo(SUPER_DELIVERY_STATE)}`,
                    "            return this.$super('getDeliveryState', order);",
                    '        },',
                    '    },',
                ),
                errors: [
                    { message: TRANSACTION },
                    { message: DELIVERY_ADDRESS },
                    { message: GET_DELIVERY },
                    { message: DELIVERIES },
                    { message: TOOLTIP },
                    { message: FILTER_LOADING },
                    { message: FILTER_SELECT_CRITERIA },
                    { message: UPDATE_CRITERIA },
                    { message: SUPER_DELIVERY_STATE },
                ],
            },
        ],
    });

    templateTester.run('no-order-list-delivery-helpers (templates)', rule, {
        valid: [
            {
                name: 'ignores templates of other components',
                filename: '/plugin/src/extension/sw-customer-list/sw-customer-list.html.twig',
                code: template('<span v-if="getDeliveryState(item)">{{ getDeliveryState(item).translated.name }}</span>'),
            },
            {
                name: 'keeps calls of methods and associations the importing script declares',
                filename: declaringTemplate,
                code: template('{{ getDeliveryAddress(item).street }} {{ item.deliveries.length }}'),
            },
        ],
        invalid: [
            {
                name: 'rewrites the accessors in attributes and interpolations of an .html.twig override',
                filename: orderListTemplate,
                code: template(
                    '{% block sw_order_list_grid_columns_delivery_state %}',
                    '    <sw-label v-if="getDeliveryState(item)" :variant="getVariantFromDeliveryState(item)">',
                    '        {{ getDeliveryState(item).translated.name }}',
                    '    </sw-label>',
                    '    {# {{ getDeliveryState(item) }} stays in comments #}',
                    '    <span>{{ getBillingAddress(item).city }}, {{ transaction(item)?.amount }}</span>',
                    '{% endblock %}',
                ),
                output: template(
                    '{% block sw_order_list_grid_columns_delivery_state %}',
                    '    <sw-label v-if="item.primaryOrderDelivery?.stateMachineState" :variant="getVariantFromDeliveryState(item)">',
                    '        {{ item.primaryOrderDelivery?.stateMachineState?.translated.name }}',
                    '    </sw-label>',
                    '    {# {{ getDeliveryState(item) }} stays in comments #}',
                    '    <span>{{ item.billingAddress.city }}, {{ item.primaryOrderTransaction?.amount }}</span>',
                    '{% endblock %}',
                ),
                errors: [
                    { message: DELIVERY_STATE },
                    { message: DELIVERY_STATE },
                    { message: message('getBillingAddress', 'billingAddress') },
                    { message: TRANSACTION },
                ],
            },
            {
                name: 'marks writes, removed members and associations in templates',
                filename: orderListTemplate,
                code: template(
                    '{% block sw_order_list_grid_columns_delivery %}',
                    '    <mt-text-field',
                    '        v-model="getDeliveryAddress(item).street"',
                    '    />',
                    '    <sw-loader v-if="filterLoading" />',
                    '    <span v-tooltip="{ message: deliveryTooltip(item.deliveries) }">',
                    '        {{ item.transactions.last().amount }}',
                    '    </span>',
                    '{% endblock %}',
                ),
                output: template(
                    '{% block sw_order_list_grid_columns_delivery %}',
                    `    ${twigTodo(DELIVERY_ADDRESS)}`,
                    '    <mt-text-field',
                    '        v-model="getDeliveryAddress(item).street"',
                    '    />',
                    `    ${twigTodo(FILTER_LOADING)}`,
                    '    <sw-loader v-if="filterLoading" />',
                    // RuleTester applies one fix pass: the second comment of a line follows in the next pass.
                    `    ${twigTodo(TOOLTIP)}`,
                    '    <span v-tooltip="{ message: deliveryTooltip(item.deliveries) }">',
                    `        ${twigTodo(TRANSACTIONS)}`,
                    '        {{ item.transactions.last().amount }}',
                    '    </span>',
                    '{% endblock %}',
                ),
                errors: [
                    { message: DELIVERY_ADDRESS },
                    { message: FILTER_LOADING },
                    { message: TOOLTIP },
                    { message: DELIVERIES },
                    { message: TRANSACTIONS },
                ],
            },
            {
                name: 'rewrites the template of a .vue override',
                filename: '/plugin/src/extension/sw-order-list/sw-order-list.vue',
                code: lines(
                    '<template>',
                    '    <span>{{ getDeliveryAddress(item).city }}</span>',
                    '</template>',
                    '<script>',
                    'export default {};',
                    '</script>',
                ),
                output: lines(
                    '<template>',
                    '    <span>{{ item.primaryOrderDelivery?.shippingOrderAddress?.city }}</span>',
                    '</template>',
                    '<script>',
                    'export default {};',
                    '</script>',
                ),
                errors: [{ message: DELIVERY_ADDRESS }],
            },
        ],
    });
});
