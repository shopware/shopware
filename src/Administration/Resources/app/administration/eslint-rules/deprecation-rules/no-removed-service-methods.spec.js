/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-removed-service-methods');
const data = require('./no-removed-service-methods.data');

const message = (method) => data.calls.find((call) => call.method === method).message;
const UPDATE_ADMIN = message('updateAdmin');
const EMAIL = message('email');
const PREVIEW = message('previewPattern');
const todo = (text) => `// TODO Codemod(6.8, service-calls): ${text}`;

describe('no-removed-service-methods data', () => {
    it('names the replacement of every removed method', () => {
        expect(UPDATE_ADMIN).toContain("create('integration').save(integration)");
        expect(EMAIL).toContain("Shopware.Service('validationApiService').validateEmailAddress(value)");
        expect(EMAIL).toContain('async');
        expect(PREVIEW).toContain('previewPatternByNumberRangeId(numberRangeId, pattern, start)');
    });
});

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-removed-service-methods', rule, {
    valid: [
        { name: 'ignores the replacements', code: "await Shopware.Service('validationApiService').validateEmailAddress(mail);" },
        { name: 'ignores other number range methods', code: 'this.numberRangeService.previewPatternByNumberRangeId(id, pattern, 1);' },
        { name: 'ignores other integration methods', code: 'this.integrationService.generateKey();' },
        { name: 'ignores email() of other services', code: 'this.mailValidator.email(mail);' },
        { name: 'ignores other imports of the validation service', code: "import { required } from 'src/core/service/validation.service';" },
        { name: 'ignores email imports from elsewhere', code: "import { email } from './my-validators';" },
    ],
    invalid: [
        {
            name: 'marks this.<service> calls',
            code: [
                'async function save() {',
                '    await this.integrationService.updateAdmin(this.integration.id, true);',
                '    if (!this.validationService.email(this.mail)) {',
                '        return;',
                '    }',
                "    this.numberRangeService.previewPattern('order', pattern, 1);",
                '}',
            ].join('\n'),
            output: [
                'async function save() {',
                `    ${todo(UPDATE_ADMIN)}`,
                '    await this.integrationService.updateAdmin(this.integration.id, true);',
                `    ${todo(EMAIL)}`,
                '    if (!this.validationService.email(this.mail)) {',
                '        return;',
                '    }',
                `    ${todo(PREVIEW)}`,
                "    this.numberRangeService.previewPattern('order', pattern, 1);",
                '}',
            ].join('\n'),
            errors: [{ message: UPDATE_ADMIN }, { message: EMAIL }, { message: PREVIEW }],
        },
        {
            name: 'marks Shopware.Service() and injected or destructured services',
            code: [
                "Shopware.Service('validationService').email(value);",
                'const { Service } = Shopware;',
                "Service('numberRangeService').previewPattern(type, pattern, start);",
                "const validationService = inject('validationService');",
                'validationService.email(value);',
            ].join('\n'),
            output: [
                todo(EMAIL),
                "Shopware.Service('validationService').email(value);",
                'const { Service } = Shopware;',
                todo(PREVIEW),
                "Service('numberRangeService').previewPattern(type, pattern, start);",
                "const validationService = inject('validationService');",
                todo(EMAIL),
                'validationService.email(value);',
            ].join('\n'),
            errors: [{ message: EMAIL }, { message: PREVIEW }, { message: EMAIL }],
        },
        {
            name: 'marks a direct import of email()',
            code: "import { email, required } from 'src/core/service/validation.service';",
            output: [todo(EMAIL), "import { email, required } from 'src/core/service/validation.service';"].join('\n'),
            errors: [{ message: EMAIL }],
        },
        {
            name: 'reports a marked call without a fix',
            code: [todo(EMAIL), 'this.validationService.email(mail);'].join('\n'),
            output: null,
            errors: [{ message: EMAIL }],
        },
    ],
});
