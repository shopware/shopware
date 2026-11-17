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
        },
        {
            name: 'Default exports with public declaration',
            code: `
                /**
                 * @public
                 */
                export default function testFunction() {}
            `,
        },
        {
            name: 'Named export with public declaration',
            code: `
                /**
                 * @public
                 */
                export const testFunction = () => {};
            `,
        },
        {
            name: 'Named exports with public declaration',
            code: `
                function testFunction() {}
                class TestClass {}

                /**
                 * @public
                 */
                export { testFunction, TestClass }
            `,
        },
        {
            name: 'Type export with public declaration',
            code: `
                /**
                 * @public
                 */
                export type TestType = string;
            `,
        },
        {
            name: 'Public declaration inside a longer doc block',
            code: `
                /**
                 * Formats a value.
                 *
                 * @public
                 * @param value
                 */
                export function format(value) {}
            `,
        },
        {
            name: 'Register calls with public declaration',
            code: `
                /**
                 * @public
                 */
                Shopware.Component.register('my-component', {})
            `,
        }
    ],
    invalid: [
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
        },
        {
            name: 'Named export with public declaration in a non-JSDoc block comment',
            code: `
                /* @public */
                export const testFunction = () => {};
            `,
            errors: [{ message: 'New exports need to be private. Old exports should be @deprecated tag:v6.X.0 - Will be private' }]
        },
        {
            name: 'Named export with public declaration not directly above the export',
            code: `
                /**
                 * @public
                 */
                // some comment
                export const testFunction = () => {};
            `,
            errors: [{ message: 'New exports need to be private. Old exports should be @deprecated tag:v6.X.0 - Will be private' }]
        },
        {
            name: 'Type export without declaration',
            code: `
                export type TestType = string;
            `,
            errors: [{ message: 'New exports need to be private. Old exports should be @deprecated tag:v6.X.0 - Will be private' }]
        }
    ]
});

