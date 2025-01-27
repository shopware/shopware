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
* @sw-package framework
*/
const foo = 'bar';`
        },
        {
            name: 'TS File with package annotation',
            filename: 'test.ts',
            code: `
/**
* @sw-package framework
*/
const foo = 'bar';`
        },
        {
            name: 'JS File with package annotation after comment',
            filename: 'test.js',
            code: `
// This is a comment

/**
* @sw-package framework
*/
const foo = 'bar';`
        },
        {
            name: 'TS File with package annotation after comment',
            filename: 'test.ts',
            code: `
// This is a comment

/**
* @sw-package framework
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
                message: 'File is missing \'@sw-package\' annotation',
                line: 1,
            }]
        },
        {
            name: 'TS File without package annotation',
            filename: 'test.ts',
            code: `const foo = 'bar';`,
            errors: [{
                message: 'File is missing \'@sw-package\' annotation',
                line: 1,
            }]
        },
        {
            name: 'JS File with package annotation in line comment',
            filename: 'test.js',
            code: `
// @sw-package framework

const foo = 'bar';
`,
            errors: [{
                message: 'File is missing \'@sw-package\' annotation',
                line: 1,
            }]
        },
        {
            name: 'TS File with package annotation in line comment',
            filename: 'test.ts',
            code: `
// @sw-package framework

const foo = 'bar';
            `,
            errors: [{
                message: 'File is missing \'@sw-package\' annotation',
                line: 1,
            }]
        },
        {
            name: 'JS File with old package annotation',
            filename: 'test.js',
            code: `
/*
 *
 *    @package    framework
*/

const foo = 'bar';
`,
            errors: [{
                message: 'Use \'@sw-package\' instead of \'@package\'',
                line: 4,
            }],
            output: `
/*
 *
 *    @sw-package    framework
*/

const foo = 'bar';
`,
        },
        {
            name: 'TS File with old package annotation',
            filename: 'test.ts',
            code: `
/*
 * @package framework
*/

const foo = 'bar';
`,
            errors: [{
                message: 'Use \'@sw-package\' instead of \'@package\'',
                line: 3,
            }],
            output: `
/*
 * @sw-package framework
*/

const foo = 'bar';
`,
        },
        {
            name: 'JS File with wrong package annotation',
            filename: 'test.js',
            code: `
/*
 * @sw-package missing
*/

const foo = 'bar';
`,
            errors: [{
                message: 'Invalid domain \'missing\'. Must be one of \'buyers-experience\', \'services-settings\', \'administration\', \'data-services\', \'innovation\', \'framework\', \'inventory\', \'discovery\', \'checkout\', \'after-sales\', \'b2b\', \'fundamentals@framework\', \'fundamentals@discovery\', \'fundamentals@checkout\', \'fundamentals@after-sales\'',
                line: 3,
            }]
        },
        {
            name: 'TS File with wrong package annotation',
            filename: 'test.ts',
            code: `
/*
 * @sw-package missing
*/

const foo = 'bar';
`,
            errors: [{
                message: 'Invalid domain \'missing\'. Must be one of \'buyers-experience\', \'services-settings\', \'administration\', \'data-services\', \'innovation\', \'framework\', \'inventory\', \'discovery\', \'checkout\', \'after-sales\', \'b2b\', \'fundamentals@framework\', \'fundamentals@discovery\', \'fundamentals@checkout\', \'fundamentals@after-sales\'',
                line: 3,
            }]
        },
    ]
});
