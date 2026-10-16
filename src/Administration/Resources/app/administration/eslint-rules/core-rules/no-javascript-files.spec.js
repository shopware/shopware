const { RuleTester } = require('eslint');
const rule = require('./no-javascript-files');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

ruleTester.run('no-javascript-files', rule, {
    valid: [
        {
            name: 'allows TypeScript files',
            filename: 'src/app/component/sw-foo/index.ts',
            code: 'export default {};',
        },
    ],
    invalid: [
        {
            name: 'reports a JavaScript component',
            filename: 'src/module/sw-integration/component/sw-integration-mcp-allowlist/index.js',
            code: 'export default {};',
            errors: [{ message: 'New code is TypeScript. Rename this file to index.ts.', line: 1, column: 1 }],
        },
        {
            name: 'reports a JavaScript spec',
            filename: 'src/app/component/sw-foo/sw-foo.spec.js',
            code: "describe('sw-foo', () => {});",
            errors: [{ message: 'New code is TypeScript. Rename this file to sw-foo.spec.ts.' }],
        },
    ],
});
