const RuleTester = require('eslint').RuleTester;
const rule = require('./no-deprecated-snippet-keys');

const tester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

const REMOVED_KEY = 'sw-import-export.profile.addNewProfileTooltipLanguage';
const RENAMED_KEY = 'global.sw-condition.condition.cartTaxDisplay.net';
const NEW_KEY = 'global.sw-condition-generic.cartTaxDisplay.taxDisplay.options.net';

tester.run('no-deprecated-snippet-keys', rule, {
    valid: [
        {
            name: 'allows unrelated snippet key',
            code: 'const label = "sw-product.detail.labelName";',
        },
        {
            name: 'allows a key that only shares the prefix of a removed key',
            code: `const label = "${REMOVED_KEY}X";`,
        },
        {
            name: 'allows the labels of the conditions whose options moved',
            code: 'const label = "global.sw-condition.condition.cartTaxDisplay.label";',
        },
    ],
    invalid: [
        {
            name: 'reports exact removed snippet key',
            code: `const label = "${REMOVED_KEY}";`,
            errors: [
                {
                    message: /removed key "sw-import-export\.profile\.addNewProfileTooltipLanguage"/,
                },
            ],
        },
        {
            name: 'reports nested removed snippet key',
            code: `const label = \`${REMOVED_KEY}.text\`;`,
            errors: [
                {
                    message: /sw-import-export\.profile\.addNewProfileTooltipLanguage\.text/,
                },
            ],
        },
        {
            name: 'replaces a renamed snippet key and keeps the quotes',
            code: `const label = this.$t('${RENAMED_KEY}');`,
            output: `const label = this.$t('${NEW_KEY}');`,
            errors: [
                {
                    message: /"global\.sw-condition\.condition\.cartTaxDisplay\.net" is renamed/,
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
            code: `<template><div>{{ $t('${REMOVED_KEY}') }}</div></template>`,
            errors: [
                {
                    message: /removed key "sw-import-export\.profile\.addNewProfileTooltipLanguage"/,
                },
            ],
        },
        {
            name: 'replaces a renamed snippet key in a twig template mustache',
            filename: 'test.html.twig',
            code: `<template><div>{{ $t('${RENAMED_KEY}') }}</div></template>`,
            output: `<template><div>{{ $t('${NEW_KEY}') }}</div></template>`,
            errors: [
                {
                    message: /is renamed to "global\.sw-condition-generic\.cartTaxDisplay\.taxDisplay\.options\.net"/,
                },
            ],
        },
        {
            name: 'replaces a renamed snippet key in a directive binding',
            filename: 'test.html.twig',
            code: `<template><sw-field :label="$t('${RENAMED_KEY}')" /></template>`,
            output: `<template><sw-field :label="$t('${NEW_KEY}')" /></template>`,
            errors: 1,
        },
    ],
});
