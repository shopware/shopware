/**
 * @sw-package framework
 */
const { RuleTester } = require('eslint');
const tsParser = require('@typescript-eslint/parser');
const vueParser = require('vue-eslint-parser');
const rule = require('./no-eager-component-imports');

const languageOptions = {
    ecmaVersion: 'latest',
    sourceType: 'module',
    globals: { Shopware: 'readonly', window: 'readonly' },
};
const errors = [{ messageId: 'eager' }];

new RuleTester({ languageOptions }).run('no-eager-component-imports', rule, {
    valid: [
        {
            name: 'a loader function that starts the import',
            code: "Shopware.Component.register('swag-a', () => import('./swag-a'));",
        },
        {
            name: 'an awaited import inside an async loader',
            code: "const { Component } = Shopware; Component.override('swag-a', async () => (await import('./swag-a')).default);",
        },
        {
            name: 'an import chained inside the loader',
            code: "Shopware.Component.extend('swag-b', 'swag-a', () => import('./swag-b').then((module) => module.default));",
        },
        {
            name: 'an import started inside the loader and returned through a constant',
            code: "Shopware.Component.register('swag-a', async () => { const pending = import('./swag-a'); return await pending; });",
        },
        {
            name: 'a loader stored in a constant',
            code: "const loader = () => import('./swag-a'); Shopware.Component.register('swag-a', loader);",
        },
        {
            name: 'an imported loader function',
            code: "import loader from './loader'; Shopware.Component.register('swag-a', loader);",
        },
        {
            name: 'an object config',
            code: "Shopware.Component.override('sw-product-list', { computed: { label: () => 'label' } });",
        },
        {
            name: 'a loader returning a local object',
            code: "const config = { template: '<div />' }; Shopware.Component.register('swag-a', () => config);",
        },
        {
            name: 'a nested function that is not the returned value',
            code: "import config from './config'; Shopware.Component.register('swag-a', () => { function unused() { return config; } return import('./swag-a'); });",
        },
        {
            name: 'a Shopware parameter that shadows the global',
            code: "import config from './config'; function setup(Shopware) { Shopware.Component.register('swag-a', () => config); }",
        },
        {
            name: 'a Component that is not destructured from Shopware',
            code: "import config from './config'; const { Component } = OtherLibrary; Component.register('swag-a', () => config);",
        },
        {
            name: 'a Component parameter',
            code: "import config from './config'; function setup(Component) { Component.register('swag-a', () => config); }",
        },
        {
            name: 'other factories of the Shopware object',
            code: "import config from './config'; Shopware.Mixin.register('swag-a', () => config);",
        },
    ],
    invalid: [
        {
            name: 'an import passed as a promise',
            code: "Shopware.Component.register('swag-a', import('./swag-a'));",
            errors,
        },
        {
            name: 'a chained import passed as a promise',
            code: "Shopware.Component.override('sw-product-list', import('./override').then((module) => module.default));",
            errors,
        },
        {
            name: 'an import started in a constant',
            code: "const pending = import('./swag-a'); Shopware.Component.register('swag-a', () => pending);",
            errors,
        },
        {
            name: 'a static import returned from the loader',
            code: "import config from './config'; Shopware.Component.register('swag-a', () => config);",
            errors,
        },
        {
            name: 'a static import returned from a block body',
            code: "import config from './config'; Shopware.Component.override('sw-product-list', function () { return config; });",
            errors,
        },
        {
            name: 'a static import returned from a loader constant',
            code: "import config from './config'; const loader = () => config; Shopware.Component.register('swag-a', loader);",
            errors,
        },
        {
            name: 'a static import returned from a function declaration',
            code: "import config from './config'; function loader() { return config; } Shopware.Component.register('swag-a', loader);",
            errors,
        },
        {
            name: 'the config of extend, which is the third argument',
            code: "import config from './config'; Shopware.Component.extend('swag-b', 'swag-a', () => config);",
            errors,
        },
        {
            name: 'a Component destructured from Shopware',
            code: "const { Component } = Shopware; Component.register('swag-a', import('./swag-a'));",
            errors,
        },
        {
            name: 'a renamed Component destructured from window.Shopware',
            code: "const { Component: Registry } = window.Shopware; Registry.register('swag-a', import('./swag-a'));",
            errors,
        },
        {
            name: 'a component imported from a Vue file',
            code: "import SwagA from './swag-a.vue'; Shopware.Component.register('swag-a', SwagA);",
            errors,
        },
        {
            name: 'a component imported from a Vue file by its default name',
            code: "import { default as SwagA } from './swag-a.vue'; Shopware.Component.register('swag-a', SwagA);",
            errors,
        },
    ],
});

new RuleTester({ languageOptions: { ...languageOptions, parser: tsParser } }).run(
    'no-eager-component-imports in TypeScript',
    rule,
    {
        valid: [
            {
                name: 'a type-only import',
                code: "import type Config from './config'; Shopware.Component.register('swag-a', () => Config);",
            },
            {
                name: 'a declared loader without a body',
                code: "declare function loader(): Promise<unknown>; Shopware.Component.register('swag-a', loader);",
            },
            {
                name: 'a namespace alias, which imports no module',
                code: "namespace Configs { export const a = {}; } import config = Configs.a; Shopware.Component.register('swag-a', () => config);",
            },
        ],
        invalid: [
            {
                name: 'a static import behind a type assertion',
                code: "import config from './config'; Shopware.Component.register('swag-a', () => config as ComponentConfig);",
                errors,
            },
        ],
    },
);

new RuleTester({ languageOptions: { ...languageOptions, parser: vueParser } }).run(
    'no-eager-component-imports in Vue files',
    rule,
    {
        valid: [
            {
                filename: 'swag-a.vue',
                code: "<script setup>Shopware.Component.register('swag-b', () => import('./swag-b'));</script>",
            },
        ],
        invalid: [
            {
                filename: 'swag-a.vue',
                code: "<script setup>Shopware.Component.register('swag-b', import('./swag-b'));</script>",
                errors,
            },
        ],
    },
);
