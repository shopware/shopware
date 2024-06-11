const RuleTester = require('eslint').RuleTester
const rule = require('./require-package-annotation');

const tester = new RuleTester({
    parserOptions: {
        ecmaVersion: 6,
    },
})

tester.run('require-package-annotation', rule, {
    valid: [
        {
            name: 'JS File with package annotation',
            filename: 'test.js',
            code: `
/**
* @package admin
*/
const foo = 'bar';`
        },
        {
            name: 'TS File with package annotation',
            filename: 'test.ts',
            code: `
/**
* @package admin
*/
const foo = 'bar';`
        },
        {
            name: 'JS File with package annotation after comment',
            filename: 'test.js',
            code: `
// This is a comment

/**
* @package admin
*/
const foo = 'bar';`
        },
        {
            name: 'TS File with package annotation after comment',
            filename: 'test.ts',
            code: `
// This is a comment

/**
* @package admin
*/
const foo = 'bar';`
        },
        {
            name: 'Ignore Twig files',
            filename: 'test.html.twig',
            code: ``
        },
        {
            name: 'Ignore spec JS files',
            filename: 'test.spec.js',
            code: ``
        },
        {
            name: 'Ignore spec TS files',
            filename: 'test.spec.ts',
            code: ``
        },
    ],
    invalid: [
        {
            name: 'JS File without package annotation',
            filename: 'test.js',
            code: `const foo = 'bar';`,
            errors: [{
                message: 'Missing package annotation',
                line: 1,
            }]
        },
        {
            name: 'TS File without package annotation',
            filename: 'test.ts',
            code: `const foo = 'bar';`,
            errors: [{
                message: 'Missing package annotation',
                line: 1,
            }]
        },
        {
            name: 'JS File with package annotation in line comment',
            filename: 'test.js',
            code: `
// @package admin

const foo = 'bar';
`,
            errors: [{
                message: 'Missing package annotation',
                line: 1,
            }]
        },
        {
            name: 'TS File with package annotation in line comment',
            filename: 'test.ts',
            code: `
// @package admin

const foo = 'bar';
            `,
            errors: [{
                message: 'Missing package annotation',
                line: 1,
            }]
        },
    ]
})

