const { RuleTester } = require('eslint');
const tsParser = require('@typescript-eslint/parser');
const rule = require('./no-ts-fix-me');

const ruleTester = new RuleTester({
    languageOptions: {
        parser: tsParser,
    },
});

const message = '$TSFixMe is `any` under another name. Use the real type, or `unknown` and narrow it.';

ruleTester.run('no-ts-fix-me', rule, {
    valid: [
        {
            name: 'allows real types',
            code: 'const modules = import.meta.glob<{ default: unknown }>("./**/*.ts");',
        },
        {
            name: 'allows a value named like the alias',
            code: 'const $TSFixMe = 1;',
        },
    ],
    invalid: [
        {
            name: 'reports the alias as a type argument',
            code: 'const modules = import.meta.glob<$TSFixMe>("./**/*.ts");',
            errors: [{ message }],
        },
        {
            name: 'reports the alias in a parameter type',
            code: 'function handle(event: $TSFixMe): void {}',
            errors: [{ message }],
        },
    ],
});
