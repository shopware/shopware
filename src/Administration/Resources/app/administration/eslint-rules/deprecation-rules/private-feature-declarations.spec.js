const fs = require('fs');
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
    ],
    invalid: [
        {
            name: 'Default exports without private declaration',
            code: `
                export default function testFunction() {}            
            `,
            errors: [
                {
                    message:
                        'New exports need to be private. Old exports should be @deprecated tag:v6.X.0 - Will be private',
                },
            ],
        },
        {
            name: 'Named export with private declaration',
            code: `
                export const testFunction = () => {};
            `,
            errors: [
                {
                    message:
                        'New exports need to be private. Old exports should be @deprecated tag:v6.X.0 - Will be private',
                },
            ],
        },
        {
            name: 'Named exports with private declaration',
            code: `
                function testFunction() {}
                class TestClass {}
            
                export { testFunction, TestClass }            
            `,
            errors: [
                {
                    message:
                        'New exports need to be private. Old exports should be @deprecated tag:v6.X.0 - Will be private',
                },
            ],
        },
        {
            name: 'Register calls with private declaration',
            code: `
                Shopware.Component.register('my-component', {})
            `,
            errors: [
                {
                    message:
                        'New features need to be private. Old features should be @deprecated tag:v6.X.0 - Will be private',
                },
            ],
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
            errors: [
                {
                    message:
                        'New features need to be private. Old features should be @deprecated tag:v6.X.0 - Will be private',
                },
            ],
        },
    ],
});

// A made-up mini-module, so the rule finds the `index.ts`, `src/` and `tests/` it looks for on disk.
const moduleRoot = 'src/core/example-module';
const realExistsSync = fs.existsSync;
const realReadFileSync = fs.readFileSync;
const exampleIndex = `
/** @public */
export { helper } from './src/helper';
/** @public */
export default definePublicComponent({ name: 'sw-example', load: () => import('./src/sw-example') });
`;

