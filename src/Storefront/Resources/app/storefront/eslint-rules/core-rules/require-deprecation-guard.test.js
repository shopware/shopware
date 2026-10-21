/**
 * @sw-package framework
 */
const { readFileSync } = require('node:fs');
const path = require('node:path');
const { describe, it } = require('node:test');
const { RuleTester } = require('eslint');
const rule = require('./require-deprecation-guard');

RuleTester.describe = describe;
RuleTester.it = it;

const tester = new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } });

function fixture(name) {
    const filename = path.join(__dirname, 'fixtures/require-deprecation-guard', name);
    return { name, filename, code: readFileSync(filename, 'utf8') };
}

tester.run('require-deprecation-guard', rule, {
    valid: [
        fixture('valid.js'),
        fixture('valid-default-function.js'),
    ],
    invalid: [
        {
            ...fixture('invalid.js'),
            errors: [
                { messageId: 'missingGuard', line: 7 },
                { messageId: 'missingGuard', line: 12 },
                { messageId: 'missingGuard', line: 16 },
                { messageId: 'missingGuard', line: 21 },
                { messageId: 'missingGuard', line: 28 },
                { messageId: 'missingGuard', line: 32 },
                { messageId: 'missingGuard', line: 37 },
                { messageId: 'missingGuard', line: 42 },
                { messageId: 'missingGuard', line: 47 },
                { messageId: 'missingGuard', line: 52 },
                { messageId: 'missingGuard', line: 57 },
                { messageId: 'missingGuard', line: 62 },
                { messageId: 'missingGuard', line: 67 },
                { messageId: 'missingGuard', line: 74 },
                { messageId: 'missingGuard', line: 81 },
                { messageId: 'missingGuard', line: 90 },
                { messageId: 'unknownFlag', line: 97, data: { flag: 'v99.0.0.0' } },
                { messageId: 'moduleGuard', line: 99 },
                { messageId: 'moduleGuard', line: 102 },
            ],
        },
        {
            ...fixture('invalid-default-function.js'),
            errors: [{ messageId: 'missingGuard', line: 6 }],
        },
    ],
});
