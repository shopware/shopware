/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const { createTodoRule } = require('./create-todo-rule');

const rule = createTodoRule({
    id: 'sample',
    components: { 'sw-old-card': 'sw-old-card is removed.' },
    members: [
        { component: 'sw-order-detail', name: 'isLoading', message: 'isLoading is removed. Use loading.order.' },
        { component: ['sw-a', 'sw-b'], name: 'onSave', kinds: ['methods'], message: 'onSave is removed.' },
        { component: 'sw-order-detail', name: 'swOrderDetailOnLoadingChange', unique: true, message: 'The provide is removed.' },
    ],
    calls: [{ service: 'orderService', method: 'toggleAutomaticPromotions', message: 'Use applyAutomaticPromotions().' }],
    globals: [{ path: 'Shopware.State', message: 'Shopware.State is removed.' }],
    literals: [{ value: '/_info/queue.json', message: 'The route is removed.' }],
    imports: [{ source: /^vuex$/, message: 'Vuex is removed.' }],
    events: [{ component: 'sw-media-grid', name: 'media-grid-selection-clear', message: 'The event is removed.' }],
    visitors: (context, api) => ({
        'CallExpression[callee.name="customCheck"]'(node) {
            api.todo(node, 'Custom finding.');
        },
    }),
});

const todo = (text) => `// TODO Codemod(6.8, sample): ${text}`;

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('create-todo-rule (script)', rule, {
    valid: [
        { name: 'ignores generic members outside the target', code: "Component.override('sw-other', { computed: { isLoading() { return this.isLoading; } } });" },
        { name: 'ignores kinds that do not match', code: "Component.override('sw-a', { computed: { onSave() {} } });" },
        { name: 'ignores other services', code: 'this.cartService.toggleAutomaticPromotions();' },
        { name: 'ignores Shopware.Store', code: "Shopware.Store.get('swOrderDetail').isLoading;" },
        {
            name: 'ignores a unique name that another core component declares itself',
            code: "Component.override('sw-settings-index', { methods: { swOrderDetailOnLoadingChange() {} } });",
        },
        { name: 'ignores component names from the object prototype', code: "Component.override('constructor', {});" },
    ],
    invalid: [
        {
            name: 'marks declared members and this accesses inside the target',
            code: [
                "Component.override('sw-order-detail', {",
                '    computed: {',
                '        isLoading() {',
                '            return this.isLoading;',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            output: [
                "Component.override('sw-order-detail', {",
                '    computed: {',
                `        ${todo('isLoading is removed. Use loading.order.')}`,
                '        isLoading() {',
                `            ${todo('isLoading is removed. Use loading.order.')}`,
                '            return this.isLoading;',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            errors: [{ message: 'isLoading is removed. Use loading.order.' }, { message: 'isLoading is removed. Use loading.order.' }],
        },
        {
            name: 'marks unique members anywhere and component calls',
            code: "this.swOrderDetailOnLoadingChange(true);\nComponent.extend('acme-card', 'sw-old-card', {});",
            output: [
                todo('The provide is removed.'),
                'this.swOrderDetailOnLoadingChange(true);',
                todo('sw-old-card is removed.'),
                "Component.extend('acme-card', 'sw-old-card', {});",
            ].join('\n'),
            errors: [{ message: 'The provide is removed.' }, { message: 'sw-old-card is removed.' }],
        },
        {
            name: 'marks service calls, globals, literals, imports, $emit and custom visitors',
            code: [
                "import Vuex from 'vuex';",
                'this.orderService.toggleAutomaticPromotions(id);',
                'const { State } = Shopware;',
                "State.get('swFlowState');",
                "const url = '/_info/queue.json';",
                "Component.override('sw-media-grid', { methods: { a() { this.$emit('media-grid-selection-clear'); } } });",
                'customCheck();',
            ].join('\n'),
            output: [
                todo('Vuex is removed.'),
                "import Vuex from 'vuex';",
                todo('Use applyAutomaticPromotions().'),
                'this.orderService.toggleAutomaticPromotions(id);',
                'const { State } = Shopware;',
                todo('Shopware.State is removed.'),
                "State.get('swFlowState');",
                todo('The route is removed.'),
                "const url = '/_info/queue.json';",
                todo('The event is removed.'),
                "Component.override('sw-media-grid', { methods: { a() { this.$emit('media-grid-selection-clear'); } } });",
                todo('Custom finding.'),
                'customCheck();',
            ].join('\n'),
            errors: [
                { message: 'Vuex is removed.' },
                { message: 'Use applyAutomaticPromotions().' },
                { message: 'Shopware.State is removed.' },
                { message: 'The route is removed.' },
                { message: 'The event is removed.' },
                { message: 'Custom finding.' },
            ],
        },
    ],
});

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('create-todo-rule (template)', rule, {
    valid: [
        {
            name: 'ignores generic members in templates of other components',
            filename: '/plugin/src/acme/acme.html.twig',
            code: '<template>{% block a %}<div>{{ isLoading }}</div>{% endblock %}</template>',
        },
    ],
    invalid: [
        {
            name: 'marks tags and listeners, and puts attribute findings above the element',
            filename: '/plugin/src/sw-order-detail/sw-order-detail.html.twig',
            code: [
                '<template>',
                '    <sw-old-card />',
                '    <sw-media-grid @media-grid-selection-clear="onClear" />',
                '    <div',
                '        :class="{ busy: isLoading }"',
                '    />',
                '</template>',
            ].join('\n'),
            output: [
                '<template>',
                '    {# TODO Codemod(6.8, sample): sw-old-card is removed. #}',
                '    <sw-old-card />',
                '    {# TODO Codemod(6.8, sample): The event is removed. #}',
                '    <sw-media-grid @media-grid-selection-clear="onClear" />',
                '    {# TODO Codemod(6.8, sample): isLoading is removed. Use loading.order. #}',
                '    <div',
                '        :class="{ busy: isLoading }"',
                '    />',
                '</template>',
            ].join('\n'),
            errors: [
                { message: 'sw-old-card is removed.' },
                { message: 'The event is removed.' },
                { message: 'isLoading is removed. Use loading.order.' },
            ],
        },
        {
            name: 'reads {{ … }} interpolations of .html.twig templates, which the parser keeps as text',
            filename: '/plugin/src/sw-order-detail/sw-order-detail.html.twig',
            code: [
                '<template>',
                '{% block sw_order_detail_content %}',
                '    <span v-for="isLoading in items">{{ isLoading }}</span>',
                '    <span>{{ isLoading ? 1 : 0 }}</span>',
                '{% endblock %}',
                '</template>',
            ].join('\n'),
            output: [
                '<template>',
                '{% block sw_order_detail_content %}',
                '    <span v-for="isLoading in items">{{ isLoading }}</span>',
                '    {# TODO Codemod(6.8, sample): isLoading is removed. Use loading.order. #}',
                '    <span>{{ isLoading ? 1 : 0 }}</span>',
                '{% endblock %}',
                '</template>',
            ].join('\n'),
            errors: [{ message: 'isLoading is removed. Use loading.order.' }],
        },
    ],
});
