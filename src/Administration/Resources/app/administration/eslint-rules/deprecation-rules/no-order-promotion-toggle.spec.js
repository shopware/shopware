/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-order-promotion-toggle');
const data = require('./no-order-promotion-toggle.data');

const message = (name) => data.members.find((entry) => entry.name === name).message;
const CALL = data.calls[0].message;
const ROUTE = data.routes[0].message;
const scriptTodo = (text) => `// TODO Codemod(6.8, order-promotion-toggle): ${text}`;

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-order-promotion-toggle (script)', rule, {
    valid: [
        {
            name: 'ignores toggleAutomaticPromotions of sw-order-create-details',
            code: "Component.override('sw-order-create-details', { methods: { toggleAutomaticPromotions(visibility) { this.$super('toggleAutomaticPromotions', visibility); } } });",
        },
        { name: 'ignores the call of the component method', code: 'this.toggleAutomaticPromotions(false);' },
        { name: 'ignores applyAutomaticPromotions', code: "this.orderService.applyAutomaticPromotions(orderId, versionId);\nconst route = `_action/order/${orderId}/applyAutomaticPromotions`;" },
        { name: 'ignores createdComponent of other components', code: "Component.override('sw-order-detail', { methods: { createdComponent() {} } });" },
    ],
    invalid: [
        {
            name: 'marks the service call',
            code: 'await this.orderService.toggleAutomaticPromotions(orderId, versionId, true);',
            output: [scriptTodo(CALL), 'await this.orderService.toggleAutomaticPromotions(orderId, versionId, true);'].join('\n'),
            errors: [{ message: CALL }],
        },
        {
            name: 'marks the route in string and template literals',
            code: [
                "const a = '_action/order/abc/toggleAutomaticPromotions';",
                'const b = `/api/_action/order/${orderId}/toggleAutomaticPromotions`;',
            ].join('\n'),
            output: [
                scriptTodo(ROUTE),
                "const a = '_action/order/abc/toggleAutomaticPromotions';",
                scriptTodo(ROUTE),
                'const b = `/api/_action/order/${orderId}/toggleAutomaticPromotions`;',
            ].join('\n'),
            errors: [{ message: ROUTE }, { message: ROUTE }],
        },
        {
            name: 'marks the members inside overrides of sw-order-promotion-field',
            code: [
                "Component.override('sw-order-promotion-field', {",
                '    methods: {',
                '        async toggleAutomaticPromotions(state) {',
                "            await this.$super('toggleAutomaticPromotions', state);",
                '            this.emitLoadingChange(false);',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            output: [
                "Component.override('sw-order-promotion-field', {",
                '    methods: {',
                `        ${scriptTodo(message('toggleAutomaticPromotions'))}`,
                '        async toggleAutomaticPromotions(state) {',
                "            await this.$super('toggleAutomaticPromotions', state);",
                `            ${scriptTodo(message('emitLoadingChange'))}`,
                '            this.emitLoadingChange(false);',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            errors: [{ message: message('toggleAutomaticPromotions') }, { message: message('emitLoadingChange') }],
        },
    ],
});

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('no-order-promotion-toggle (template)', rule, {
    valid: [
        {
            name: 'ignores the toggle of sw-order-create-details',
            filename: '/plugin/src/sw-order-create-details/sw-order-create-details.html.twig',
            code: '<template>\n    <mt-switch @update:model-value="toggleAutomaticPromotions" />\n</template>',
        },
    ],
    invalid: [
        {
            name: 'marks the old switch binding in overrides of sw-order-promotion-field',
            filename: '/plugin/src/sw-order-promotion-field/sw-order-promotion-field.html.twig',
            code: '<template>\n    <mt-switch v-model="disabledAutoPromotions" />\n</template>',
            output: [
                '<template>',
                `    {# TODO Codemod(6.8, order-promotion-toggle): ${message('disabledAutoPromotions')} #}`,
                '    <mt-switch v-model="disabledAutoPromotions" />',
                '</template>',
            ].join('\n'),
            errors: [{ message: message('disabledAutoPromotions') }],
        },
    ],
});
