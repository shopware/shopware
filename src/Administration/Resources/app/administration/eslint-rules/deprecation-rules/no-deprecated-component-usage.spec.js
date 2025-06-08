const RuleTester = require('eslint').RuleTester
const rule = require('./no-deprecated-component-usage');
const { mtIconValidTests, mtIconInvalidTests } = require('./no-deprecated-component-usage-checks/mt-icon.check');
const { mtButtonValidChecks, mtButtonInvalidChecks } = require('./no-deprecated-component-usage-checks/mt-button.check');
const { mtCardValidTests, mtCardInvalidTests } = require('./no-deprecated-component-usage-checks/mt-card.check');
const { mtTextFieldValidTests, mtTextFieldInvalidTests } = require('./no-deprecated-component-usage-checks/mt-text-field.check');
const { mtSwitchValidChecks, mtSwitchInvalidChecks } = require('./no-deprecated-component-usage-checks/mt-switch.check');
const { mtNumberFieldValidTests, mtNumberFieldInvalidTests } = require('./no-deprecated-component-usage-checks/mt-number-field.check');
const { mtCheckboxValidTests, mtCheckboxInvalidTests } = require('./no-deprecated-component-usage-checks/mt-checkbox.check');
const { mtTabsValidTests, mtTabsInvalidTests } = require('./no-deprecated-component-usage-checks/mt-tabs.check');
const { mtSelectValidTests, mtSelectInvalidTests } = require('./no-deprecated-component-usage-checks/mt-select.check');
const { mtTextareaValidTests, mtTextareaInvalidTests } = require('./no-deprecated-component-usage-checks/mt-textarea.check');
const { mtBannerValidTests, mtBannerInvalidTests } = require('./no-deprecated-component-usage-checks/mt-banner.check');
const { mtExternalLinkValidTests, mtExternalLinkInvalidTests } = require('./no-deprecated-component-usage-checks/mt-external-link.check');
const { mtDatepickerInvalidTests, mtDatepickerValidTests } = require('./no-deprecated-component-usage-checks/mt-datepicker.check');
const { mtColorpickerValidTests, mtColorpickerInvalidTests } = require('./no-deprecated-component-usage-checks/mt-colorpicker.check');
const { mtEmailFieldValidTests, mtEmailFieldInvalidTests } = require('./no-deprecated-component-usage-checks/mt-email-field.check');
const { mtPasswordFieldValidTests, mtPasswordFieldInvalidTests } = require('./no-deprecated-component-usage-checks/mt-password-field.check');
const { mtProgressBarValidTests, mtProgressBarInvalidTests } = require('./no-deprecated-component-usage-checks/mt-progress-bar.check');
const { mtFloatingUiValidTests, mtFloatingUiInvalidTests } = require("./no-deprecated-component-usage-checks/mt-floating-ui.check");

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
        ...mtBannerValidTests,
        ...mtExternalLinkValidTests,
        ...mtDatepickerValidTests,
        ...mtColorpickerValidTests,
        ...mtEmailFieldValidTests,
        ...mtPasswordFieldValidTests,
        ...mtProgressBarValidTests,
        ...mtFloatingUiValidTests,
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
        ...mtSelectInvalidTests,
        ...mtTextareaInvalidTests,
        ...mtDatepickerInvalidTests,
        ...mtBannerInvalidTests,
        ...mtExternalLinkInvalidTests,
        ...mtColorpickerInvalidTests,
        ...mtEmailFieldInvalidTests,
        ...mtPasswordFieldInvalidTests,
        ...mtProgressBarInvalidTests,
        ...mtFloatingUiInvalidTests,
    ]
})
