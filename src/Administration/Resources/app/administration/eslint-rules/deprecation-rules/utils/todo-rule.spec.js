/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const { createTodoRule } = require('./todo-rule');

const rule = createTodoRule({
    description: 'Test rule',
    script: [
        { type: 'member', object: 'Shopware', properties: ['State'], hint: 'Use Shopware.Store.' },
        { type: 'member', object: 'this', properties: ['isLoading'], components: ['sw-order-detail'], hint: 'Read the swOrderDetail store.' },
        { type: 'inject', names: ['swOrderDetailOnLoadingChange'], hint: 'Watch the swOrderDetail store.' },
        { type: 'component', names: ['sw-text-editor'], hint: 'Use mt-text-editor.' },
        { type: 'string', values: ['sw-condition-is-net-select'], hint: 'Use sw-condition-generic.' },
        { type: 'import', source: ['axios'], specifiers: ['CancelToken'], hint: 'Use an AbortController.' },
        { type: 'option', names: ['productStates'], components: ['sw-product-detail'], hint: 'Use productType.' },
        { type: 'key', names: ['cancelToken'], hint: 'Pass an AbortController signal.' },
        { type: 'file', pattern: /^vite\.config\.[cm]?[jt]s$/, hint: 'Migrate this config to Vite 8.' },
        { type: 'member', service: 'systemConfigApiService', properties: ['getConfig'], hint: 'Use getSchema().' },
        { type: 'member', store: 'swProductDetail', properties: ['productStates'], hint: 'Use productType.' },
    ],
    template: [
        { type: 'element', names: ['sw-text-editor'], hint: 'Use mt-text-editor.' },
        { type: 'attribute', elements: ['sw-popover'], names: ['resize-width'], hint: 'Use match-reference-width.' },
        { type: 'block', names: ['sw_removed_block'], hint: 'Override sw_other_block instead.' },
        { type: 'identifier', names: ['isSaveSuccessful'], hint: 'Read the swOrderDetail store.' },
    ],
});

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

const todo = (hint) => ({ messageId: 'todo', data: { hint } });

