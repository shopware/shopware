const path = require('path');
const { Linter, RuleTester } = require('eslint');
const twigVue = require('eslint-plugin-twig-vue');
const rule = require('./no-deprecated-snippet-keys');

const GROSS = 'global.sw-condition.condition.cartTaxDisplay.gross';
const NEW_GROSS = 'global.sw-condition-generic.cartTaxDisplay.taxDisplay.options.gross';
const NET = 'global.sw-condition.condition.cartTaxDisplay.net';
const NEW_NET = 'global.sw-condition-generic.cartTaxDisplay.taxDisplay.options.net';
const TOOLTIP = 'sw-import-export.profile.addNewProfileTooltipLanguage';

const renamed = (key, replacement) => ({
    message: new RegExp(`^The Administration snippet key "${key}" is removed\\. Use "${replacement}" instead\\.\\n`),
});

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
            name: 'allows the condition labels that stay',
            code: `
                const labels = [
                    'global.sw-condition.condition.cartTaxDisplay.label',
                    'global.sw-condition.condition.lineItemOfTypeRule.label',
                    'global.sw-condition.condition.dayOfWeekRule',
                ];`,
        },
        {
            name: 'allows a key that only shares the prefix of a removed key',
            code: `const label = "${GROSS}X";`,
        },
        {
            name: 'allows building a key from a prefix that is not the parent of a removed key',
            code: "const label = $t('global.sw-condition.condition.' + type);",
        },
    ],
    invalid: [
        {
            name: 'renames a removed key and keeps the double quotes',
            code: `const label = "${GROSS}";`,
            output: `const label = "${NEW_GROSS}";`,
            errors: [renamed(GROSS, NEW_GROSS)],
        },
        {
            name: 'renames a removed key and keeps the single quotes',
            code: `this.$t('${NET}');`,
            output: `this.$t('${NEW_NET}');`,
            errors: [renamed(NET, NEW_NET)],
        },
        {
            name: 'renames a removed key in a template literal without expressions',
            code: `const label = \`${GROSS}\`;`,
            output: `const label = \`${NEW_GROSS}\`;`,
            errors: [renamed(GROSS, NEW_GROSS)],
        },
        {
            name: 'reports a key built from the parent of removed keys in a template literal',
            code: 'const label = `global.sw-condition.condition.cartTaxDisplay.${type}`;',
            output: null,
            errors: [
                {
                    message: new RegExp(
                        `^The Administration snippet key built from "global\\.sw-condition\\.condition\\.cartTaxDisplay\\." can resolve to one of the removed keys "${GROSS}", "${NET}"\\.`,
                    ),
                },
            ],
        },
        {
            name: 'reports a key built from the parent of removed keys by concatenation',
            code: "const label = 'global.sw-condition.condition.lineItemOfTypeRule.' + type;",
            output: null,
            errors: [
                {
                    message:
                        /can resolve to one of the removed keys "global\.sw-condition\.condition\.lineItemOfTypeRule\.product"/,
                },
            ],
        },
        {
            name: 'reports a key below a renamed key without fixing it',
            code: `const label = "${GROSS}.short";`,
            output: null,
            errors: [
                {
                    message: new RegExp(`uses removed key "${GROSS}", which moved to "${NEW_GROSS}"\\.`),
                },
            ],
        },
        {
            name: 'reports a key that is removed without replacement',
            code: `const tooltip = "${TOOLTIP}";`,
            output: null,
            errors: [
                {
                    message: new RegExp(
                        `^The Administration snippet key "${TOOLTIP}" uses removed key "${TOOLTIP}"\\.\\nRemove the tooltip that shows this snippet\\.\\n`,
                    ),
                },
            ],
        },
        {
            name: 'only reports a renamed key with disableFix',
            code: `const label = "${GROSS}";`,
            options: ['disableFix'],
            output: null,
            errors: [renamed(GROSS, NEW_GROSS)],
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
        {
            name: 'allows a staying label in a twig template mustache',
            filename: 'test.html.twig',
            code: "<template><div>{{ $t('global.sw-condition.condition.dayOfWeekRule') }}</div></template>",
        },
    ],
    invalid: [
        {
            name: 'renames a removed key in a single-file component mustache',
            filename: 'test.vue',
            code: `<template><div>{{ $t('${GROSS}') }}</div></template>`,
            output: `<template><div>{{ $t('${NEW_GROSS}') }}</div></template>`,
            errors: [renamed(GROSS, NEW_GROSS)],
        },
        {
            name: 'renames removed keys in twig template mustaches behind an HTML entity',
            filename: 'test.html.twig',
            code: `<template><div>A &amp; {{ $t('${GROSS}') }} / {{ $t("${NET}") }}</div></template>`,
            output: `<template><div>A &amp; {{ $t('${NEW_GROSS}') }} / {{ $t("${NEW_NET}") }}</div></template>`,
            errors: [
                renamed(GROSS, NEW_GROSS),
                renamed(NET, NEW_NET),
            ],
        },
        {
            name: 'reports a key built in a twig template mustache',
            filename: 'test.html.twig',
            code: '<template><div>{{ $t(`global.sw-condition.condition.cartTaxDisplay.${type}`) }}</div></template>',
            output: null,
            errors: [{ message: /built from "global\.sw-condition\.condition\.cartTaxDisplay\."/ }],
        },
        {
            name: 'renames a removed key in a directive binding',
            filename: 'test.html.twig',
            code: `<template><sw-field :label="$t('${NET}')" /></template>`,
            output: `<template><sw-field :label="$t('${NEW_NET}')" /></template>`,
            errors: [renamed(NET, NEW_NET)],
        },
        {
            name: 'renames a removed key in a static attribute',
            filename: 'test.html.twig',
            code: `<template><sw-field label="${NET}" /></template>`,
            output: `<template><sw-field label="${NEW_NET}" /></template>`,
            errors: [renamed(NET, NEW_NET)],
        },
    ],
});

