const RuleTester = require('eslint').RuleTester;
const rule = require('./no-deprecated-snippet-keys');

const tester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

tester.run('no-deprecated-snippet-keys', rule, {
    valid: [
        {
            name: 'allows unrelated snippet key',
            code: 'const label = "sw-product.detail.labelName";',
        },
    ],
    invalid: [
        {
            name: 'reports exact removed snippet key',
            code: 'const label = "global.sw-condition.condition.dayOfWeekRule";',
            errors: [
                {
                    message: /removed key "global\.sw-condition\.condition\.dayOfWeekRule"/,
                },
            ],
        },
        {
            name: 'reports nested removed snippet key',
            code: 'const label = `global.sw-condition.condition.cartTaxDisplay.label`;',
            errors: [
                {
                    message: /global\.sw-condition\.condition\.cartTaxDisplay\.label/,
                },
            ],
        },
    ],
});
