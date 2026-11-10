const {
    atLevel,
    createTempFiles,
    lintTwig,
    reportOf,
    rule,
    scriptTester,
    todoScript,
    typeScriptTester,
    vueTester,
} = require('./tester');

const STATE = reportOf('Shopware.State is removed.');
const STORE = reportOf('this.$store is removed.');
const MAP_VUEX_STATE = reportOf('mapVuexState is removed.');
const TEMPLATE_DATA = reportOf('templateData is removed.');
const SEND_MAIL = reportOf('Pass the template data.');
const PRODUCT_STATES_GETTER = reportOf('The productStates getter is removed.');
const IS_SCOPED = reportOf('isScoped is removed.');
const PRODUCT_STATES = reportOf('product.states is removed.');

scriptTester.run('no-deprecated-api-usage member-usage (scripts)', rule, {
    valid: [
        { name: 'another property of the object', code: 'Shopware.Store.get("x");' },
        { name: 'the property on another object', code: 'Foo.State.get("x");' },
        { name: 'a receiver that only ends like the object path', code: 'other.notMailTemplateType.templateData;' },
        {
            name: 'a call that passes the argument',
            code: "this.mailService.sendMailTemplate('a', 'b', templateData);",
        },
        {
            name: 'a call whose arguments are spread',
            code: "this.mailService.sendMailTemplate('a', ...rest);",
        },
        { name: 'a reference to a method that is not called', code: 'const send = this.mailService.sendMailTemplate;' },
        {
            name: 'a destructured method of a step that checks arguments',
            code: 'const { sendMailTemplate } = this.mailService;',
        },
        { name: 'another service', code: "Shopware.Service('otherService').sendMailTemplate('a');" },
        { name: 'another store', code: "Shopware.Store.get('swOrder').productStates;" },
        {
            name: 'a component-scoped step outside of an override',
            code: 'export default { computed: { a() { return this.isScoped; } } };',
        },
        {
            name: 'a component-scoped step in the registration of the component itself',
            code: "Shopware.Component.register('sw-order-list', { computed: { a() { return this.isScoped; } } });",
        },
        { name: 'a template-only receiver path in a script', code: 'product.states.includes(1);' },
    ],
    invalid: [
        {
            name: 'the property of a dotted object',
            code: "const store = Shopware.State.get('x');",
            errors: [{ message: STATE }],
        },
        {
            name: 'the property behind window',
            code: "window.Shopware.State.get('x');",
            errors: [{ message: STATE }],
        },
        {
            name: 'a computed member with a static name',
            code: "Shopware['State'].get('x');",
            errors: [{ message: STATE }],
        },
        {
            name: 'a property destructured from the object',
            code: 'const { State } = Shopware;',
            errors: [{ message: STATE }],
        },
        {
            name: 'this as receiver',
            code: 'export default { created() { this.$store.commit("x"); } };',
            errors: [{ message: STORE }],
        },
        {
            name: 'a property destructured from this',
            code: 'export default { created() { const { $store } = this; } };',
            errors: [{ message: STORE }],
        },
        {
            name: 'any receiver for a step without one',
            code: 'Shopware.Component.getComponentHelper().mapVuexState("x");',
            errors: [{ message: MAP_VUEX_STATE }],
        },
        {
            name: 'a destructuring from any value for a step without receiver',
            code: 'const { mapVuexState, mapState } = Shopware.Component.getComponentHelper();',
            errors: [{ message: MAP_VUEX_STATE }],
        },
        {
            name: 'a receiver path that ends with the name after *.',
            code: 'const data = this.mailTemplate.mailTemplateType.templateData;',
            errors: [{ message: TEMPLATE_DATA }],
        },
        {
            name: 'a receiver that is the name after *.',
            code: 'mailTemplateType.templateData;',
            errors: [{ message: TEMPLATE_DATA }],
        },
        {
            name: 'a service read through this',
            code: "this.mailService.sendMailTemplate('a', 'b');",
            errors: [{ message: SEND_MAIL }],
        },
        {
            name: 'a service from the container that passes null for the argument',
            code: "Shopware.Service('mailService').sendMailTemplate('a', 'b', null);",
            errors: [{ message: SEND_MAIL }],
        },
        {
            name: 'a service from Service().get() that passes undefined for the argument',
            code: "Shopware.Service().get('mailService').sendMailTemplate('a', 'b', undefined, 'd');",
            errors: [{ message: SEND_MAIL }],
        },
        {
            name: 'a Pinia store',
            code: "const states = Shopware.Store.get('swProductDetail').productStates;",
            errors: [{ message: PRODUCT_STATES_GETTER }],
        },
        {
            name: 'a component-scoped step inside an override',
            code: "Shopware.Component.override('sw-order-list', { computed: { a() { return this.isScoped; } } });",
            errors: [{ message: IS_SCOPED }],
        },
        {
            name: 'a component-scoped step inside an extension',
            code: "Component.extend('swag-list', 'sw-order-list', { computed: { a() { return this.isScoped; } } });",
            errors: [{ message: IS_SCOPED }],
        },
        {
            name: 'a dotted receiver that starts with this',
            code: 'export default { computed: { a() { return this.product.states; } } };',
            errors: [{ message: PRODUCT_STATES }],
        },
        {
            name: 'a review comment above the line at the partial level',
            settings: atLevel('partial'),
            code: "function load() {\n    return Shopware.State.get('x');\n}",
            output: `function load() {\n    ${todoScript('Shopware.State is removed.')}\n    return Shopware.State.get('x');\n}`,
            errors: [{ message: STATE }],
        },
        {
            name: 'a review comment above the first code line of a multi-line template literal',
            settings: atLevel('partial'),
            code: 'const text = `a\n${Shopware.State.get("x")}`;',
            output: `${todoScript('Shopware.State is removed.')}\nconst text = \`a\n\${Shopware.State.get("x")}\`;`,
            errors: [{ message: STATE }],
        },
    ],
});

