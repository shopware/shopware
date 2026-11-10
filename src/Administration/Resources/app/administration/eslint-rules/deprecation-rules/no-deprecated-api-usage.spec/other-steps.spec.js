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

const INJECT_KEY = reportOf('The inject key is removed.');
const HELP_SIDEBAR = reportOf('sw-help-sidebar is removed.');
const POPOVER = reportOf('sw-popover is removed.');
const VUEX = reportOf('Vuex is removed.');
const CANCEL_TOKEN = reportOf('The CancelToken is removed.');
const PRODUCT_STATES = reportOf('Product states are removed.');
const CANCEL_TOKEN_KEY = reportOf('cancelToken is removed.');
const OPTIMIZE_DEPS = reportOf('optimizeDeps changed.');
const ROUTER_VIEW = reportOf('The router-view attributes are removed.');
const SEARCH_TERM = reportOf('searchTerm is removed.');

scriptTester.run('no-deprecated-api-usage inject-key', rule, {
    valid: [
        { name: 'another injected key', code: "export default { inject: ['acl'] };" },
        { name: 'the key as a value outside of inject', code: "const name = { key: 'swOrderDetailOnLoadingChange' }.key;" },
        { name: 'a local name that injects another key', code: "export default { inject: { swOrderDetailOnLoadingChange: 'other' } };" },
    ],
    invalid: [
        {
            name: 'an inject array',
            code: "export default { inject: ['acl', 'swOrderDetailOnLoadingChange'] };",
            errors: [{ message: INJECT_KEY }],
        },
        {
            name: 'an inject object that renames the key',
            code: "export default { inject: { onLoadingChange: 'swOrderDetailOnLoadingChange' } };",
            errors: [{ message: INJECT_KEY }],
        },
        {
            name: 'an inject object with from',
            code: "export default { inject: { onLoadingChange: { from: 'swOrderDetailOnLoadingChange', default: null } } };",
            errors: [{ message: INJECT_KEY }],
        },
        {
            name: 'an inject object with the key as local name',
            code: 'export default { inject: { swOrderDetailOnLoadingChange: { default: null } } };',
            errors: [{ message: INJECT_KEY }],
        },
        {
            name: 'the inject function of the Composition API',
            code: "const onLoadingChange = inject('swOrderDetailOnLoadingChange');",
            errors: [{ message: INJECT_KEY }],
        },
    ],
});

scriptTester.run('no-deprecated-api-usage removed-component (scripts)', rule, {
    valid: [
        { name: 'an override of another component', code: "Shopware.Component.override('sw-help-center-v2', {});" },
        { name: 'the registration of a component with the name', code: "Shopware.Component.register('sw-help-sidebar', {});" },
        { name: 'the name as base of an extension', code: "Shopware.Component.extend('sw-help-sidebar', 'sw-help-center-v2', {});" },
        {
            name: 'an override in the Administration itself',
            filename: path.resolve(__dirname, '../../../src/module/sw-profile/extension/sw-help-sidebar/index.js'),
            code: "Shopware.Component.override('sw-help-sidebar', {});",
        },
    ],
    invalid: [
        {
            name: 'an override, once per call',
            code: "Shopware.Component.override('sw-help-sidebar', {\n    template,\n});",
            errors: [{ message: HELP_SIDEBAR, line: 1, column: 1 }],
        },
        {
            name: 'an extension',
            code: "Component.extend('swag-help', 'sw-help-sidebar', {});",
            errors: [{ message: HELP_SIDEBAR }],
        },
        {
            name: 'an override of a component that is only removed for overrides',
            code: "Shopware.Component.override('sw-popover', {});",
            errors: [{ message: POPOVER }],
        },
        {
            name: 'a review comment above the call at the partial level',
            settings: atLevel('partial'),
            code: "import template from './x.html.twig';\n\nShopware.Component.override('sw-help-sidebar', { template });",
            output: `import template from './x.html.twig';\n\n${todoScript('sw-help-sidebar is removed.')}\nShopware.Component.override('sw-help-sidebar', { template });`,
            errors: [{ message: HELP_SIDEBAR }],
        },
    ],
});

