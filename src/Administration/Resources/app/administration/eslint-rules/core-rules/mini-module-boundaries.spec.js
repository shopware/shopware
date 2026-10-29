/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./mini-module-boundaries');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
    },
});

// A real mini-module, because the rule looks for the `tests/` folder next to an index.ts or private.ts.
const moduleRoot = 'src/module/sw-order/page/sw-order-list';

ruleTester.run('mini-module-boundaries', rule, {
    valid: [
        {
            name: 'other code imports the index',
            filename: 'src/module/sw-order/index.js',
            code: `import list from '${moduleRoot}';`,
        },
        {
            name: 'the index re-exports its own src',
            filename: `${moduleRoot}/index.ts`,
            code: "export { default } from './src/sw-order-list';",
        },
        {
            name: 'private.ts re-exports its own src',
            filename: `${moduleRoot}/private.ts`,
            code: "export { helper } from './src/helper';",
        },
        {
            name: 'files inside src import each other',
            filename: `${moduleRoot}/src/a.ts`,
            code: "import b from './b';",
        },
        {
            name: 'other specs of the module may use private.ts',
            filename: `${moduleRoot}/tests/sw-order-list.spec.ts`,
            code: "import { helper } from '../private';",
        },
        {
            name: 'contract tests import the index',
            filename: `${moduleRoot}/tests/requirements.spec.ts`,
            code: "import list from '../index';",
        },
        {
            name: 'a private.ts outside a mini-module is ordinary code',
            filename: 'src/app/main.ts',
            code: "import { x } from 'src/app/private';",
        },
    ],
    invalid: [
        {
            name: 'other code reaches into src through the alias',
            filename: 'src/module/sw-order/index.js',
            code: `import list from '${moduleRoot}/src/sw-order-list';`,
            errors: [{ messageId: 'srcImport' }],
        },
        {
            name: 'other code reaches into src through a relative path',
            filename: 'src/module/sw-order/page/sw-order-detail/src/sw-order-detail.js',
            code: "import list from '../../sw-order-list/src/sw-order-list';",
            errors: [{ messageId: 'srcImport' }],
        },
        {
            name: 'other code uses the private API',
            filename: 'src/module/sw-order/index.js',
            code: `import { helper } from '${moduleRoot}/private';`,
            errors: [{ messageId: 'privateImport' }],
        },
        {
            name: 'contract tests use the private API',
            filename: `${moduleRoot}/tests/regressions.spec.ts`,
            code: "import { helper } from '../private';",
            errors: [{ messageId: 'contractTestImport' }],
        },
    ],
});