describe('no-deprecated-snippet-keys with the twig-vue processor', () => {
    const linter = new Linter({ configType: 'flat' });
    const config = [
        {
            files: ['**/*.html.twig'],
            plugins: {
                'sw-deprecation-rules': { rules: { 'no-deprecated-snippet-keys': rule } },
            },
            processor: {
                meta: { name: 'twig-vue', version: '1.0.0' },
                ...twigVue.processors['twig-vue'],
            },
            languageOptions: {
                parser: require('vue-eslint-parser'),
                ecmaVersion: 2022,
                sourceType: 'module',
            },
            rules: {
                'sw-deprecation-rules/no-deprecated-snippet-keys': 'error',
            },
        },
    ];

    // `Linter` only lints `.js` blocks of a processor unless told otherwise; the ESLint CLI checks the config instead.
    const lintOptions = (file) => ({ filename: path.join(__dirname, file), filterCodeBlock: () => true });

    it('renames a removed key in a mustache of a twig template without a template tag', () => {
        const code = `{% block sw_condition_x %}\n<div>{{ $t('${GROSS}') }}</div>\n{% endblock %}\n`;

        const { output, messages } = linter.verifyAndFix(code, config, lintOptions('sw-condition-x.html.twig'));

        expect(messages).toEqual([]);
        expect(output).toBe(`{% block sw_condition_x %}\n<div>{{ $t('${NEW_GROSS}') }}</div>\n{% endblock %}\n`);
    });

    it('reports a removed key in a mustache of a twig template on its line', () => {
        const code = `{% block sw_import_export_x %}\n<span>{{ $t('${TOOLTIP}') }}</span>\n{% endblock %}\n`;

        const messages = linter.verify(code, config, lintOptions('sw-import-export-x.html.twig'));

        expect(messages).toEqual([
            expect.objectContaining({
                ruleId: 'sw-deprecation-rules/no-deprecated-snippet-keys',
                line: 2,
                message: expect.stringContaining(`uses removed key "${TOOLTIP}"`),
            }),
        ]);
    });
});
