const RuleTester = require('eslint').RuleTester
const rule = require('./no-deprecated-component-usage');
const { mtIconValidTests, mtIconInvalidTests } = require('./no-deprecated-component-usage-checks/mt-icon.check');
const { mtButtonValidChecks, mtButtonInvalidChecks } = require("./no-deprecated-component-usage-checks/mt-button.check");

const tester = new RuleTester({
    parser: require.resolve('vue-eslint-parser'),
    parserOptions: { ecmaVersion: 2015 }
})

tester.run('no-deprecated-component-usage', rule, {
    valid: [
        {
            name: 'Empty file',
            filename: 'test.html.twig',
            code: ''
        },
        ...mtButtonValidChecks,
        ...mtIconValidTests,
    ],
    invalid: [
        ...mtButtonInvalidChecks,
        ...mtIconInvalidTests,
    ]
})
