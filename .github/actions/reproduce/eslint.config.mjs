// Flat ESLint config for the reproduce action's Node ESM sources and tests.
//
// Scope note: this lints the `.mjs` sources only. The Playwright boilerplate (`.ts` config, browser
// `.js`/`.mjs` helpers that run in the page context) is ignored here, and TypeScript linting is a
// deliberate FUTURE step — see README "Linting & tests". Keep the rule set pragmatic: catch real
// defects (unused vars, accidental globals, loose equality) without churning the existing style.
import js from '@eslint/js';
import globals from 'globals';

export default [
  {
    ignores: [
      'node_modules/**',
      'coverage/**',
      // Runs in the Playwright/browser context, not Node; TS + browser globals handled later.
      'executors/playwright/boilerplate/**',
      '**/*.ts',
    ],
  },
  js.configs.recommended,
  {
    files: ['**/*.mjs'],
    languageOptions: {
      ecmaVersion: 2023,
      sourceType: 'module',
      globals: { ...globals.node },
    },
    rules: {
      'no-unused-vars': ['error', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
      'prefer-const': 'error',
      'no-var': 'error',
      eqeqeq: ['error', 'smart'],
      'no-console': 'off',
    },
  },
  {
    // Tests use the node:test globals and may keep intentionally-unused fixtures.
    files: ['**/*.test.mjs'],
    languageOptions: { globals: { ...globals.node } },
  },
];
