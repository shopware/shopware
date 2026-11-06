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
        {
            name: 'allows a key that only shares the prefix of a removed key',
            code: 'const label = "global.sw-condition.condition.dayOfWeekRuleX";',
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

const templateTester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

templateTester.run('no-deprecated-snippet-keys in templates', rule, {
    valid: [
        {
            name: 'allows unrelated snippet key in a template',
            filename: 'test.html.twig',
            code: "<template><div>{{ $t('sw-product.detail.labelName') }}</div></template>",
        },
    ],
    invalid: [
        {
            name: 'reports removed snippet key in a single-file component mustache',
            filename: 'test.vue',
            code: "<template><div>{{ $t('global.sw-condition.condition.dayOfWeekRule') }}</div></template>",
            errors: [
                {
                    message: /removed key "global\.sw-condition\.condition\.dayOfWeekRule"/,
                },
            ],
        },
        {
            name: 'reports removed snippet key in a twig template mustache',
            filename: 'test.html.twig',
            code: "<template><div>{{ $t('global.sw-condition.condition.dayOfWeekRule') }}</div></template>",
            errors: [
                {
                    message: /removed key "global\.sw-condition\.condition\.dayOfWeekRule"/,
                },
            ],
        },
        {
            name: 'reports removed snippet key in a directive binding',
            filename: 'test.html.twig',
            code: '<template><sw-field :label="$tc(\'global.sw-condition.condition.dayOfWeekRule\')" /></template>',
            errors: [
                {
                    message: /removed key "global\.sw-condition\.condition\.dayOfWeekRule"/,
                },
            ],
        },
    ],
});
