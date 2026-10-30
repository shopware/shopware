const { RuleTester } = require('eslint');
const rule = require('./private-feature-declarations');

const ruleTester = new RuleTester({
    languageOptions: {
        parser: require('@typescript-eslint/parser'),
    },
});

ruleTester.run('private-feature-declarations', rule, {
    valid: [
        {
            name: 'The index.ts of a mini-module exports @public API',
            filename: 'src/module/sw-order/page/sw-order-list/index.ts',
            code: `
                /** @public */
                export { default } from './src/sw-order-list';
            `,
        },
        {
            name: 'The private.ts of a mini-module exports @private API',
            filename: 'src/module/sw-order/page/sw-order-list/private.ts',
            code: `
                /** @private */
                export * from './src/helper';
            `,
        },
        {
            name: 'The src of a mini-module needs no marker',
            filename: 'src/module/sw-order/page/sw-order-list/src/helper.ts',
            code: 'export function helper() {}',
        },
        {
            name: 'Default exports with private declaration',
            code: `
                /**
                 * @private
                 */
                export default function testFunction() {}            
            `,
        },
        {
            name: 'Named export with private declaration',
            code: `
                /**
                 * @private
                 */
                export const testFunction = () => {};
            `,
        },
        {
            name: 'Named exports with private declaration',
            code: `
                function testFunction() {}
                class TestClass {}
            
                /**
                 * @private
                 */
                export { testFunction, TestClass }            
            `,
        },
        {
            name: 'Register calls with private declaration',
            code: `
                /**
                 * @private
                 */
                Shopware.Component.register('my-component', {})
            `,
        },
        {
            name: 'Nested Register calls with private declaration',
            code: `
                function registerMyComponent() {
                    /**
                     * @private
                     */
                    Shopware.Module.register('my-module', {
                        component: Shopware.Component.register('my-component', {})
                    });
                };
            `,
        }
    ],
    invalid: [
        {
            name: 'The index.ts of a mini-module without @public',
            filename: 'src/module/sw-order/page/sw-order-list/index.ts',
            code: "export { default } from './src/sw-order-list';",
            errors: 1,
        },
        {
            name: 'The index.ts of a mini-module with @private',
            filename: 'src/module/sw-order/page/sw-order-list/index.ts',
            code: `
                /** @private */
                export { default } from './src/sw-order-list';
            `,
            errors: 1,
        },
        {
            name: 'The private.ts of a mini-module without @private',
            filename: 'src/module/sw-order/page/sw-order-list/private.ts',
            code: "export * from './src/helper';",
            errors: 1,
        },
        {
            name: 'The private.ts of a mini-module with @public',
            filename: 'src/module/sw-order/page/sw-order-list/private.ts',
            code: `
                /** @public */
                export { helper } from './src/helper';
            `,
            errors: 1,
        },
        {
            name: 'A marker in the src of a mini-module',
            filename: 'src/module/sw-order/page/sw-order-list/src/helper.ts',
            code: `
                /** @private */
                export function helper() {}
            `,
            errors: 1,
        },
        {
            name: '@public outside a mini-module',
            filename: 'src/app/main.ts',
            code: `
                /** @public */
                export const foo = 'foo';
            `,
            errors: 1,
        },
        {
            name: 'Default exports without private declaration',
            code: `
                export default function testFunction() {}            
            `,
            errors: [{ message: 'New exports need to be private. Old exports should be @deprecated tag:v6.X.0 - Will be private' }]
        },
        {
            name: 'Named export with private declaration',
            code: `
                export const testFunction = () => {};
            `,
            errors: [{ message: 'New exports need to be private. Old exports should be @deprecated tag:v6.X.0 - Will be private' }]
        },
        {
            name: 'Named exports with private declaration',
            code: `
                function testFunction() {}
                class TestClass {}
            
                export { testFunction, TestClass }            
            `,
            errors: [{ message: 'New exports need to be private. Old exports should be @deprecated tag:v6.X.0 - Will be private' }]
        },
        {
            name: 'Register calls with private declaration',
            code: `
                Shopware.Component.register('my-component', {})
            `,
            errors: [{ message: 'New features need to be private. Old features should be @deprecated tag:v6.X.0 - Will be private',}]
        },
        {
            name: 'Nested Register calls with private declaration',
            code: `
                function registerMyComponent() {
                    Shopware.Module.register('my-module', {
                        component: Shopware.Component.register('my-component', {})
                    });
                };
            `,
            errors: [{ message: 'New features need to be private. Old features should be @deprecated tag:v6.X.0 - Will be private',}]
        }
    ]
});

