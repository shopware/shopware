const RuleTester = require('eslint').RuleTester
const rule = require('./no-deprecated-component-usage');
const { mtIconValidTests, mtIconInvalidTests } = require('./no-deprecated-component-usage-checks/mt-icon.check');
const { mtButtonValidChecks, mtButtonInvalidChecks } = require("./no-deprecated-component-usage-checks/mt-button.check");
const { mtCardValidTests, mtCardInvalidTests } = require("./no-deprecated-component-usage-checks/mt-card.check");
const { mtTextFieldValidTests, mtTextFieldInvalidTests } = require("./no-deprecated-component-usage-checks/mt-text-field.check");
const { mtSwitchValidChecks, mtSwitchInvalidChecks } = require("./no-deprecated-component-usage-checks/mt-switch.check");
const { mtNumberFieldValidTests, mtNumberFieldInvalidTests } = require("./no-deprecated-component-usage-checks/mt-number-field.check");
const { mtCheckboxValidTests, mtCheckboxInvalidTests } = require("./no-deprecated-component-usage-checks/mt-checkbox.check");
const { mtTabsValidTests, mtTabsInvalidTests } = require("./no-deprecated-component-usage-checks/mt-tabs.check");
const { mtSelectValidTests, mtSelectInvalidTests } = require("./no-deprecated-component-usage-checks/mt-select.check");
const { mtTextareaValidTests, mtTextareaInvalidTests } = require("./no-deprecated-component-usage-checks/mt-textarea.check");

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
        ...mtCardValidTests,
        ...mtTextFieldValidTests,
        ...mtSwitchValidChecks,
        ...mtNumberFieldValidTests,
        ...mtCheckboxValidTests,
        ...mtTabsValidTests,
        ...mtSelectValidTests,
        ...mtTextareaValidTests,
    ],
    invalid: [
        ...mtButtonInvalidChecks,
        ...mtIconInvalidTests,
        ...mtCardInvalidTests,
        ...mtTextFieldInvalidTests,
        ...mtSwitchInvalidChecks,
        ...mtNumberFieldInvalidTests,
        ...mtCheckboxInvalidTests,
        ...mtTabsInvalidTests,
        ...mtCheckboxInvalidTests,
        ...mtSelectInvalidTests,
        ...mtTextareaInvalidTests,
    ]
})
