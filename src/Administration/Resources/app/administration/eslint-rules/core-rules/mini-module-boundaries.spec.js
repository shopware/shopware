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

// A made-up mini-module, so the rule finds the `index.ts`, `src/` and `tests/` it looks for on disk,
// with two nested private modules in its src/ that have only a `private.ts`, and a second module beside it.
const moduleRoot = 'src/core/example-module';
const nestedRoot = `${moduleRoot}/src/example-card`;
const realExistsSync = fs.existsSync;

describe('mini-module-boundaries', () => {
    beforeEach(() => {
        jest.spyOn(fs, 'existsSync').mockImplementation(
            (file) =>
                /(^|\/)src\/core\/example-module\/(index\.ts|src|tests)$/.test(file) ||
                /(^|\/)src\/core\/example-module\/src\/(example|other)-card\/(private\.ts|src|tests)$/.test(file) ||
                /(^|\/)src\/core\/other-module\/(index\.ts|private\.ts|src|tests)$/.test(file) ||
                realExistsSync(file),
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
                name: 'contract tests import the index with its extension',
                filename: `${moduleRoot}/tests/public-api.spec.ts`,
                code: "import { helper } from '../index.ts';",
            },
            {
                name: 'the contract tests of a module without an index.ts import its private.ts',
                filename: `${nestedRoot}/tests/requirements.spec.ts`,
                code: "import { helper } from '../private';",
            },
            {
                name: 'the enclosing module imports the private.ts of its nested module',
                filename: `${moduleRoot}/src/example.ts`,
                code: "import SwExampleCard from './example-card/private';",
            },
            {
                name: 'the files inside a nested module import each other',
                filename: `${nestedRoot}/private.ts`,
                code: "export { default } from './src/example-card';",
            },
            {
                name: 'the global.d.ts of a private module types the component its private.ts declares',
                filename: `${moduleRoot}/global.d.ts`,
                code: "import SwExample from './private';",
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
                name: 'other code uses the private.ts of a nested module',
                filename: 'src/core/consumer.ts',
                code: `import SwExampleCard from '${nestedRoot}/private';`,
                errors: [{ messageId: 'nestedPrivateImport' }],
            },
            {
                name: 'the tests of the enclosing module use the private.ts of a nested module',
                filename: `${moduleRoot}/tests/example.harness.ts`,
                code: "import SwExampleCard from '../src/example-card/private';",
                errors: [{ messageId: 'nestedPrivateImport' }],
            },
            {
                name: 'other code reaches into the src of a nested module',
                filename: `${moduleRoot}/src/example.ts`,
                code: "import SwExampleCard from './example-card/src/example-card';",
                errors: [{ messageId: 'srcImport' }],
            },
            {
                name: 'public-api specs reach into src',
                filename: `${moduleRoot}/tests/public-api.spec.ts`,
                code: "import SwExample from '../src/sw-example';",
                errors: [{ messageId: 'contractTestImport' }],
            },
            {
                name: 'contract tests use the private API',
                filename: `${moduleRoot}/tests/regressions.spec.ts`,
                code: "import { helper } from '../private';",
                errors: [{ messageId: 'contractTestImport' }],
            },
            {
                name: 'the contract tests of a module without an index.ts reach into its src',
                filename: `${nestedRoot}/tests/requirements.spec.ts`,
                code: "import { helper } from '../src/helper';",
                errors: [{ messageId: 'contractTestImport' }],
            },
            {
                name: 'the contract tests of a module without an index.ts import its dependencies',
                filename: `${nestedRoot}/tests/requirements.spec.ts`,
                code: "import { helper } from '../dependencies';",
                errors: [{ messageId: 'contractTestImport' }],
            },
            {
                name: 'the contract tests of a nested module use the private.ts of another nested module',
                filename: `${nestedRoot}/tests/requirements.spec.ts`,
                code: "import { other } from '../../other-card/private';",
                errors: [{ messageId: 'nestedPrivateImport' }],
            },
            {
                name: 'contract tests use the private API of another module',
                filename: `${moduleRoot}/tests/requirements.spec.ts`,
                code: "import { other } from 'src/core/other-module/private';",
                errors: [{ messageId: 'privateImport' }],
            },
        ],
    });
});
