/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-removed-service-methods');

const scriptTester = new RuleTester({
    languageOptions: { ecmaVersion: 2022, sourceType: 'module' },
    settings: { 'sw-deprecation-rules': { level: 'partial' } },
});

const templateTester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2022,
        sourceType: 'module',
    },
    settings: { 'sw-deprecation-rules': { level: 'partial' } },
});

const PREVIEW_HINT = 'numberRangeService.previewPattern() was removed. Use previewPatternByNumberRangeId(numberRange.id, pattern, start), it needs a saved number range.';
const UPDATE_ADMIN_HINT = 'integrationService.updateAdmin() was removed. Set integration.admin and save the integration with repositoryFactory.create(\'integration\').save(integration).';
const EMAIL_HINT = 'validationService.email() was removed. Use await validationApiService.validateEmailAddress(value), it validates on the server.';

scriptTester.run('no-removed-service-methods (script)', rule, {
    valid: [
        {
            name: 'ignores the methods that stay',
            code: [
                'this.numberRangeService.reserve("product");',
                'this.numberRangeService.previewPatternByNumberRangeId(numberRange.id, "{n}", 1);',
                'Shopware.Service("validationService").required("value");',
            ].join('\n'),
        },
        {
            name: 'ignores methods of the same name on other objects',
            code: 'this.mailService.email("a@b.c"); admin.updateAdmin(1, true); this.pattern.previewPattern("a");',
        },
        {
            name: 'ignores other imports of the validation service file',
            code: 'import { required } from "src/core/service/validation.service";',
        },
    ],
    invalid: [
        {
            name: 'rewrites the type-based preview call core used',
            code: 'this.numberRangeService.previewPattern(this.numberRange.type.technicalName, this.numberRange.pattern, this.numberRange.start);',
            output: 'this.numberRangeService.previewPatternByNumberRangeId(this.numberRange.id, this.numberRange.pattern, this.numberRange.start);',
            errors: [{ messageId: 'previewPattern' }],
        },
        {
            name: 'comments a preview call with a plain type name',
            code: 'Shopware.Service("numberRangeService").previewPattern("product", "{n}", 1);',
            output: `/* TODO Codemod: ${PREVIEW_HINT} */\nShopware.Service("numberRangeService").previewPattern("product", "{n}", 1);`,
            errors: [{ message: /\S/ }],
        },
        {
            name: 'comments updateAdmin and email calls',
            code: [
                'this.integrationService.updateAdmin(integration.id, true);',
                'if (!validationService.email(value)) {}',
            ].join('\n'),
            output: [
                `/* TODO Codemod: ${UPDATE_ADMIN_HINT} */`,
                'this.integrationService.updateAdmin(integration.id, true);',
                `/* TODO Codemod: ${EMAIL_HINT} */`,
                'if (!validationService.email(value)) {}',
            ].join('\n'),
            errors: [{ message: /\S/ }, { message: /\S/ }],
        },
        {
            name: 'comments the email import of the validation service file',
            code: 'import { email } from "src/core/service/validation.service";',
            output: `/* TODO Codemod: ${EMAIL_HINT} */\nimport { email } from "src/core/service/validation.service";`,
            errors: [{ message: /\S/ }],
        },
    ],
});

templateTester.run('no-removed-service-methods (template)', rule, {
    valid: [
        {
            name: 'ignores other validation rules',
            filename: 'test.html.twig',
            code: '<template><sw-text-field validation="required" /></template>',
        },
    ],
    invalid: [
        {
            name: 'comments the email validation rule',
            filename: 'test.html.twig',
            code: [
                '<template>',
                '    <sw-text-field validation="required, email" />',
                '</template>',
            ].join('\n'),
            output: [
                '<template>',
                `    <!-- TODO Codemod: ${EMAIL_HINT} -->`,
                '    <sw-text-field validation="required, email" />',
                '</template>',
            ].join('\n'),
            errors: [{ message: /\S/ }],
        },
    ],
});