vueTester.run('no-deprecated-api-usage removed-component (templates)', rule, {
    valid: [
        {
            name: 'an element of a component whose elements another migration converts',
            filename: 'swag-help.vue',
            code: '<template><sw-popover /></template>',
        },
        { name: 'another element', filename: 'swag-help.vue', code: '<template><sw-help-center-v2 /></template>' },
    ],
    invalid: [
        {
            name: 'an element in kebab-case',
            filename: 'swag-help.vue',
            code: '<template><div><sw-help-sidebar /></div></template>',
            errors: [{ message: HELP_SIDEBAR }],
        },
        {
            name: 'an element in PascalCase',
            filename: 'swag-help.vue',
            code: '<template><div><SwHelpSidebar /></div></template>',
            errors: [{ message: HELP_SIDEBAR }],
        },
        {
            name: 'a native override of the component',
            filename: 'sw-help-sidebar.override.vue',
            settings: atLevel('partial'),
            code: '<template>\n    <div />\n</template>\n<script setup>\nswDefineOverride({});\n</script>',
            output: `${todoTemplate('sw-help-sidebar is removed.')}\n<template>\n    <div />\n</template>\n<script setup>\nswDefineOverride({});\n</script>`,
            errors: [{ message: HELP_SIDEBAR }],
        },
        {
            name: 'a native override without template of a component that is only removed for overrides',
            filename: '/ext/src/sw-popover/index.override.vue',
            code: '<script setup>\nswDefineOverride({});\n</script>',
            errors: [{ message: POPOVER }],
        },
    ],
});

scriptTester.run('no-deprecated-api-usage import-source', rule, {
    valid: [
        { name: 'a package that only ends with the name', code: "import x from 'not-vuex';" },
        { name: 'another named import of the module', code: "import axios, { AxiosError } from 'axios';" },
        { name: 'a require without the named import', code: "const axios = require('axios');" },
    ],
    invalid: [
        { name: 'an import of the module', code: "import { createStore } from 'vuex';", errors: [{ message: VUEX }] },
        { name: 'a side-effect import', code: "import 'vuex';", errors: [{ message: VUEX }] },
        { name: 'a module path that ends with the source', code: "import Vuex from 'src/vendor/vuex';", errors: [{ message: VUEX }] },
        { name: 'a require', code: "const Vuex = require('vuex');", errors: [{ message: VUEX }] },
        { name: 'a dynamic import', code: "const Vuex = await import('vuex');", errors: [{ message: VUEX }] },
        { name: 'a re-export', code: "export * from 'vuex';", errors: [{ message: VUEX }] },
        {
            name: 'a named import of the specifiers',
            code: "import axios, { CancelToken } from 'axios';",
            errors: [{ message: CANCEL_TOKEN }],
        },
        {
            name: 'a named import from a module path that ends with a source',
            code: "import { CancelToken } from '../../src/core/factory/http.factory';",
            errors: [{ message: CANCEL_TOKEN }],
        },
        {
            name: 'a destructured require of the specifiers',
            code: "const { CancelToken } = require('axios');",
            errors: [{ message: CANCEL_TOKEN }],
        },
        {
            name: 'a destructured dynamic import of the specifiers',
            code: "const { CancelToken } = await import('axios');",
            errors: [{ message: CANCEL_TOKEN }],
        },
        {
            name: 'a re-export of the specifiers',
            code: "export { CancelToken } from 'axios';",
            errors: [{ message: CANCEL_TOKEN }],
        },
    ],
});

typeScriptTester.run('no-deprecated-api-usage import-source (TypeScript)', rule, {
    valid: [
        { name: 'a type-only import', filename: 'a.ts', code: "import type { CancelToken } from 'axios';" },
        { name: 'a type-only specifier', filename: 'a.ts', code: "import { type CancelToken, AxiosError } from 'axios';" },
        { name: 'a type-only import of the module', filename: 'a.ts', code: "import type { Store } from 'vuex';" },
        { name: 'only type-only specifiers', filename: 'a.ts', code: "import { type Store } from 'vuex';" },
        { name: 'a type-only re-export', filename: 'a.ts', code: "export type { CancelToken } from 'axios';" },
    ],
    invalid: [
        {
            name: 'a runtime specifier next to a type-only one',
            filename: 'a.ts',
            code: "import { type AxiosError, CancelToken } from 'axios';",
            errors: [{ message: CANCEL_TOKEN }],
        },
    ],
});

scriptTester.run('no-deprecated-api-usage string-literal', rule, {
    valid: [
        { name: 'another string', code: "const state = 'is-download-only';" },
        { name: 'an object key', code: "const labels = { 'is-physical': 'Physical' };" },
        { name: 'a computed object key', code: "const labels = { ['is-physical']: 'Physical' };" },
        { name: 'a module path', code: "import x from 'is-physical';" },
        { name: 'a dynamic import path', code: "import('is-physical');" },
        { name: 'a required module path', code: "require('is-physical');" },
    ],
    invalid: [
        { name: 'a string value', code: "const isPhysical = states.includes('is-physical');", errors: [{ message: PRODUCT_STATES }] },
        { name: 'a template literal without expressions', code: 'const state = `is-physical`;', errors: [{ message: PRODUCT_STATES }] },
        {
            name: 'a value in an object',
            code: "const config = { component: 'is-physical' };",
            errors: [{ message: PRODUCT_STATES }],
        },
    ],
});

