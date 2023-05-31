/**
 * @package admin
 */

const path = require('path');

const baseRules = {
    'file-progress/activate': 1,
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
    'sw-core-rules/require-position-identifier': ['error', {
        components: [
            'sw-card',
            'sw-tabs',
            'sw-extension-component-section',
        ],
    }],
    'sw-deprecation-rules/private-feature-declarations': 'error',
    'no-restricted-exports': 'off',
    'filename-rules/match': [2, /^(?!.*\.spec\.ts$).*(?:\.js|\.ts|\.html|\.html\.twig)$/],
    'vue/multi-word-component-names': ['error', {
        ignores: ['index.html'],
    }],
};

module.exports = {
    root: true,
    extends: [
        '@shopware-ag/eslint-config-base',
    ],
    env: {
        browser: true,
        'jest/globals': true,
    },

    globals: {
        Shopware: true,
        VueJS: true,
        Cypress: true,
        cy: true,
        autoStub: true,
        flushPromises: true,
    },

    plugins: [
        'jest',
        'twig-vue',
        'inclusive-language',
        'vuejs-accessibility',
        'file-progress',
        'sw-core-rules',
        'sw-deprecation-rules',
        'sw-test-rules',
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
            extends: [
                'plugin:vue/recommended',
                '@shopware-ag/eslint-config-base',
            ],
            files: ['**/*.js'],
            excludedFiles: '*.spec.js',
            rules: {
                ...baseRules,
                'vue/require-prop-types': 'error',
                'vue/require-default-prop': 'error',
                'vue/no-mutating-props': 'error',
                'vue/component-definition-name-casing': ['error', 'kebab-case'],
                'vue/no-boolean-default': ['error', 'default-false'],
                'vue/order-in-components': ['error', {
                    order: [
                        'el',
                        'name',
                        'parent',
                        'functional',
                        ['template', 'render'],
                        'inheritAttrs',
                        ['provide', 'inject'],
                        'extends',
                        'mixins',
                        'model',
                        ['components', 'directives', 'filters'],
                        ['props', 'propsData'],
                        'data',
                        'metaInfo',
                        'computed',
                        'watch',
                        'LIFECYCLE_HOOKS',
                        'methods',
                        ['delimiters', 'comments'],
                        'renderError',
                    ],
                }],
            },
        }, {
            extends: [
                'plugin:vue/essential',
                'plugin:vue/recommended',
                'eslint:recommended',
                'plugin:vuejs-accessibility/recommended',
            ],
            processor: 'twig-vue/twig-vue',
            files: ['**/*.html.twig'],
            rules: {
                'vue/component-name-in-template-casing': ['error', 'kebab-case', {
                    registeredComponentsOnly: true,
                    ignores: [],
                }],
                'vue/html-indent': ['error', 4, {
                    baseIndent: 0,
                }],
                'eol-last': 'off', // no newline required at the end of file
                'no-multiple-empty-lines': ['error', { max: 1 }],
                'max-len': 'off',
                'vue/attribute-hyphenation': 'error',
                'vue/multiline-html-element-content-newline': 'off', // allow more spacy templates
                'vue/html-self-closing': ['error', {
                    html: {
                        void: 'never',
                        normal: 'never',
                        component: 'always',
                    },
                    svg: 'always',
                    math: 'always',
                }],
                'vue/no-multiple-template-root': 'off',
                'vue/no-unused-vars': 'off',
                'vue/no-template-shadow': 'off',
                'vue/no-v-html': 'off',
                'vue/valid-template-root': 'off',
                'vue/no-parsing-error': ['error', {
                    'nested-comment': false,
                }],
                'vue/valid-v-slot': ['error', {
                    allowModifiers: true,
                }],
                'vue/v-slot-style': 'error',
                'vue/attributes-order': 'error',
                'vue/no-deprecated-slot-attribute': ['error'],
                'vue/no-deprecated-slot-scope-attribute': ['error'],
                'sw-deprecation-rules/no-twigjs-blocks': 'error',
                'vue/no-useless-template-attributes': 'error',
                'vue/no-lone-template': 'error',
            },
        }, {
            files: ['**/*.spec.js', '**/fixtures/*.js', 'test/**/*.js', 'test/**/*.ts'],
            rules: {
                'sw-test-rules/await-async-functions': 'error',
                'max-len': 0,
                'sw-deprecation-rules/private-feature-declarations': 0,
                'jest/expect-expect': 'error',
                'jest/no-duplicate-hooks': 'error',
                'jest/no-test-return-statement': 'error',
                'jest/prefer-hooks-in-order': 'error',
                'jest/prefer-hooks-on-top': 'error',
                'jest/prefer-to-be': 'error',
                'jest/require-top-level-describe': 'error',
                'jest/prefer-to-contain': 'error',
                'jest/prefer-to-have-length': 'error',
                'jest/consistent-test-it': ['error', { fn: 'it', withinDescribe: 'it' }],
            },
            extends: ['plugin:jest/recommended'],
        }, {
            files: ['**/snippet/*.json'],
            rules: {
                'inclusive-language/use-inclusive-words': 'error',
            },
        }, {
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
            plugins: ['@typescript-eslint'],
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
