const path = require('path');
const {
    atLevel,
    createTempFiles,
    lintTwig,
    reportOf,
    rule,
    scriptTester,
    todoScript,
    todoTemplate,
    typeScriptTester,
    vueTester,
} = require('./tester');

const MESSAGE = 'Use the swOrderDetail store.';
const STORE_HINT = reportOf(MESSAGE);

function override(options) {
    return `Shopware.Component.override('sw-order-detail', ${options});`;
}

scriptTester.run('createApiUsageRule() component-member (scripts)', rule, {
    valid: [
        {
            name: 'the member in an override of another component',
            code: "Shopware.Component.override('sw-order-list', { computed: { a() { return this.isLoading; } } });",
        },
        {
            name: 'the member in the registration of the component itself',
            code: "Shopware.Component.register('sw-order-detail', { data() { return { isLoading: false }; } });",
        },
        {
            name: 'the member in a Pinia store file of an override folder',
            filename: '/ext/src/extension/sw-order-detail/store.js',
            code: "export default { id: 'swagOrder', state: () => ({ isLoading: false }), actions: { load() { this.isLoading = true; } } };",
        },
        {
            name: 'the member in another file of an override folder outside of an override call',
            filename: '/ext/src/extension/sw-order-detail/helper.js',
            code: 'export default { computed: { isLoading() { return this.isLoading; } } };',
        },
        {
            name: 'the member in an override in the Administration itself',
            filename: path.resolve(__dirname, '../../../../src/module/sw-order/extension/sw-order-detail/index.js'),
            code: override('{ methods: { load() { return this.isLoading; } } }'),
        },
        {
            name: 'a member of another object',
            code: override('{ methods: { load() { return this.order.isLoading; } } }'),
        },
    ],
    invalid: [
        {
            name: 'a member read through this in an override',
            code: override('{ methods: { load() { return this.isLoading; } } }'),
            errors: [{ message: STORE_HINT }],
        },
        {
            name: 'a member read in the index of a lazy override, which registers in another file',
            filename: '/ext/src/extension/sw-order-detail/index.js',
            code: 'export default { methods: { load() { return this.isLoading; } } };',
            errors: [{ message: STORE_HINT }],
        },
        {
            name: 'a member written through this in an extension',
            code: "Shopware.Component.extend('swag-order-detail', 'sw-order-detail', { created() { this.isLoading = true; } });",
            errors: [{ message: STORE_HINT }],
        },
        {
            name: 'a member destructured from this',
            code: override('{ methods: { load() { const { isLoading } = this; } } }'),
            errors: [{ message: STORE_HINT }],
        },
        {
            name: 'a computed, data, prop, watch key and inject that the override defines',
            code: override(`{
    inject: ['isLoading'],
    props: { isLoading: Boolean },
    data() { return { isLoading: false }; },
    computed: { isLoading() { return false; } },
    watch: { 'isLoading.value'() {} },
    methods: { onSaveAndReload() {} },
}`),
            errors: [
                { message: STORE_HINT, line: 2 },
                { message: STORE_HINT, line: 3 },
                { message: STORE_HINT, line: 4 },
                { message: STORE_HINT, line: 5 },
                { message: STORE_HINT, line: 6 },
                { message: STORE_HINT, line: 7 },
            ],
        },
        {
            name: 'only the definition when the override uses the member it defines itself',
            code: override('{ data() { return { onSaveAndReload: null }; }, methods: { load() { return this.onSaveAndReload; } } }'),
            errors: [{ message: STORE_HINT, column: 68 }],
        },
        {
            name: 'a $super call of the member, also when the override defines it',
            code: override("{ methods: { onSaveAndReload() { return this.$super('onSaveAndReload'); } } }"),
            errors: [{ message: STORE_HINT }, { message: STORE_HINT }],
        },
        {
            name: 'a review comment at the partial level',
            settings: atLevel('partial'),
            code: override('{\n    methods: {\n        load() {\n            return this.isLoading;\n        },\n    },\n}'),
            output: override(
                `{\n    methods: {\n        load() {\n            ${todoScript(MESSAGE)}\n            return this.isLoading;\n        },\n    },\n}`,
            ),
            errors: [{ message: STORE_HINT }],
        },
    ],
});

