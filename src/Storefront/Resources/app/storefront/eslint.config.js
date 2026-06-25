/**
 * @sw-package framework
 */

const js = require('@eslint/js');
const tseslint = require('typescript-eslint');
const jestPlugin = require('eslint-plugin-jest');
const globals = require('globals');

const isDevMode = process.env.NODE_ENV !== 'production';

module.exports = tseslint.config(
    {
        ignores: ['test/e2e/**/*', 'vendor/**/*', 'node_modules/**/*'],
    },

    js.configs.recommended,

    {
        files: ['**/*.{js,ts}'],
        plugins: {
            jest: jestPlugin,
        },
        languageOptions: {
            ecmaVersion: 2020,
            sourceType: 'module',
            parser: tseslint.parser,
            globals: {
                ...globals.browser,
                ...globals.node,
                ...globals.es2020,
                ...globals.jest,
                ...globals.jquery,
                gtag: 'readonly',
                bootstrap: 'readonly',
            },
        },
        rules: {
            'comma-dangle': ['error', 'always-multiline'],
            'one-var': ['error', 'never'],
            'no-console': ['error', { allow: ['warn', 'error'] }],
            'no-debugger': (isDevMode ? 0 : 2),
            'no-unused-vars': ['error', { caughtErrors: 'none' }],
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
    },

    {
        files: ['**/*.ts'],
        extends: [
            ...tseslint.configs.recommended,
            ...tseslint.configs.recommendedTypeChecked,
        ],
        languageOptions: {
            parserOptions: {
                project: './tsconfig.json',
                tsconfigRootDir: __dirname,
            },
        },
        rules: {
            '@typescript-eslint/no-unused-vars': ['error', { caughtErrors: 'none' }],
            '@typescript-eslint/no-redundant-type-constituents': 'off',
            '@typescript-eslint/await-thenable': 'error',
            '@typescript-eslint/consistent-type-exports': 'error',
            '@typescript-eslint/consistent-type-imports': 'error',
            '@typescript-eslint/switch-exhaustiveness-check': 'error',
            '@typescript-eslint/ban-ts-comment': 'off',
        },
    },

    {
        files: [
            'src/plugin/spatial/**/*.ts',
            'src/service/app-client.service.ts',
        ],
        rules: {
            '@typescript-eslint/no-unsafe-call': 'off',
            '@typescript-eslint/no-unsafe-member-access': 'off',
            '@typescript-eslint/no-unsafe-assignment': 'off',
            '@typescript-eslint/no-unsafe-return': 'off',
            '@typescript-eslint/no-misused-promises': 'off',
        },
    },

    {
        // Test files and manual mocks: relax rules that are overly strict in
        // the context of a test runner where vi.fn() mock references are passed
        // as arguments and this-binding is irrelevant.
        files: ['**/*.test.{js,ts}', 'test/**/*.{js,ts}', '__mocks__/**/*.ts'],
        rules: {
            '@typescript-eslint/unbound-method': 'off',
        },
    },
);
