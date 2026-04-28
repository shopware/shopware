const RuleTester = require('eslint').RuleTester;
const rule = require('./no-tc-translation');

const tester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2020,
        sourceType: 'module',
    },
});

tester.run('no-tc-translation', rule, {
    valid: [
        {
            name: 'this.$t() is allowed',
            code: `this.$t('some.translation.key');`,
        },
        {
            name: '$t() in template expression is allowed',
            code: `const label = $t('some.key', 2);`,
        },
        {
            name: 'unrelated method call',
            code: `this.someMethod();`,
        },
        {
            name: '$tc as a variable name is allowed',
            code: `const $tc = 'test';`,
        },
    ],
    invalid: [
        {
            name: 'this.$tc() should be this.$t()',
            code: `this.$tc('some.translation.key');`,
            output: `this.$t('some.translation.key');`,
            errors: [{ messageId: 'noTc' }],
        },
        {
            name: 'this.$tc() with count parameter',
            code: `this.$tc('some.translation.key', count);`,
            output: `this.$t('some.translation.key', count);`,
            errors: [{ messageId: 'noTc' }],
        },
        {
            name: '$tc() without this (template context)',
            code: `$tc('some.translation.key');`,
            output: `$t('some.translation.key');`,
            errors: [{ messageId: 'noTc' }],
        },
        {
            name: '$tc() with count in template context',
            code: `$tc('some.translation.key', 3);`,
            output: `$t('some.translation.key', 3);`,
            errors: [{ messageId: 'noTc' }],
        },
        {
            name: 'multiple $tc calls in one file',
            code: `this.$tc('key.one'); this.$tc('key.two');`,
            output: `this.$t('key.one'); this.$t('key.two');`,
            errors: [{ messageId: 'noTc' }, { messageId: 'noTc' }],
        },
    ],
});