typeScriptTester.run('createApiUsageRule() component-member (TypeScript)', rule, {
    valid: [],
    invalid: [
        {
            name: 'a member defined in options wrapped in as',
            filename: 'index.ts',
            code: override('{ computed: { isLoading(): boolean { return false; } } } as unknown as ComponentOptions'),
            errors: [{ message: STORE_HINT }],
        },
        {
            name: 'a member defined in a data function whose object is wrapped in satisfies',
            filename: 'index.ts',
            code: override('{ data: () => ({ isLoading: false } satisfies object) }'),
            errors: [{ message: STORE_HINT }],
        },
    ],
});

vueTester.run('createApiUsageRule() component-member (.vue)', rule, {
    valid: [
        {
            name: 'a template of another component',
            filename: 'swag-order.vue',
            code: '<template><div v-if="isLoading" /></template>',
        },
        {
            name: 'a binding of the script setup block of the override',
            filename: 'sw-order-detail.override.vue',
            code: '<template><div v-if="isLoading" @click="onSaveAndReload" /></template>\n<script setup>\nimport { onSaveAndReload } from "./actions";\nconst { isLoading } = useSwPreviousState();\nswDefineOverride({});\n</script>',
        },
        {
            name: 'a prop of the script setup block of the override',
            filename: 'sw-order-detail.override.vue',
            code: '<template><div v-if="isLoading" /></template>\n<script setup lang="ts">\nconst props = defineProps<{ isLoading: boolean }>();\n</script>',
        },
        {
            name: 'a v-for alias',
            filename: 'sw-order-detail.override.vue',
            code: '<template><div v-for="isLoading in list">{{ isLoading }}</div></template>',
        },
        {
            name: 'a slot prop',
            filename: 'sw-order-detail.override.vue',
            code: '<template><sw-x><template #default="{ isLoading }">{{ isLoading }}</template></sw-x></template>',
        },
        {
            name: 'a parameter of an arrow function in the template',
            filename: 'sw-order-detail.override.vue',
            code: '<template><div @click="(isLoading) => isLoading" /></template>',
        },
        {
            name: 'a member name and an object key',
            filename: 'sw-order-detail.override.vue',
            code: '<template><div :class="{ isLoading: order.isLoading }" /></template>',
        },
    ],
    invalid: [
        {
            name: 'a member in the template of a native override',
            filename: 'sw-order-detail.override.vue',
            code: '<template><div v-if="isLoading">{{ onSaveAndReload }}</div></template>',
            errors: [{ message: STORE_HINT }, { message: STORE_HINT }],
        },
        {
            name: 'only the definition when the options of the override define the member the template reads',
            filename: 'sw-order-detail.override.vue',
            code: '<template><div v-if="isLoading" /></template>\n<script>\nexport default { computed: { isLoading() { return false; } } };\n</script>',
            errors: [{ message: STORE_HINT, line: 3 }],
        },
        {
            name: 'a shorthand object property',
            filename: 'sw-order-detail.override.vue',
            code: '<template><div :data="{ isLoading }" /></template>',
            errors: [{ message: STORE_HINT }],
        },
        {
            name: 'a member in the script of a native override',
            filename: 'sw-order-detail.override.vue',
            code: '<script>\nexport default { methods: { load() { return this.isLoading; } } };\n</script>',
            errors: [{ message: STORE_HINT }],
        },
        {
            name: 'a review comment above the element at the partial level',
            filename: 'sw-order-detail.override.vue',
            settings: atLevel('partial'),
            code: '<template>\n    <div>\n        <span v-if="isLoading" />\n    </div>\n</template>',
            output: `<template>\n    <div>\n        ${todoTemplate(MESSAGE)}\n        <span v-if="isLoading" />\n    </div>\n</template>`,
            errors: [{ message: STORE_HINT }],
        },
        {
            name: 'a review comment above the line of a mustache in the template root',
            filename: 'sw-order-detail.override.vue',
            settings: atLevel('partial'),
            code: '<template>\n    {{ isLoading }}\n</template>',
            output: `<template>\n    ${todoTemplate(MESSAGE)}\n    {{ isLoading }}\n</template>`,
            errors: [{ message: STORE_HINT }],
        },
    ],
});