describe('private-feature-declarations in mini-modules', () => {
    beforeEach(() => {
        jest.spyOn(fs, 'existsSync').mockImplementation(
            (file) =>
                /(^|\/)src\/core\/example-module\/(index\.ts|src|tests)$/.test(file) ||
                /(^|\/)src\/core\/example-module\/src\/example-card\/(private\.ts|src|tests)$/.test(file) ||
                realExistsSync(file),
        );
        jest.spyOn(fs, 'readFileSync').mockImplementation((file, ...options) =>
            /src\/core\/example-module\/index\.ts$/.test(file) ? exampleIndex : realReadFileSync(file, ...options),
        );
        jest.spyOn(fs, 'readdirSync').mockReturnValue([]);
    });

    ruleTester.run('private-feature-declarations', rule, {
        valid: [
            {
                name: 'The index.ts of a mini-module exports @public API',
                filename: `${moduleRoot}/index.ts`,
                code: `
                    /** @public */
                    export { helper } from './src/helper';
                `,
            },
            {
                name: 'The private.ts of a mini-module exports @private API',
                filename: `${moduleRoot}/private.ts`,
                code: `
                    /** @private */
                    export * from './src/helper';
                `,
            },
            {
                name: 'The register.ts of a mini-module registers without a marker',
                filename: `${moduleRoot}/register.ts`,
                code: "import SwExample from './index';\nShopware.Component.register(SwExample);",
            },
            {
                name: 'A src export that index.ts exposes is @public',
                filename: `${moduleRoot}/src/helper.ts`,
                code: `
                    /** @public */
                    export function helper() {}
                `,
            },
            {
                name: 'The component that index.ts loads is @public',
                filename: `${moduleRoot}/src/sw-example.js`,
                code: `
                    /** @public */
                    export default {};
                `,
            },
            {
                name: 'A src export that index.ts does not expose is @private',
                filename: `${moduleRoot}/src/helper.ts`,
                code: `
                    /** @private */
                    export function format() {}
                `,
            },
            {
                name: 'The private.ts of a nested module declares its component @private',
                filename: `${moduleRoot}/src/example-card/private.ts`,
                code: `
                    /** @private */
                    export default definePrivateComponent({ load: () => import('./src/example-card') });
                `,
            },
            {
                name: 'The component of a nested module is @private, because nothing exposes it publicly',
                filename: `${moduleRoot}/src/example-card/src/example-card.js`,
                code: `
                    /** @private */
                    export default {};
                `,
            },
            {
                name: 'The exports of dependencies.ts are @private',
                filename: `${moduleRoot}/dependencies.ts`,
                code: `
                    /** @private */
                    export const getApiContext = () => Shopware.Context.api;
                `,
            },
        ],
        invalid: [
            {
                name: 'The index.ts of a mini-module without @public',
                filename: `${moduleRoot}/index.ts`,
                code: "export { helper } from './src/helper';",
                errors: 1,
            },
            {
                name: 'The index.ts of a mini-module with @private',
                filename: `${moduleRoot}/index.ts`,
                code: `
                    /** @private */
                    export { helper } from './src/helper';
                `,
                errors: 1,
            },
            {
                name: 'The component of a nested module marked @public',
                filename: `${moduleRoot}/src/example-card/src/example-card.js`,
                code: `
                    /** @public */
                    export default {};
                `,
                errors: 1,
            },
            {
                name: 'The private.ts of a mini-module without @private',
                filename: `${moduleRoot}/private.ts`,
                code: "export * from './src/helper';",
                errors: 1,
            },
            {
                name: 'The private.ts of a mini-module with @public',
                filename: `${moduleRoot}/private.ts`,
                code: `
                    /** @public */
                    export { helper } from './src/helper';
                `,
                errors: 1,
            },
            {
                name: 'A src export that index.ts exposes, marked @private',
                filename: `${moduleRoot}/src/helper.ts`,
                code: `
                    /** @private */
                    export function helper() {}
                `,
                errors: [{ message: 'index.ts exposes this export, so mark it @public.' }],
            },
            {
                name: 'A src export that index.ts does not expose, marked @public',
                filename: `${moduleRoot}/src/helper.ts`,
                code: `
                    /** @public */
                    export function format() {}
                `,
                errors: [{ message: 'index.ts does not expose this export, so mark it @private.' }],
            },
            {
                name: 'A src export without a marker',
                filename: `${moduleRoot}/src/helper.ts`,
                code: 'export function helper() {}',
                errors: [{ message: 'index.ts exposes this export, so mark it @public.' }],
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
        ],
    });
});

// The same made-up mini-module, seen from its `index.ts`: the rule reads `src/helper.ts` from disk.
const helperSource = `/** @public */
export function helper() {}
/** @private */
export function format() {}
`;

describe('private-feature-declarations checks src/ markers from index.ts', () => {
    beforeEach(() => {
        jest.spyOn(fs, 'existsSync').mockImplementation(
            (file) => /(^|\/)src\/core\/example-module\/(index\.ts|src|tests)$/.test(file) || realExistsSync(file),
        );
        jest.spyOn(fs, 'readdirSync').mockImplementation(() => [{ name: 'helper.ts', isDirectory: () => false }]);
        jest.spyOn(fs, 'readFileSync').mockImplementation((file, ...options) =>
            /src\/core\/example-module\/src\/helper\.ts$/.test(file) ? helperSource : realReadFileSync(file, ...options),
        );
    });

    ruleTester.run('private-feature-declarations', rule, {
        valid: [
            {
                name: 'index.ts exposes exactly the src exports marked @public',
                filename: `${moduleRoot}/index.ts`,
                code: `
                    /** @public */
                    export { helper } from './src/helper';
                `,
            },
        ],
        invalid: [
            {
                name: 'index.ts stops exposing a src export that is still marked @public',
                filename: `${moduleRoot}/index.ts`,
                code: `
                    /** @public */
                    export const unrelated = 1;
                `,
                errors: [{ message: 'src/helper.ts:2 `helper`: index.ts does not expose this export, so mark it @private.' }],
            },
            {
                name: 'index.ts starts exposing a src export that is still marked @private',
                filename: `${moduleRoot}/index.ts`,
                code: `
                    /** @public */
                    export { helper, format } from './src/helper';
                `,
                errors: [{ message: 'src/helper.ts:4 `format`: index.ts exposes this export, so mark it @public.' }],
            },
        ],
    });
});
