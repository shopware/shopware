// Flat ESLint config for the reproduce action's Node ESM/TypeScript sources and tests.
//
// The `.ts` sources run via Node's native type-stripping (no build); `tsc --noEmit` (npm run
// typecheck) is the type gate. ESLint here adds the syntactic + async-safety rules on top. The
// Playwright boilerplate (browser/Playwright context, uses @playwright/test) stays ignored.
import js from '@eslint/js';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default tseslint.config(
  {
    ignores: [
      'node_modules/**',
      'coverage/**',
      'executors/playwright/boilerplate/**',
      'tests/e2e/scenarios/**',
    ],
  },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  {
    files: ['**/*.ts', '**/*.mjs'],
    languageOptions: {
      ecmaVersion: 2023,
      sourceType: 'module',
      globals: { ...globals.node },
    },
    rules: {
      '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
      'prefer-const': 'error',
      'no-var': 'error',
      eqeqeq: ['error', 'smart'],
      'no-console': 'off',
    },
  },
);
