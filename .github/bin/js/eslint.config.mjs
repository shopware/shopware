import js from '@eslint/js';
import globals from 'globals';
import tseslint from 'typescript-eslint';
import { defineConfig } from 'eslint/config';

// Runs from .github/ (see the lint script in package.json) so it covers every script there, not just this package.
export default defineConfig(
    {
        ignores: ['**/node_modules/**'],
    },
    js.configs.recommended,
    tseslint.configs.recommended,
    {
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module',
            globals: {
                ...globals.node,
            },
        },
        rules: {
            '@typescript-eslint/no-unused-vars': [
                'error',
                { argsIgnorePattern: '^_', varsIgnorePattern: '^_', caughtErrorsIgnorePattern: '^_' },
            ],
        },
    },
    {
        files: ['release_info_generator.ts'],
        languageOptions: {
            globals: {
                Deno: 'readonly',
            },
        },
    },
);
