const {
    atLevel,
    createTempFiles,
    fixTwig,
    lintTwig,
    reportOf,
    rule,
    scriptTester,
    todoScript,
    todoTemplate,
    vueTester,
} = require('./tester');

const DATE_FILTER = reportOf('dateFilter is removed.');
const DATE_FILTER_TEMPLATE = reportOf('Add a dateFilter computed.');
const ASSET_FILTER = reportOf('assetFilter is removed.');
const DELIVERY_STATE = reportOf('getDeliveryState() is removed.');
const STRICT_NULL_TEXT = 'getDeliveryState() is removed and its replacement is undefined instead of null.';
const STRICT_NULL = reportOf(STRICT_NULL_TEXT);

function override(body) {
    return `Shopware.Component.override('sw-order-list', { ${body} });`;
}

scriptTester.run('no-deprecated-api-usage replace-component-member', rule, {
    valid: [
        {
            name: 'the member in an override of another component',
            code: "Shopware.Component.override('sw-product-list', { methods: { a() { return this.dateFilter; } } });",
        },
        {
            name: 'the member in the registration of the component',
            code: "Shopware.Component.register('sw-order-list', { methods: { a() { return this.dateFilter; } } });",
        },
        {
            name: 'the member the override defines itself',
            code: override("computed: { dateFilter() { return Shopware.Filter.getByName('date'); } }, methods: { a() { return this.dateFilter; } }"),
        },
        {
            name: 'the member outside of an override',
            code: 'export default { methods: { a() { return this.dateFilter; } } };',
        },
    ],
    invalid: [
        {
            name: 'replaces a read through this',
            code: override('methods: { format(value) { return this.dateFilter(value); } }'),
            output: override("methods: { format(value) { return Shopware.Filter.getByName('date')(value); } }"),
            errors: [{ message: DATE_FILTER }],
        },
        {
            name: 'replaces a computed member with a static name',
            code: override("methods: { format(value) { return this['dateFilter'](value); } }"),
            output: override("methods: { format(value) { return Shopware.Filter.getByName('date')(value); } }"),
            errors: [{ message: DATE_FILTER }],
        },
        {
            name: 'replaces a $super call, also when the override defines the member',
            code: override("computed: { dateFilter() { return this.$super('dateFilter'); } }"),
            output: override("computed: { dateFilter() { return Shopware.Filter.getByName('date'); } }"),
            errors: [{ message: DATE_FILTER }],
        },
        {
            name: 'reports a write without a fix',
            code: override('created() { this.dateFilter = null; }'),
            output: null,
            errors: [{ message: DATE_FILTER }],
        },
        {
            name: 'reports a destructuring without a fix',
            code: override('created() { const { dateFilter } = this; }'),
            output: null,
            errors: [{ message: DATE_FILTER }],
        },
        {
            name: 'leaves the fix of an unsafe-auto step out at the safe level',
            code: override('methods: { a() { return this.assetFilter; } }'),
            output: null,
            errors: [{ message: ASSET_FILTER }],
        },
        {
            name: 'applies the fix of an unsafe-auto step with a review comment at the review level',
            settings: atLevel('review'),
            code: `${override('methods: {\n    a() {\n        return this.assetFilter;\n    },\n}')}`,
            output: `${override(`methods: {\n    a() {\n        ${todoScript('assetFilter is removed.')}\n        return Shopware.Filter.getByName('asset');\n    },\n}`)}`,
            errors: [{ message: ASSET_FILTER }],
        },
        {
            name: 'reports without a fix when the rule runs with disableFix',
            options: ['disableFix'],
            code: override('methods: { a() { return this.dateFilter; } }'),
            output: null,
            errors: [{ message: DATE_FILTER }],
        },
    ],
});

