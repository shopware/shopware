/**
 * @sw-package framework
 */

const fs = require('fs');
const { RuleTester } = require('eslint');
const rule = require('./mini-module-shopware-imports');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
    },
});

// A made-up mini-module, so the rule finds the `tests/` folder it looks for on disk.
const moduleRoot = 'src/core/example-module';
const realExistsSync = fs.existsSync;

describe('mini-module-shopware-imports', () => {
    beforeEach(() => {
        jest.spyOn(fs, 'existsSync').mockImplementation(
            (file) => /(^|\/)src\/core\/example-module\/(index\.ts|src|tests)$/.test(file) || realExistsSync(file),
        );
    });

    ruleTester.run('mini-module-shopware-imports', rule, {
        valid: [
            {
                name: 'dependencies.ts imports a shopware:* module',
                filename: `${moduleRoot}/dependencies.ts`,
                code: "export { createId } from 'shopware:utils';",
            },
            {
                name: 'dependencies.ts imports a mini-module of another team',
                filename: `${moduleRoot}/dependencies.ts`,
                code: "import { definePublicComponent } from 'shopware:mini-modules/public-component';",
            },
            {
                name: 'other code imports the Administration source',
                filename: 'src/app/main.ts',
                code: "import { createId } from 'src/core/service/util.service';",
            },
            {
                name: 'a string that names a shopware:* module is no import',
                filename: 'src/app/main.ts',
                code: "const specifier = 'shopware:utils';",
            },
        ],
        invalid: [
            {
                name: 'code outside a mini-module imports a shopware:* module',
                filename: 'src/app/main.ts',
                code: "import { createId } from 'shopware:utils';",
                errors: [{ messageId: 'outsideDependencies' }],
            },
            {
                name: 'the src/ of a mini-module imports a shopware:* module',
                filename: `${moduleRoot}/src/helper.ts`,
                code: "import Criteria from 'shopware:data/Criteria';",
                errors: [{ messageId: 'outsideDependencies' }],
            },
            {
                name: 'the index.ts of a mini-module re-exports a shopware:* module',
                filename: `${moduleRoot}/index.ts`,
                code: "export { createId } from 'shopware:utils';",
                errors: [{ messageId: 'outsideDependencies' }],
            },
            {
                name: 'a spec imports a shopware:* module',
                filename: `${moduleRoot}/tests/pinned.spec.ts`,
                code: "import useListing from 'shopware:composables/useListing';",
                errors: [{ messageId: 'outsideDependencies' }],
            },
            {
                name: 'code loads a shopware:* module on demand',
                filename: 'src/app/main.ts',
                code: "const load = () => import('shopware:stores/notification');",
                errors: [{ messageId: 'outsideDependencies' }],
            },
            {
                name: 'code re-exports all of a shopware:* module',
                filename: 'src/app/main.ts',
                code: "export * from 'shopware:mini-modules/public-component';",
                errors: [{ messageId: 'outsideDependencies' }],
            },
        ],
    });
});