typeScriptTester.run('no-deprecated-api-usage member-usage (TypeScript)', rule, {
    valid: [],
    invalid: [
        {
            name: 'a receiver behind a type assertion',
            filename: 'store.ts',
            code: "(Shopware.Store.get('swProductDetail') as Store).productStates;",
            errors: [{ message: PRODUCT_STATES_GETTER }],
        },
        {
            name: 'a component-scoped step in options wrapped in satisfies',
            filename: 'index.ts',
            code: "Shopware.Component.override('sw-order-list', { computed: { a() { return this.isScoped; } } } satisfies object);",
            errors: [{ message: IS_SCOPED }],
        },
        {
            name: 'a non-null asserted call that leaves the argument out',
            filename: 'mail.ts',
            code: "this.mailService!.sendMailTemplate!('a', 'b');",
            errors: [{ message: SEND_MAIL }],
        },
    ],
});

vueTester.run('no-deprecated-api-usage member-usage (templates)', rule, {
    valid: [
        {
            name: 'a v-for alias with the name of a member of this',
            filename: 'swag-list.vue',
            code: '<template><div v-for="$store in stores">{{ $store.state }}</div></template>',
        },
        {
            name: 'a slot prop with the name of a member of this',
            filename: 'swag-list.vue',
            code: '<template><sw-x><template #default="{ $store }">{{ $store.state }}</template></sw-x></template>',
        },
        {
            name: 'a member name in a template expression',
            filename: 'swag-list.vue',
            code: '<template><div>{{ app.$store }}</div></template>',
        },
        {
            name: 'a component-scoped step in a template of no override',
            filename: 'swag-list.vue',
            code: '<template><div>{{ isScoped }}</div></template>',
        },
        {
            name: 'a binding of the script setup block',
            filename: 'sw-order-list.override.vue',
            code: '<template><div>{{ isScoped }}</div></template>\n<script setup>\nconst isScoped = true;\n</script>',
        },
    ],
    invalid: [
        {
            name: 'a member of this read without this in a mustache',
            filename: 'swag-list.vue',
            code: '<template><div>{{ $store.state.x }}</div></template>',
            errors: [{ message: STORE }],
        },
        {
            name: 'a member of this read without this in a binding',
            filename: 'swag-list.vue',
            code: '<template><div :value="$store.getters.y" /></template>',
            errors: [{ message: STORE }],
        },
        {
            name: 'a component-scoped step in a native override of the component',
            filename: 'sw-order-list.override.vue',
            code: '<template><div v-if="isScoped" /></template>',
            errors: [{ message: IS_SCOPED }],
        },
        {
            name: 'a component-scoped step in the index override of a component folder',
            filename: '/ext/src/sw-order-list/index.override.vue',
            code: '<template><div v-if="isScoped" /></template>',
            errors: [{ message: IS_SCOPED }],
        },
        {
            name: 'a receiver path that the template reads through this',
            filename: 'swag-list.vue',
            code: '<template><div v-if="product.states.length" /></template>',
            errors: [{ message: PRODUCT_STATES }],
        },
        {
            name: 'a script and a template use in one file',
            filename: 'swag-list.vue',
            code: '<template><div>{{ $store.state }}</div></template>\n<script>\nexport default { created() { this.$store.commit("x"); } };\n</script>',
            errors: [
                { message: STORE, line: 1 },
                { message: STORE, line: 3 },
            ],
        },
    ],
});

describe('no-deprecated-api-usage member-usage (.html.twig)', () => {
    let files;

    beforeAll(() => {
        files = createTempFiles({
            'src/extension/sw-order-list/index.js':
                "import template from './swag-order-list.html.twig';\nShopware.Component.override('sw-order-list', { template });\n",
            'src/extension/sw-order-list/swag-order-list.html.twig': '',
            'src/component/swag-list/index.js': "Shopware.Component.register('swag-list', { template });\n",
            'src/component/swag-list/swag-list.html.twig': '',
        });
    });

    afterAll(() => {
        files.remove();
    });

    it('reports a member of this in a mustache and in a binding', () => {
        const messages = lintTwig(
            '{% block a %}\n    {{ $store.state.x }}\n    <div :value="$store.getters.y" />\n{% endblock %}\n',
            files.path('src/component/swag-list/swag-list.html.twig'),
            'safe',
        );

        expect(messages.map(({ message, line }) => ({ line, message: message.split('\n')[0] }))).toEqual([
            { line: 2, message: 'this.$store is removed.' },
            { line: 3, message: 'this.$store is removed.' },
        ]);
    });

    it('reports a component-scoped step in a template that an override of the component imports', () => {
        const messages = lintTwig(
            '{% block a %}\n    {{ isScoped }}\n{% endblock %}\n',
            files.path('src/extension/sw-order-list/swag-order-list.html.twig'),
            'safe',
        );

        expect(messages.map(({ message }) => message)).toEqual([expect.stringMatching(IS_SCOPED)]);
    });

    it('ignores a component-scoped step in the template of a component of the extension', () => {
        const messages = lintTwig(
            '{% block a %}\n    {{ isScoped }}\n{% endblock %}\n',
            files.path('src/component/swag-list/swag-list.html.twig'),
            'safe',
        );

        expect(messages).toEqual([]);
    });
});
