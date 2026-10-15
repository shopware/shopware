const { RuleTester } = require('eslint');
const rule = require('./require-wrap-component-config');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

const plainObject = { messageId: 'plainObject' };

ruleTester.run('require-wrap-component-config', rule, {
    valid: [
        {
            name: 'allows a component wrapped in wrapComponentConfig',
            code: 'export default Shopware.Component.wrapComponentConfig({ template, props: {} });',
        },
        {
            name: 'allows a component wrapped through the destructured factory',
            code: 'const { Component } = Shopware; export default Component.wrapComponentConfig({ template });',
        },
        {
            name: 'allows a component wrapped in defineComponent',
            code: `import { defineComponent } from 'vue';
            export default defineComponent({ template, props: {} });`,
        },
        {
            name: 'ignores a module definition',
            code: "export default { type: 'core', name: 'product', routes: {} };",
        },
        {
            name: 'ignores a lazy component registration',
            code: "Shopware.Component.register('sw-product-list', () => import('./page/sw-product-list'));",
        },
        {
            name: 'ignores objects passed to other calls',
            code: 'createStore({ state: {}, methods: {} });',
        },
    ],
    invalid: [
        {
            name: 'reports a plain component export',
            code: 'export default { template, inject: ["acl"], methods: {} };',
            errors: [{ ...plainObject, line: 1, column: 16 }],
        },
        {
            name: 'reports inline component options passed to the component factory',
            code: `Shopware.Component.register('sw-foo', { template });
            Component.extend('sw-bar', 'sw-foo', { methods: {} });
            Component.override('sw-foo', { created() {} });`,
            errors: [
                plainObject,
                plainObject,
                plainObject,
            ],
        },
    ],
});