vueTester.run('no-deprecated-api-usage string-literal (templates)', rule, {
    valid: [
        {
            name: 'a static attribute value',
            filename: 'swag-product.vue',
            code: '<template><div class="is-physical" /></template>',
        },
    ],
    invalid: [
        {
            name: 'a string in a template expression',
            filename: 'swag-product.vue',
            code: "<template><div v-if=\"item.type === 'is-physical'\" /></template>",
            errors: [{ message: PRODUCT_STATES }],
        },
    ],
});

scriptTester.run('no-deprecated-api-usage object-key', rule, {
    valid: [
        { name: 'another key', code: 'httpClient.get(url, { signal });' },
        { name: 'the key as a member', code: 'config.cancelToken = token;' },
        { name: 'the key in a destructuring', code: 'const { cancelToken } = config;' },
        { name: 'a key limited to other files', filename: 'src/main.js', code: 'export default { optimizeDeps: {} };' },
        { name: 'a key limited to files that only contain the name', filename: 'my-vite.config.js.bak.js', code: 'export default { optimizeDeps: {} };' },
    ],
    invalid: [
        {
            name: 'an object key',
            code: 'httpClient.get(url, { cancelToken: source.token });',
            errors: [{ message: CANCEL_TOKEN_KEY }],
        },
        {
            name: 'a quoted object key',
            code: "httpClient.get(url, { 'cancelToken': source.token });",
            errors: [{ message: CANCEL_TOKEN_KEY }],
        },
        {
            name: 'a key in a file whose name matches',
            filename: '/ext/src/Resources/app/administration/vite.config.mts',
            code: 'export default { optimizeDeps: {} };',
            errors: [{ message: OPTIMIZE_DEPS }],
        },
    ],
});

vueTester.run('no-deprecated-api-usage template-attribute', rule, {
    valid: [
        {
            name: 'a component-scoped attribute in the template of another component',
            filename: 'swag-order.vue',
            code: '<template><router-view :is-editing="a" /></template>',
        },
        {
            name: 'the attribute on another element',
            filename: 'sw-order-detail.override.vue',
            code: '<template><sw-x :is-editing="a" /></template>',
        },
        {
            name: 'a listener of a prop name and a prop of a listener name',
            filename: 'sw-order-detail.override.vue',
            code: '<template><router-view @is-editing="a" :loading-change="b" /></template>',
        },
        {
            name: 'a dynamic argument',
            filename: 'sw-order-detail.override.vue',
            code: '<template><router-view :[name]="a" /></template>',
        },
    ],
    invalid: [
        {
            name: 'a prop binding, a static attribute and a listener',
            filename: 'sw-order-detail.override.vue',
            code: '<template><router-view :isEditing="a" is-editing @loading-change="b" v-on:loadingChange="c" /></template>',
            errors: [{ message: ROUTER_VIEW }, { message: ROUTER_VIEW }, { message: ROUTER_VIEW }, { message: ROUTER_VIEW }],
        },
        {
            name: 'an attribute without element limit on any element',
            filename: 'swag-mail.vue',
            code: '<template><sw-mail-template-list v-model:search-term="term" /></template>',
            errors: [{ message: SEARCH_TERM }],
        },
        {
            name: 'a review comment above the element at the partial level',
            filename: 'swag-mail.vue',
            settings: atLevel('partial'),
            code: '<template>\n    <sw-mail-template-list\n        :search-term="term"\n    />\n</template>',
            output: `<template>\n    ${todoTemplate('searchTerm is removed.')}\n    <sw-mail-template-list\n        :search-term="term"\n    />\n</template>`,
            errors: [{ message: SEARCH_TERM }],
        },
    ],
});

describe('no-deprecated-api-usage template steps (.html.twig)', () => {
    let files;

    beforeAll(() => {
        files = createTempFiles({
            'src/extension/sw-order-detail/index.js': "Shopware.Component.override('sw-order-detail', { template });\n",
            'src/extension/sw-order-detail/sw-order-detail.html.twig': '',
        });
    });

    afterAll(() => {
        files.remove();
    });

    it('reports elements, attributes and strings of a twig template', () => {
        const messages = lintTwig(
            [
                '{% block sw_order_detail_content_view %}',
                '    <router-view :is-editing="isEditing" />',
                '    <sw-help-sidebar />',
                "    {{ item.type === 'is-physical' ? 'a' : 'b' }}",
                '{% endblock %}',
                '',
            ].join('\n'),
            files.path('src/extension/sw-order-detail/sw-order-detail.html.twig'),
            'safe',
        );

        expect(messages.map(({ line, message }) => ({ line, message: message.split('\n')[0] }))).toEqual([
            { line: 2, message: 'The router-view attributes are removed.' },
            { line: 3, message: 'sw-help-sidebar is removed.' },
            { line: 4, message: 'Product states are removed.' },
        ]);
    });
});
