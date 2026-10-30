/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./enforce-async-component-registers');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
    },
});

ruleTester.run('enforce-async-component-registers', rule, {
    valid: [
        {
            name: 'a lazy import',
            code: "Shopware.Component.register('sw-example', () => import('./sw-example'));",
        },
        {
            name: 'a component declared with definePublicComponent()',
            code: "import SwExample from './index';\nShopware.Component.register(SwExample);",
        },
    ],
    invalid: [
        {
            name: 'an eager component',
            code: "Shopware.Component.register('sw-example', { template: '<div />' });",
            errors: 1,
        },
        {
            name: 'a single object',
            code: "Shopware.Component.register({ name: 'sw-example' });",
            errors: 1,
        },
    ],
});
