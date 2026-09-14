// Lint config for the sw-screenshot toolchain. Scoped to this directory — the repo's own ESLint
// setups cover the Administration and Storefront bundles, not .github/.
import js from '@eslint/js';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    // This file is not in tsconfig, so the typed-linting project service cannot resolve it.
    { ignores: ['node_modules/**', 'eslint.config.mjs'] },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    {
        languageOptions: {
            parserOptions: { projectService: true, tsconfigRootDir: import.meta.dirname },
        },
        rules: {
            // The seed script contract and the acceptance suite's own typings both hand us `any` at
            // the boundary; erroring on it here would only produce casts that hide nothing.
            '@typescript-eslint/no-explicit-any': 'off',
        },
    },
);