scriptTester.run('no-deprecated-api-usage replace-component-method-call', rule, {
    valid: [
        {
            name: 'the method in an override of another component',
            code: "Shopware.Component.override('sw-product-list', { methods: { a(order) { return this.getDeliveryState(order); } } });",
        },
        {
            name: 'the method the override defines itself',
            code: override('methods: { getDeliveryState(order) { return null; }, a(order) { return this.getDeliveryState(order); } }'),
        },
        {
            name: 'a method of another object',
            code: override('methods: { a(order) { return this.helper.getDeliveryState(order); } }'),
        },
    ],
    invalid: [
        {
            name: 'replaces the call with the first argument',
            code: override('methods: { a(order) { return this.getDeliveryState(order).technicalName; } }'),
            output: override('methods: { a(order) { return order.primaryOrderDelivery?.stateMachineState.technicalName; } }'),
            errors: [{ message: DELIVERY_STATE }],
        },
        {
            name: 'keeps a member argument as it is',
            code: override('methods: { a() { return this.getDeliveryState(this.order); } }'),
            output: override('methods: { a() { return this.order.primaryOrderDelivery?.stateMachineState; } }'),
            errors: [{ message: DELIVERY_STATE }],
        },
        {
            name: 'puts parentheses around an argument that needs them',
            code: override('methods: { a() { return this.getDeliveryState(this.order || fallback); } }'),
            output: override('methods: { a() { return (this.order || fallback).primaryOrderDelivery?.stateMachineState; } }'),
            errors: [{ message: DELIVERY_STATE }],
        },
        {
            name: 'replaces a $super call with the argument after the name',
            code: override("methods: { getDeliveryState(order) { return this.$super('getDeliveryState', order); } }"),
            output: override('methods: { getDeliveryState(order) { return order.primaryOrderDelivery?.stateMachineState; } }'),
            errors: [{ message: DELIVERY_STATE }],
        },
        {
            name: 'reports a strict comparison with null without a fix',
            code: override('methods: { a(order) { return this.getDeliveryState(order) === null; } }'),
            output: null,
            errors: [{ message: STRICT_NULL }],
        },
        {
            name: 'reports a strict comparison with undefined without a fix',
            code: override('methods: { a(order) { return undefined !== this.getDeliveryState(order); } }'),
            output: null,
            errors: [{ message: STRICT_NULL }],
        },
        {
            name: 'writes the strict null message as review comment at the partial level',
            settings: atLevel('partial'),
            code: override('methods: {\n    a(order) {\n        return this.getDeliveryState(order) === null;\n    },\n}'),
            output: override(`methods: {\n    a(order) {\n        ${todoScript(STRICT_NULL_TEXT)}\n        return this.getDeliveryState(order) === null;\n    },\n}`),
            errors: [{ message: STRICT_NULL }],
        },
        {
            name: 'replaces a loose comparison with null',
            code: override('methods: { a(order) { return this.getDeliveryState(order) == null; } }'),
            output: override('methods: { a(order) { return order.primaryOrderDelivery?.stateMachineState == null; } }'),
            errors: [{ message: DELIVERY_STATE }],
        },
        {
            name: 'reports a call with other arguments without a fix',
            code: override('methods: { a(order) { return this.getDeliveryState(order, true); } }'),
            output: null,
            errors: [{ message: DELIVERY_STATE }],
        },
        {
            name: 'reports a spread argument without a fix',
            code: override('methods: { a(args) { return this.getDeliveryState(...args); } }'),
            output: null,
            errors: [{ message: DELIVERY_STATE }],
        },
        {
            name: 'reports a reference that is not called without a fix',
            code: override('methods: { a(orders) { return orders.map(this.getDeliveryState); } }'),
            output: null,
            errors: [{ message: DELIVERY_STATE }],
        },
    ],
});