scriptTester.run('todo-rule (script)', rule, {
    valid: [
        {
            name: 'ignores other members of the same object',
            code: 'Shopware.Store.get("swOrderDetail");',
        },
        {
            name: 'ignores a component member outside of the listed components',
            filename: '/plugin/src/extension/sw-product-detail/index.js',
            code: 'export default { computed: { foo() { return this.isLoading; } } };',
        },
        {
            name: 'ignores the registration of an own component with a matching string elsewhere',
            code: 'Shopware.Component.register("acme-editor", {});',
        },
        {
            name: 'ignores other imports from the same package',
            code: 'import axios from "axios";',
        },
        {
            name: 'ignores options of other components and plain objects',
            code: [
                'Shopware.Component.override("sw-product-list", { computed: { productStates() {} } });',
                'const states = { computed: { productStates: [] } };',
            ].join('\n'),
        },
        {
            name: 'ignores the member on other services and stores',
            code: 'this.configService.getConfig(); Shopware.Store.get("swOrder").productStates;',
        },
        {
            name: 'ignores other files',
            filename: '/plugin/src/vite.config.json.js',
            code: 'export default {};',
        },
    ],
    invalid: [
        {
            name: 'comments a member of a dotted object path',
            code: [
                'export default {',
                '    created() {',
                '        Shopware.State.commit("foo");',
                '    },',
                '};',
            ].join('\n'),
            output: [
                'export default {',
                '    created() {',
                '        // TODO Codemod: Use Shopware.Store.',
                '        Shopware.State.commit("foo");',
                '    },',
                '};',
            ].join('\n'),
            errors: [todo('Use Shopware.Store.')],
        },
        {
            name: 'reports a spot that already has its comment without writing it again',
            code: [
                '// TODO Codemod: Use Shopware.Store.',
                'const state = Shopware.State;',
            ].join('\n'),
            output: null,
            errors: [todo('Use Shopware.Store.')],
        },
        {
            name: 'finds its comment among other comments above the line',
            code: [
                '// TODO Codemod: Use an AbortController.',
                '// TODO Codemod: Use Shopware.Store.',
                'import { CancelToken } from "axios";',
            ].join('\n'),
            output: null,
            errors: [todo('Use an AbortController.')],
        },
        {
            name: 'comments a destructured member',
            code: 'const { State } = Shopware;',
            output: [
                '// TODO Codemod: Use Shopware.Store.',
                'const { State } = Shopware;',
            ].join('\n'),
            errors: [todo('Use Shopware.Store.')],
        },
        {
            name: 'comments a component member inside the override of a listed component',
            code: [
                'Shopware.Component.override("sw-order-detail", {',
                '    computed: {',
                '        busy() {',
                '            return this.isLoading;',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            output: [
                'Shopware.Component.override("sw-order-detail", {',
                '    computed: {',
                '        busy() {',
                '            // TODO Codemod: Read the swOrderDetail store.',
                '            return this.isLoading;',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            errors: [todo('Read the swOrderDetail store.')],
        },
        {
            name: 'comments a component member in an override file named after the component',
            filename: '/plugin/src/extension/sw-order-detail/index.js',
            code: 'export default { computed: { busy() { return this.isLoading; } } };',
            output: [
                '// TODO Codemod: Read the swOrderDetail store.',
                'export default { computed: { busy() { return this.isLoading; } } };',
            ].join('\n'),
            errors: [todo('Read the swOrderDetail store.')],
        },
        {
            name: 'comments injected keys in array and object form',
            code: [
                'export default {',
                '    inject: [\'swOrderDetailOnLoadingChange\'],',
                '};',
                'export const other = {',
                '    inject: { onLoading: { from: \'swOrderDetailOnLoadingChange\' } },',
                '};',
            ].join('\n'),
            output: [
                'export default {',
                '    // TODO Codemod: Watch the swOrderDetail store.',
                '    inject: [\'swOrderDetailOnLoadingChange\'],',
                '};',
                'export const other = {',
                '    // TODO Codemod: Watch the swOrderDetail store.',
                '    inject: { onLoading: { from: \'swOrderDetailOnLoadingChange\' } },',
                '};',
            ].join('\n'),
            errors: [todo('Watch the swOrderDetail store.'), todo('Watch the swOrderDetail store.')],
        },
        {
            name: 'comments overrides and extensions of a component',
            code: [
                'Shopware.Component.override(\'sw-text-editor\', {});',
                'Component.extend(\'acme-editor\', \'sw-text-editor\', {});',
            ].join('\n'),
            output: [
                '// TODO Codemod: Use mt-text-editor.',
                'Shopware.Component.override(\'sw-text-editor\', {});',
                '// TODO Codemod: Use mt-text-editor.',
                'Component.extend(\'acme-editor\', \'sw-text-editor\', {});',
            ].join('\n'),
            errors: [todo('Use mt-text-editor.'), todo('Use mt-text-editor.')],
        },
        {
            name: 'comments a string literal',
            code: 'const condition = { component: \'sw-condition-is-net-select\' };',
            output: [
                '// TODO Codemod: Use sw-condition-generic.',
                'const condition = { component: \'sw-condition-is-net-select\' };',
            ].join('\n'),
            errors: [todo('Use sw-condition-generic.')],
        },
        {
            name: 'comments an import specifier',
            code: 'import axios, { CancelToken } from "axios";',
            output: [
                '// TODO Codemod: Use an AbortController.',
                'import axios, { CancelToken } from "axios";',
            ].join('\n'),
            errors: [todo('Use an AbortController.')],
        },
        {
            name: 'comments an option an override defines',
            code: [
                'Shopware.Component.override("sw-product-detail", {',
                '    computed: {',
                '        productStates() {',
                '            return [];',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            output: [
                'Shopware.Component.override("sw-product-detail", {',
                '    computed: {',
                '        // TODO Codemod: Use productType.',
                '        productStates() {',
                '            return [];',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            errors: [todo('Use productType.')],
        },
        {
            name: 'comments a key of any object',
            code: 'httpClient.get("/foo", { cancelToken: source.token });',
            output: '// TODO Codemod: Pass an AbortController signal.\nhttpClient.get("/foo", { cancelToken: source.token });',
            errors: [todo('Pass an AbortController signal.')],
        },
        {
            name: 'comments a matching file',
            filename: '/plugin/src/Resources/app/administration/src/vite.config.mts',
            code: 'export default {};',
            output: '// TODO Codemod: Migrate this config to Vite 8.\nexport default {};',
            errors: [todo('Migrate this config to Vite 8.')],
        },
        {
            name: 'comments a member of a service and of a store',
            code: [
                'Shopware.Service("systemConfigApiService").getConfig("Acme.config");',
                'const states = Shopware.Store.get("swProductDetail").productStates;',
            ].join('\n'),
            output: [
                '// TODO Codemod: Use getSchema().',
                'Shopware.Service("systemConfigApiService").getConfig("Acme.config");',
                '// TODO Codemod: Use productType.',
                'const states = Shopware.Store.get("swProductDetail").productStates;',
            ].join('\n'),
            errors: [todo('Use getSchema().'), todo('Use productType.')],
        },
        {
            name: 'puts the comment above the first line of a multi-line template literal',
            code: [
                'const text = `first',
                '${Shopware.State.get("foo")}`;',
            ].join('\n'),
            output: [
                '// TODO Codemod: Use Shopware.Store.',
                'const text = `first',
                '${Shopware.State.get("foo")}`;',
            ].join('\n'),
            errors: [todo('Use Shopware.Store.')],
        },
    ],
});

templateTester.run('todo-rule (template)', rule, {
    valid: [
        {
            name: 'ignores other tags and attributes',
            filename: 'test.html.twig',
            code: '<template><mt-text-editor /><sw-popover :z-index="10" /></template>',
        },
        {
            name: 'ignores other blocks',
            filename: 'test.html.twig',
            code: '<template>{% block sw_other_block %}{% parent %}{% endblock %}</template>',
        },
        {
            name: 'ignores slot props and v-for aliases of the same name',
            filename: 'test.html.twig',
            code: '<template><sw-card #default="{ isSaveSuccessful }"><p v-if="isSaveSuccessful" /></sw-card></template>',
        },
        {
            name: 'ignores member names in template expressions',
            filename: 'test.html.twig',
            code: '<template><div v-if="order.isSaveSuccessful" /></template>',
        },
    ],
    invalid: [
        {
            name: 'comments an element',
            filename: 'test.html.twig',
            code: [
                '<template>',
                '    <sw-text-editor v-model:value="content" />',
                '</template>',
            ].join('\n'),
            output: [
                '<template>',
                '    <!-- TODO Codemod: Use mt-text-editor. -->',
                '    <sw-text-editor v-model:value="content" />',
                '</template>',
            ].join('\n'),
            errors: [todo('Use mt-text-editor.')],
        },
        {
            name: 'comments an attribute in kebab and camel case',
            filename: 'test.html.twig',
            code: [
                '<template>',
                '    <sw-popover :resizeWidth="true" />',
                '    <sw-popover resize-width />',
                '</template>',
            ].join('\n'),
            output: [
                '<template>',
                '    <!-- TODO Codemod: Use match-reference-width. -->',
                '    <sw-popover :resizeWidth="true" />',
                '    <!-- TODO Codemod: Use match-reference-width. -->',
                '    <sw-popover resize-width />',
                '</template>',
            ].join('\n'),
            errors: [todo('Use match-reference-width.'), todo('Use match-reference-width.')],
        },
        {
            name: 'comments a Twig block override',
            filename: 'test.html.twig',
            code: [
                '<template>',
                '{% block sw_removed_block %}',
                '    {% parent %}',
                '{% endblock %}',
                '</template>',
            ].join('\n'),
            output: [
                '<template>',
                '{# TODO Codemod: Override sw_other_block instead. #}',
                '{% block sw_removed_block %}',
                '    {% parent %}',
                '{% endblock %}',
                '</template>',
            ].join('\n'),
            errors: [todo('Override sw_other_block instead.')],
        },
        {
            name: 'reports a commented Twig block without writing the comment again',
            filename: 'test.html.twig',
            code: [
                '<template>',
                '{# TODO Codemod: Override sw_other_block instead. #}',
                '{% block sw_removed_block %}{% endblock %}',
                '</template>',
            ].join('\n'),
            output: null,
            errors: [todo('Override sw_other_block instead.')],
        },
        {
            name: 'comments an sw-block that extends a removed block',
            filename: 'test.vue',
            code: [
                '<template>',
                '    <sw-block extends="sw_removed_block" />',
                '</template>',
            ].join('\n'),
            output: [
                '<template>',
                '    <!-- TODO Codemod: Override sw_other_block instead. -->',
                '    <sw-block extends="sw_removed_block" />',
                '</template>',
            ].join('\n'),
            errors: [todo('Override sw_other_block instead.')],
        },
        {
            name: 'comments the element around a template expression',
            filename: 'test.html.twig',
            code: [
                '<template>',
                '    <div>',
                '        <mt-banner v-if="isSaveSuccessful && !isLoading" />',
                '    </div>',
                '</template>',
            ].join('\n'),
            output: [
                '<template>',
                '    <div>',
                '        <!-- TODO Codemod: Read the swOrderDetail store. -->',
                '        <mt-banner v-if="isSaveSuccessful && !isLoading" />',
                '    </div>',
                '</template>',
            ].join('\n'),
            errors: [todo('Read the swOrderDetail store.')],
        },
        {
            name: 'comments the element around a Twig interpolation',
            filename: 'test.html.twig',
            code: [
                '<template>',
                '    <p>{{ isSaveSuccessful ? \'saved\' : \'\' }}</p>',
                '</template>',
            ].join('\n'),
            output: [
                '<template>',
                '    <!-- TODO Codemod: Read the swOrderDetail store. -->',
                '    <p>{{ isSaveSuccessful ? \'saved\' : \'\' }}</p>',
                '</template>',
            ].join('\n'),
            errors: [todo('Read the swOrderDetail store.')],
        },
        {
            name: 'moves an inline element below its comment',
            filename: 'test.html.twig',
            code: '<template><p>Text <sw-text-editor /></p></template>',
            output: '<template><p>Text <!-- TODO Codemod: Use mt-text-editor. -->\n<sw-text-editor /></p></template>',
            errors: [todo('Use mt-text-editor.')],
        },
    ],
});
