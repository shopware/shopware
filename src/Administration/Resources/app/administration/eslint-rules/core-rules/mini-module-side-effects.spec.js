/**
 * @sw-package framework
 */

const fs = require('fs');
const { RuleTester } = require('eslint');
const rule = require('./mini-module-side-effects');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
    },
});

// A made-up mini-module, so the rule finds the `tests/` folder it looks for on disk.
const moduleRoot = 'src/core/example-module';
const realExistsSync = fs.existsSync;

describe('mini-module-side-effects', () => {
    beforeEach(() => {
        jest.spyOn(fs, 'existsSync').mockImplementation(
            (file) => /(^|\/)src\/core\/example-module\/(index\.ts|src|tests)$/.test(file) || realExistsSync(file),
        );
    });

    ruleTester.run('mini-module-side-effects', rule, {
        valid: [
            {
                name: 'register.ts registers',
                filename: `${moduleRoot}/register.ts`,
                code: "import SwExample from './index';\nShopware.Component.register(SwExample);",
            },
            {
                name: 'src imports its template and styles',
                filename: `${moduleRoot}/src/sw-example.js`,
                code: "import template from './sw-example.html.twig';\nimport './sw-example.scss';\nexport default { template };",
            },
            {
                name: 'code outside a mini-module is not checked',
                filename: 'src/module/sw-example/index.js',
                code: "Shopware.Component.register('sw-example', () => import('./page/sw-example'));",
            },
        ],
        invalid: [
            {
                name: 'src registers a store when it loads',
                filename: `${moduleRoot}/src/sw-example.js`,
                code: "import '../store/example.store';\nexport default {};",
                errors: [{ messageId: 'sideEffect' }],
            },
            {
                name: 'index.ts registers its component',
                filename: `${moduleRoot}/index.ts`,
                code: "Shopware.Component.register('sw-example', () => import('./src/sw-example'));",
                errors: [{ messageId: 'sideEffect' }],
            },
        ],
    });
});