vueTester.run('no-deprecated-api-usage replace steps (.vue)', rule, {
    valid: [
        {
            name: 'a template member the script setup block of the override defines',
            filename: 'sw-order-list.override.vue',
            code: "<template><span>{{ dateFilter(order.createdAt) }}</span></template>\n<script setup>\nconst dateFilter = Shopware.Filter.getByName('date');\nswDefineOverride({});\n</script>",
        },
        {
            name: 'a template member the options of the override define',
            filename: 'sw-order-list.override.vue',
            code: "<template><span>{{ dateFilter(order.createdAt) }}</span></template>\n<script>\nexport default { computed: { dateFilter() { return Shopware.Filter.getByName('date'); } } };\n</script>",
        },
        {
            name: 'a template method the script setup block of the override defines',
            filename: 'sw-order-list.override.vue',
            code: '<template><span>{{ getDeliveryState(order) }}</span></template>\n<script setup>\nfunction getDeliveryState(order) { return order.state; }\n</script>',
        },
        {
            name: 'a template of another component',
            filename: 'swag-order-list.vue',
            code: '<template><span>{{ dateFilter(order.createdAt) }}</span></template>',
        },
    ],
    invalid: [
        {
            name: 'reports a template member with the template message and no fix',
            filename: 'sw-order-list.override.vue',
            code: '<template><span>{{ dateFilter(order.createdAt) }}</span></template>',
            output: null,
            errors: [{ message: DATE_FILTER_TEMPLATE }],
        },
        {
            name: 'writes the template message as review comment at the partial level',
            filename: 'sw-order-list.override.vue',
            settings: atLevel('partial'),
            code: '<template>\n    <span :title="dateFilter(order.createdAt)" />\n</template>',
            output: `<template>\n    ${todoTemplate('Add a dateFilter computed.')}\n    <span :title="dateFilter(order.createdAt)" />\n</template>`,
            errors: [{ message: DATE_FILTER_TEMPLATE }],
        },
        {
            name: 'replaces a method call in a template',
            filename: 'sw-order-list.override.vue',
            code: '<template><span v-for="item in orders">{{ getDeliveryState(item).name }}</span></template>',
            output: '<template><span v-for="item in orders">{{ item.primaryOrderDelivery?.stateMachineState.name }}</span></template>',
            errors: [{ message: DELIVERY_STATE }],
        },
        {
            name: 'reports a strict null comparison in a template without a fix',
            filename: 'sw-order-list.override.vue',
            code: '<template><span v-if="getDeliveryState(item) !== null" /></template>',
            output: null,
            errors: [{ message: STRICT_NULL }],
        },
        {
            name: 'replaces a member in the script of the native override',
            filename: 'sw-order-list.override.vue',
            code: '<script>\nexport default { methods: { a(v) { return this.dateFilter(v); } } };\n</script>',
            output: "<script>\nexport default { methods: { a(v) { return Shopware.Filter.getByName('date')(v); } } };\n</script>",
            errors: [{ message: DATE_FILTER }],
        },
    ],
});

describe('no-deprecated-api-usage replace steps (.html.twig)', () => {
    let files;

    beforeAll(() => {
        files = createTempFiles({
            'src/extension/sw-order-list/index.js':
                "import template from './sw-order-list.html.twig';\nShopware.Component.override('sw-order-list', { template });\n",
            'src/extension/sw-order-list/sw-order-list.html.twig': '',
            'src/extension/sw-order-list-own/index.js':
                "import template from './sw-order-list.html.twig';\nShopware.Component.override('sw-order-list', {\n    template,\n    computed: {\n        dateFilter() {\n            return Shopware.Filter.getByName('date');\n        },\n    },\n});\n",
            'src/extension/sw-order-list-own/sw-order-list.html.twig': '',
        });
    });

    afterAll(() => {
        files.remove();
    });

    it('replaces a method call in a mustache and in a binding', () => {
        const code = [
            '{% block sw_order_list_grid_columns_delivery_state %}',
            '    <sw-label :variant="getDeliveryState(item).name">',
            '        {{ getDeliveryState(item).translated.name }}',
            '    </sw-label>',
            '{% endblock %}',
            '',
        ].join('\n');

        expect(fixTwig(code, files.path('src/extension/sw-order-list/sw-order-list.html.twig'), 'safe')).toBe(
            [
                '{% block sw_order_list_grid_columns_delivery_state %}',
                '    <sw-label :variant="item.primaryOrderDelivery?.stateMachineState.name">',
                '        {{ item.primaryOrderDelivery?.stateMachineState.translated.name }}',
                '    </sw-label>',
                '{% endblock %}',
                '',
            ].join('\n'),
        );
    });

    it('comments a strict null comparison in a mustache right inside a block', () => {
        const code = '{% block a %}\n    {{ getDeliveryState(item) === null ? "-" : "x" }}\n{% endblock %}\n';
        const filename = files.path('src/extension/sw-order-list/sw-order-list.html.twig');

        expect(fixTwig(code, filename)).toBe(
            `{% block a %}\n    ${todoTemplate(STRICT_NULL_TEXT)}\n    {{ getDeliveryState(item) === null ? "-" : "x" }}\n{% endblock %}\n`,
        );
    });

    it('reports a template member with the template message', () => {
        const messages = lintTwig(
            '{% block a %}\n    {{ dateFilter(item.createdAt) }}\n{% endblock %}\n',
            files.path('src/extension/sw-order-list/sw-order-list.html.twig'),
            'safe',
        );

        expect(messages).toEqual([expect.objectContaining({ line: 2, message: expect.stringMatching(DATE_FILTER_TEMPLATE) })]);
    });

    it('ignores a template member that the override next to the template defines', () => {
        const messages = lintTwig(
            '{% block a %}\n    {{ dateFilter(item.createdAt) }}\n{% endblock %}\n',
            files.path('src/extension/sw-order-list-own/sw-order-list.html.twig'),
            'safe',
        );

        expect(messages).toEqual([]);
    });
});
