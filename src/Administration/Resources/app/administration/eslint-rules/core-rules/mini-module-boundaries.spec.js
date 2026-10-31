/**
 * @sw-package framework
 */

const fs = require('fs');
const { RuleTester } = require('eslint');
const rule = require('./mini-module-boundaries');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
    },
});

// A made-up mini-module, so the rule finds the `index.ts`, `src/` and `tests/` it looks for on disk.
const moduleRoot = 'src/core/example-module';
const realExistsSync = fs.existsSync;

describe('mini-module-boundaries', () => {
    beforeEach(() => {
        jest.spyOn(fs, 'existsSync').mockImplementation(
            (file) => /(^|\/)src\/core\/example-module\/(index\.ts|src|tests)$/.test(file) || realExistsSync(file),
        );
    });

    ruleTester.run('mini-module-boundaries', rule, {
        valid: [
            {
                name: 'other code imports the index',
                filename: 'src/core/consumer.ts',
                code: `import { helper } from '${moduleRoot}';`,
            },
            {
                name: 'the index re-exports its own src',
                filename: `${moduleRoot}/index.ts`,
                code: "export { helper } from './src/helper';",
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
                filename: `${moduleRoot}/tests/helper.spec.ts`,
                code: "import { helper } from '../private';",
            },
            {
                name: 'contract tests import the index',
                filename: `${moduleRoot}/tests/requirements.spec.ts`,
                code: "import { helper } from '../index';",
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
                filename: 'src/core/consumer.ts',
                code: `import { helper } from '${moduleRoot}/src/helper';`,
                errors: [{ messageId: 'srcImport' }],
            },
            {
                name: 'other code reaches into src through a relative path',
                filename: 'src/core/other-module/src/other.ts',
                code: "import { helper } from '../../example-module/src/helper';",
                errors: [{ messageId: 'srcImport' }],
            },
            {
                name: 'other code uses the private API',
                filename: 'src/core/consumer.ts',
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
});