describe('createApiUsageRule() component-member (.html.twig)', () => {
    let files;

    beforeAll(() => {
        files = createTempFiles({
            'src/extension/sw-order-detail/index.js':
                "import template from './swag-order-detail.html.twig';\nShopware.Component.override('sw-order-detail', { template });\n",
            'src/extension/sw-order-detail/swag-order-detail.html.twig': '',
            'src/extension/sw-order-detail-own/index.js':
                "Shopware.Component.override('sw-order-detail', { template, computed: { isLoading() { return false; } } });\n",
            'src/extension/sw-order-detail-own/sw-order-detail.html.twig': '',
            'src/extension/sw-order-detail/sw-order-detail.html.twig': '',
            'src/sw-order-detail/sw-order-detail.html.twig': '',
            'src/component/swag-order/index.js': "Shopware.Component.register('swag-order', { template });\n",
            'src/extension/lazy/sw-order-detail/index.js': 'export default { template };\n',
            'src/extension/lazy/sw-order-detail/swag-order-detail.html.twig': '',
            'src/component/swag-order/sw-order-detail.html.twig': '',
        });
    });

    afterAll(() => {
        files.remove();
    });

    it('reports a member in a template that an override imports under another name', () => {
        const messages = lintTwig(
            '{% block a %}\n    <div v-if="isLoading" />\n{% endblock %}\n',
            files.path('src/extension/sw-order-detail/swag-order-detail.html.twig'),
            'safe',
        );

        expect(messages).toEqual([expect.objectContaining({ line: 2, message: expect.stringMatching(STORE_HINT) })]);
    });

    it('reports a member in a template without sibling index by its folder and name', () => {
        const messages = lintTwig(
            '{% block a %}\n    {{ isLoading }}\n{% endblock %}\n',
            files.path('src/sw-order-detail/sw-order-detail.html.twig'),
            'safe',
        );

        expect(messages).toEqual([expect.objectContaining({ line: 2, message: expect.stringMatching(STORE_HINT) })]);
    });

    it('ignores a member that the override next to the template defines itself', () => {
        const messages = lintTwig(
            '{% block a %}\n    {{ isLoading }}\n{% endblock %}\n',
            files.path('src/extension/sw-order-detail-own/sw-order-detail.html.twig'),
            'safe',
        );

        expect(messages).toEqual([]);
    });

    it('ignores the template of a component of its own, whatever its name', () => {
        const messages = lintTwig(
            '{% block a %}\n    {{ isLoading }}\n{% endblock %}\n',
            files.path('src/component/swag-order/sw-order-detail.html.twig'),
            'safe',
        );

        expect(messages).toEqual([]);
    });

    it('reports a member in the template of a lazy override, whose index only exports options', () => {
        const messages = lintTwig(
            '{% block a %}\n    {{ isLoading }}\n{% endblock %}\n',
            files.path('src/extension/lazy/sw-order-detail/swag-order-detail.html.twig'),
            'safe',
        );

        expect(messages).toEqual([expect.objectContaining({ line: 2, message: expect.stringMatching(STORE_HINT) })]);
    });

    it('ignores the template of the Administration itself', () => {
        const coreTemplate = path.resolve(__dirname, '../../../../src/module/sw-order/page/sw-order-detail/sw-order-detail.html.twig');

        expect(lintTwig('{% block a %}\n    {{ isLoading }}\n{% endblock %}\n', coreTemplate, 'safe')).toEqual([]);
    });

    it('ignores v-for aliases and slot props in mustaches', () => {
        const messages = lintTwig(
            '<div v-for="isLoading in list">{{ isLoading }}</div>\n<sw-x><template #default="{ onSaveAndReload }">{{ onSaveAndReload() }}</template></sw-x>\n',
            files.path('src/extension/sw-order-detail/swag-order-detail.html.twig'),
            'safe',
        );

        expect(messages).toEqual([]);
    });
});
