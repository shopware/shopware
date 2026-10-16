const { RuleTester } = require('eslint');
const tsParser = require('@typescript-eslint/parser');
const rule = require('./no-double-cast');

const ruleTester = new RuleTester({
    languageOptions: {
        parser: tsParser,
    },
});

ruleTester.run('no-double-cast', rule, {
    valid: [
        {
            name: 'allows a single cast',
            code: 'const product = entity as Product;',
        },
        {
            name: 'allows a cast to unknown',
            code: 'const value = response as unknown;',
        },
        {
            name: 'allows a double cast that does not pass through unknown',
            code: 'const count = (value as number) as 1 | 2;',
        },
    ],
    invalid: [
        {
            name: 'reports a cast through unknown',
            code: 'const item = (wrapper.vm as unknown as MenuItemVm).rowActive;',
            errors: [{ message: 'Avoid `as unknown as`. Fix the type, or narrow it with a type guard.' }],
        },
    ],
});
