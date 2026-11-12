const { RuleTester } = require('eslint');
const rule = require('./enforce-async-component-registers');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

const dynamicImportMessage = (call) =>
    `The component config of ${call} must be a dynamic import: () => import('./sw-example')`;

ruleTester.run('enforce-async-component-registers', rule, {
    valid: [
        {
            name: 'allows a lazy register through the global object',
            code: "Shopware.Component.register('sw-example', () => import('./sw-example'));",
        },
        {
            name: 'allows a lazy extend',
            code: "Component.extend('sw-example-child', 'sw-example', () => import('./sw-example-child'));",
        },
        {
            name: 'allows a lazy override',
            code: "Shopware.Component.override('sw-example', () => import('./sw-example-override'));",
        },
        {
            name: 'allows an extend without its own config',
            code: "Component.extend('sw-example-child', 'sw-example');",
        },
        {
            name: 'allows an inline config passed to extend',
            code: "Component.extend('sw-example-child', 'sw-example', { methods: {} });",
        },
        {
            name: 'allows a config variable passed to extend',
            code: "Shopware.Component.extend('sw-example-child', 'sw-example', config);",
        },
        {
            name: 'allows an inline config passed to override',
            code: "Component.override('sw-example', { methods: {} });",
        },
        {
            name: 'ignores other methods of the component facade',
            code: "Shopware.Component.build('sw-example');",
        },
        {
            name: 'ignores register calls on other objects',
            code: "Shopware.Mixin.register('sw-example', { methods: {} });",
        },
    ],
    invalid: [
        {
            name: 'reports an inline config passed to register',
            code: "Shopware.Component.register('sw-example', { template: '<div></div>' });",
            errors: [{ message: dynamicImportMessage('Shopware.Component.register') }],
        },
        {
            name: 'reports an import started at boot passed to register',
            code: "Component.register('sw-example', import('./sw-example'));",
            errors: [{ message: dynamicImportMessage('Component.register') }],
        },
        {
            name: 'reports an import started at boot passed to extend',
            code: "Shopware.Component.extend('sw-example-child', 'sw-example', import('./sw-example-child'));",
            errors: [{ message: dynamicImportMessage('Shopware.Component.extend') }],
        },
        {
            name: 'reports an import started at boot passed to override',
            code: "Component.override('sw-example', import('./sw-example-override'));",
            errors: [{ message: dynamicImportMessage('Component.override') }],
        },
        {
            name: 'reports a register without a config',
            code: "Component.register('sw-example');",
            errors: [{ message: dynamicImportMessage('Component.register') }],
        },
    ],
});
