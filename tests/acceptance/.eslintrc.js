/**
 * @package admin
 */

const path = require('path');

const baseRules = {
    // Match the max line length with the phpstorm default settings
    'max-len': ['error', 125, { ignoreRegExpLiterals: true }],
    // Warn about useless path segment in import statements
    'import/no-useless-path-segments': 0,
    // don't require .vue and .js extensions
    'import/extensions': ['error', 'always', {
        js: 'never',
        ts: 'never',
        vue: 'never',
    }],
    'no-console': ['error', { allow: ['warn', 'error'] }],
    'inclusive-language/use-inclusive-words': 'error',
    'comma-dangle': ['error', 'always-multiline'],
    'no-restricted-exports': 'off',
    'filename-rules/match': [2, /^(?!.*\.spec\.ts$).*(?:\.js|\.ts|\.html|\.html\.twig)$/],
};

module.exports = {
    root: true,
    extends: [
        '@shopware-ag/eslint-config-base',
    ],
    env: {
        browser: true,
    },

    globals: {
        Shopware: true,
        VueJS: true,
        Cypress: true,
        cy: true,
        autoStub: true,
        flushPromises: true,
        wrapTestComponent: true,
    },

    plugins: [
        'inclusive-language',
        'filename-rules',
    ],

    settings: {
        'import/resolver': {
            node: {},
            webpack: {
                config: {
                    // Sync with webpack.config.js
                    resolve: {
                        extensions: ['.js', '.ts', '.vue', '.json', '.less', '.twig'],
                        alias: {
                            vue$: 'vue/dist/vue.esm.js',
                            src: path.join(__dirname, 'src'),
                            module: path.join(__dirname, 'src/module'),
                            scss: path.join(__dirname, 'src/app/assets/scss'),
                            assets: path.join(__dirname, 'static'),
                            // Alias for externals
                            Shopware: path.join(__dirname, 'src/core/shopware'),
                            '@administration': path.join(__dirname, 'src'),
                        },
                    },
                },
            },
        },
    },

    rules: {
        ...baseRules,
    },

    overrides: [
        {
            files: ['**/*.ts', '**/*.tsx'],
            extends: [
                '@shopware-ag/eslint-config-base',
                'plugin:@typescript-eslint/eslint-recommended',
                'plugin:@typescript-eslint/recommended',
                'plugin:@typescript-eslint/recommended-requiring-type-checking',
            ],
            parser: '@typescript-eslint/parser',
            parserOptions: {
                tsconfigRootDir: __dirname,
                project: ['./tsconfig.json'],
            },
            plugins: ['@typescript-eslint', 'eslint-plugin-playwright'],
            rules: {
                ...baseRules,
                '@typescript-eslint/ban-ts-comment': 0,
                '@typescript-eslint/no-unsafe-member-access': 'error',
                '@typescript-eslint/no-unsafe-call': 'error',
                '@typescript-eslint/no-unsafe-assignment': 'error',
                '@typescript-eslint/no-unsafe-return': 'error',
                '@typescript-eslint/explicit-module-boundary-types': 0,
                '@typescript-eslint/prefer-ts-expect-error': 'error',
                'no-shadow': 'off',
                '@typescript-eslint/no-shadow': ['error'],
                '@typescript-eslint/consistent-type-imports': ['error'],
                'import/extensions': [
                    'error',
                    'ignorePackages',
                    {
                        js: 'never',
                        jsx: 'never',
                        ts: 'never',
                        tsx: 'never',
                    },
                ],
                'no-void': 'off',
                // Disable the base rule as it can report incorrect errors
                'no-unused-vars': 'off',
                '@typescript-eslint/no-unused-vars': 'error',
            },
        },
    ],
};
