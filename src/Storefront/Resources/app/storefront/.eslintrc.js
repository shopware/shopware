const isDevMode = process.env.NODE_ENV !== 'production';

module.exports = {
    root: true,
    extends: ['eslint:recommended'],
    parser: '@typescript-eslint/parser',
    'env': {
        'browser': true,
        'jquery': true,
        'node': true,
        'es6': true,
        'jest/globals': true,
    },
    'globals': {
        'gtag': true,
        'bootstrap': true,
    },
    plugins: [
        'jest',
        '@typescript-eslint',
    ],
    'parserOptions': {
        'ecmaVersion': 6,
        'sourceType': 'module',
    },
    'rules': {
        'comma-dangle': ['error', 'always-multiline'],
        'one-var': ['error', 'never'],
        'no-console': ['error', { allow: ['warn', 'error'] }],
        'no-debugger': (isDevMode ? 0 : 2),
        'prefer-const': 'warn',
        'quotes': ['warn', 'single'],
        'indent': ['warn', 4, {
            'SwitchCase': 1,
        }],
        'semi': ['error', 'always'],
        'keyword-spacing': ['error', { 'before': true }],
        'jest/no-identical-title': 'warn',
        'jest/no-focused-tests': 'error',
        'jest/no-duplicate-hooks': 'error',
    },
    overrides: [
        {
            files: ['*.ts'],
            extends: [
                'plugin:@typescript-eslint/recommended',
                'plugin:@typescript-eslint/recommended-requiring-type-checking',
            ],
            parserOptions: {
                project: './tsconfig.json',
                tsconfigRootDir: __dirname,
            },
            rules: {
                '@typescript-eslint/await-thenable': 'error',
                '@typescript-eslint/consistent-type-exports': 'error',
                '@typescript-eslint/consistent-type-imports': 'error',
                '@typescript-eslint/switch-exhaustiveness-check': 'error',
                '@typescript-eslint/ban-ts-comment': 'off',
            },
        },
    ],
};
