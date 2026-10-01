const { RuleTester } = require('eslint');
const warningRule = require('./warning');
const errorRule = require('./error');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2020,
        sourceType: 'module',
    },
});

const createLines = (lineCount) => {
    return Array.from({ length: lineCount }, (_, index) => `const line${index} = ${index};`).join('\n');
};

ruleTester.run('test-file-max-lines-warning', warningRule, {
    valid: [
        {
            name: 'allows files below the warning threshold',
            code: createLines(499),
            options: [{ max: 500 }],
        },
    ],
    invalid: [
        {
            name: 'warns at the configured threshold',
            code: createLines(500),
            options: [{ max: 500 }],
            errors: [
                {
                    message: 'Test file has 500 lines. Split test files with 500 lines or more into smaller specs. See adr/2026-05-06-split-large-administration-test-files.md.',
                },
            ],
        },
    ],
});

ruleTester.run('test-file-max-lines-error', errorRule, {
    valid: [
        {
            name: 'allows files below the error threshold',
            code: createLines(999),
            options: [{ max: 1000 }],
        },
    ],
    invalid: [
        {
            name: 'errors at the configured threshold',
            code: createLines(1000),
            options: [{ max: 1000 }],
            errors: [
                {
                    message: 'Test file has 1000 lines. Split test files with 1000 lines or more into smaller specs. See adr/2026-05-06-split-large-administration-test-files.md.',
                },
            ],
        },
    ],
});
