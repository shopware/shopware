/**
 * @sw-package admin
 */

import path from 'node:path';
import { fileURLToPath } from 'node:url';
import js from '@eslint/js';
import { fixupPluginRules } from '@eslint/compat';
import tseslint from 'typescript-eslint';
import pluginVue from 'eslint-plugin-vue';
import importX from 'eslint-plugin-import-x';
import jestPlugin from 'eslint-plugin-jest';
import prettier from 'eslint-config-prettier';
import globals from 'globals';
import inclusiveLanguage from 'eslint-plugin-inclusive-language';
import fileProgress from 'eslint-plugin-file-progress';
import filenameRules from 'eslint-plugin-filename-rules';
import vuejsAccessibility from 'eslint-plugin-vuejs-accessibility';
import listeners from 'eslint-plugin-listeners';

import swCoreRules from 'eslint-plugin-sw-core-rules';
import swDeprecationRules from 'eslint-plugin-sw-deprecation-rules';
import swTestRules from 'eslint-plugin-sw-test-rules';
import twigVue from 'eslint-plugin-twig-vue';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// eslint-plugin-filename-rules doesn't define meta.schema, which ESLint 9 treats
// as "no options allowed". Patch the rule to accept an option.
const filenameRulesPatched = {
    ...filenameRules,
    rules: Object.fromEntries(
        Object.entries(filenameRules.rules).map(([name, rule]) => [
            name,
            {
                ...rule,
                meta: {
                    ...rule.meta,
                    schema: rule.meta?.schema ?? [{ oneOf: [{ type: 'string' }, { type: 'object' }] }],
                },
            },
        ]),
    ),
};

const vueParserSetup = pluginVue.configs['flat/recommended'].find(
    c => c.name === 'vue/base/setup-for-vue',
);
const vueParser = vueParserSetup.languageOptions.parser;

const baseRules = {
    'file-progress/activate': 0,
    'max-len': ['error', 125, { ignoreRegExpLiterals: true }],
    'import/no-useless-path-segments': 0,
    'import/extensions': ['error', 'ignorePackages', {
        js: 'never',
        ts: 'never',
        tsx: 'never',
        vue: 'never',
    }],
    'no-console': ['error', { allow: ['warn', 'error'] }],
    'no-warning-comments': ['error', { location: 'anywhere' }],
    'inclusive-language/use-inclusive-words': 'error',
    'comma-dangle': ['error', 'always-multiline'],
    'sw-core-rules/require-position-identifier': ['error', {
        components: [
            'sw-button',
            'sw-card',
            'sw-tabs',
            'sw-extension-component-section',
        ],
    }],
    'sw-core-rules/require-package-annotation': ['error'],
    'sw-core-rules/no-tc-translation': 'error',
    'sw-deprecation-rules/private-feature-declarations': 'error',
    'no-restricted-exports': 'off',
    'filename-rules/match': [2, /^.*(?:\.js|\.ts|\.html|\.html\.twig)$/],
    'vue/multi-word-component-names': ['error', {
        ignores: ['index.html'],
    }],
    'func-names': 'off',
    'listeners/no-missing-remove-event-listener': 'error',
    'listeners/matching-remove-event-listener': 'error',
    'listeners/no-inline-function-event-listener': 'error',

    // From @shopware-ag/eslint-config-base (airbnb-base overrides)
    'no-multiple-empty-lines': ['error', { max: 2, maxEOF: 1 }],
    'arrow-parens': 0,
    'arrow-body-style': 0,
    'generator-star-spacing': 0,
    'no-debugger': process.env.NODE_ENV === 'production' ? 2 : 0,
    indent: ['error', 4, { SwitchCase: 1 }],
    'no-use-before-define': ['error', { functions: false }],
    'no-param-reassign': 0,
    'linebreak-style': ['error', 'unix'],
    'object-shorthand': 0,
    'no-useless-escape': 0,
    'no-prototype-builtins': 0,
    'object-curly-newline': ['error', { consistent: true }],
    'no-underscore-dangle': 0,
    'prefer-destructuring': ['off', { object: true, array: false }],
    'operator-linebreak': 0,
    'import/no-cycle': 0,
    'class-methods-use-this': 0,
    'no-unused-vars': ['error', { vars: 'all', args: 'after-used', ignoreRestSiblings: true, caughtErrors: 'all', caughtErrorsIgnorePattern: '^_' }],
    'vue/prefer-import-from-vue': 'off',
    'vue/one-component-per-file': 'off',
};

export default [
    // Global ignores (from .eslintignore)
    {
        ignores: [
            'build/*.js',
            'config/*.js',
            'test/e2e/**/*',
            'scripts/**/*',
            'test/eslint/error-reference.html.twig',
            '**/*.spec.vue2.js',
            '**/*.fixtures.js',
            'src/app/adapter/_mocks_/example-extendable-script-setup-component.vue',
        ],
    },

    js.configs.recommended,

    // Vue plugin setup (global) + parser for .vue files
    ...pluginVue.configs['flat/recommended'].filter(
        c => c.name === 'vue/base/setup' || c.name === 'vue/base/setup-for-vue',
    ),
    // Vue rules scoped to JS, Vue, and Twig files only (not TS)
    ...pluginVue.configs['flat/recommended']
        .filter(c => c.name !== 'vue/base/setup' && c.name !== 'vue/base/setup-for-vue')
        .map(c => ({ ...c, files: ['**/*.js', '**/*.vue', '**/*.html.twig'] })),

    // Base config for all files
    {
        plugins: {
            import: importX,
            'inclusive-language': fixupPluginRules(inclusiveLanguage),
            'file-progress': fixupPluginRules(fileProgress),
            'filename-rules': fixupPluginRules(filenameRulesPatched),
            'sw-core-rules': fixupPluginRules(swCoreRules),
            'sw-deprecation-rules': fixupPluginRules(swDeprecationRules),
            'sw-test-rules': fixupPluginRules(swTestRules),
            'twig-vue': twigVue,
            listeners: fixupPluginRules(listeners),
        },
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
                ...globals.node,
                ...globals.jest,
                Shopware: true,
                VueJS: true,
                Cypress: true,
                cy: true,
                autoStub: true,
                flushPromises: true,
                wrapTestComponent: true,
                resetFilters: true,
            },
        },
        settings: {
            'import-x/resolver': {
                node: {},
                typescript: {
                    alwaysTryTypes: true,
                    project: './tsconfig.json',
                },
                vite: {
                    viteConfig: {
                        resolve: {
                            extensions: ['.js', '.ts', '.vue', '.json', '.less', '.twig'],
                            alias: [
                                {
                                    find: 'vue',
                                    replacement: '@vue/compat/dist/vue.esm-bundler.js',
                                },
                                {
                                    find: 'src',
                                    replacement: path.join(__dirname, 'src'),
                                },
                                {
                                    find: 'test',
                                    replacement: path.join(__dirname, 'test'),
                                },
                            ],
                        },
                    },
                },
            },
        },
        rules: {
            ...baseRules,
        },
    },

    // JS files (non-spec): Vue parser + component rules
    {
        files: ['**/*.js'],
        ignores: ['**/*.spec.js'],
        languageOptions: {
            parser: vueParser,
            parserOptions: {
                sourceType: 'module',
            },
        },
        rules: {
            'sw-core-rules/require-explicit-emits': 'error',
            'sw-core-rules/enforce-async-component-registers': 'error',
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
                    'emits',
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
            'vue/no-deprecated-destroyed-lifecycle': 'error',
            'vue/no-deprecated-events-api': 'error',
            'vue/require-slots-as-functions': 'error',
            'vue/no-deprecated-props-default-this': 'error',
            'sw-deprecation-rules/no-compat-conditions': ['error'],
            'sw-deprecation-rules/no-empty-listeners': ['error', 'enableFix'],
            'sw-deprecation-rules/no-vue-options-api': 'off',
        },
    },

    // Twig template files: Vue parser + twig-vue processor
    {
        files: ['src/**/*.html.twig', 'test/eslint/**/*.html.twig'],
        languageOptions: {
            parser: vueParser,
            parserOptions: {
                sourceType: 'module',
            },
        },
        processor: {
            meta: { name: 'twig-vue', version: '1.0.0' },
            ...twigVue.processors['twig-vue'],
        },
        plugins: {
            'vuejs-accessibility': vuejsAccessibility,
        },
        rules: {
            ...Object.fromEntries(
                Object.entries(vuejsAccessibility.configs['flat/recommended'][1].rules)
                    .map(([rule]) => [rule, 'warn']),
            ),
            'no-warning-comments': ['error', { location: 'anywhere' }],
            'vue/component-name-in-template-casing': ['error', 'kebab-case', {
                registeredComponentsOnly: true,
                ignores: [],
            }],
            'vue/html-indent': ['error', 4, { baseIndent: 0 }],
            'no-multiple-empty-lines': ['error', { max: 1 }],
            'vue/attribute-hyphenation': 'error',
            'vue/multiline-html-element-content-newline': 'off',
            'vue/html-self-closing': ['error', {
                html: { void: 'never', normal: 'never', component: 'always' },
                svg: 'always',
                math: 'always',
            }],
            'vue/no-parsing-error': ['error', { 'nested-comment': false }],
            'vue/valid-v-slot': ['error', { allowModifiers: true }],
            'vue/v-slot-style': 'error',
            'vue/attributes-order': 'error',
            'vue/no-deprecated-slot-attribute': ['error'],
            'vue/no-deprecated-slot-scope-attribute': ['error'],
            'sw-deprecation-rules/no-deprecated-components': ['error', {
                fix: true,
                activatedComponents: [
                    'sw-button',
                    'sw-colorpicker',
                    'sw-alert',
                    'sw-progress-bar',
                    'sw-button',
                    'sw-text-field',
                    'sw-email-field',
                    'sw-card',
                    'sw-switch-field',
                    'sw-textarea-field',
                    'sw-icon',
                    'sw-url-field',
                    'sw-datepicker',
                    'sw-select-field',
                    'sw-checkbox-field',
                    'sw-number-field',
                    'sw-password-field',
                ],
            }],
            'sw-deprecation-rules/no-deprecated-component-usage': ['error', 'enableFix'],
            'vue/no-useless-template-attributes': 'error',
            'vue/no-lone-template': 'error',

            'eol-last': 'off',
            'max-len': 'off',
            'vue/no-multiple-template-root': 'off',
            'vue/no-unused-vars': 'off',
            'vue/no-template-shadow': 'off',
            'vue/no-v-html': 'off',
            'vue/valid-template-root': 'off',
            'vue/no-v-model-argument': 'off',
            'vue/no-v-for-template-key': 'off',
            'vue/html-closing-bracket-newline': 'error',
            'vue/no-v-for-template-key-on-child': 'error',
            'vue/no-deprecated-filter': 'error',
            'vue/no-deprecated-dollar-listeners-api': 'error',
            'vue/no-deprecated-dollar-scopedslots-api': 'error',
            'vue/no-deprecated-v-on-native-modifier': 'error',
            'vuejs-accessibility/media-has-caption': 'off',
        },
    },

    // Twig files with known false positives or pre-existing patterns.
    // Inline eslint-disable comments don't work in twig files due to twig-vue processor line shifting.
    {
        files: [
            'src/**/sw-grouped-single-select/sw-grouped-single-select.html.twig',
            'src/**/sw-sidebar-collapse/sw-sidebar-collapse.html.twig',
            'src/**/sw-cms-create/sw-cms-create.html.twig',
            'src/**/sw-mail-header-footer-create/sw-mail-header-footer-create.html.twig',
            'src/**/sw-mail-template-create/sw-mail-template-create.html.twig',
            'src/**/sw-property-create/sw-property-create.html.twig',
            'src/**/sw-sales-channel-create/sw-sales-channel-create.html.twig',
            'src/**/sw-settings-country-create/sw-settings-country-create.html.twig',
            'src/**/sw-settings-listing-option-create/sw-settings-listing-option-create.html.twig',
            'src/**/sw-settings-number-range-create/sw-settings-number-range-create.html.twig',
            'src/**/sw-settings-payment-create/sw-settings-payment-create.html.twig',
        ],
        rules: {
            'vue/valid-v-slot': 'off',
        },
    },
    {
        files: ['src/**/sw-sidebar-media-item/sw-sidebar-media-item.html.twig'],
        rules: {
            'vue/no-use-v-if-with-v-for': 'off',
        },
    },

    // Test files
    {
        files: ['**/*.spec.js', '**/*.spec.ts', '**/fixtures/*.js', 'test/**/*.js', 'test/**/*.ts'],
        ...jestPlugin.configs['flat/recommended'],
        languageOptions: {
            ...jestPlugin.configs['flat/recommended'].languageOptions,
            globals: {
                ...jestPlugin.configs['flat/recommended'].languageOptions?.globals,
                ...globals.node,
                ...globals.commonjs,
            },
        },
        rules: {
            ...jestPlugin.configs['flat/recommended'].rules,
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
            'jest/valid-expect': ['error', { maxArgs: 2 }],
            'jest/no-disabled-tests': 'error',
            'func-names': 'off',
        },
    },

    // TypeScript files
    ...tseslint.configs.recommendedTypeChecked.map(config => ({
        ...config,
        files: ['**/*.ts', '**/*.tsx'],
    })),
    {
        files: ['**/*.ts', '**/*.tsx'],
        languageOptions: {
            parserOptions: {
                tsconfigRootDir: __dirname,
                project: ['./tsconfig.json'],
            },
        },
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
            '@typescript-eslint/no-misused-spread': 'error',
            'import/extensions': [
                'error',
                'ignorePackages',
                { js: 'never', jsx: 'never', ts: 'never', tsx: 'never' },
            ],
            'no-void': 'off',
            'no-unused-vars': 'off',
            '@typescript-eslint/no-unused-vars': ['error', { caughtErrors: 'all', caughtErrorsIgnorePattern: '^_' }],
            '@typescript-eslint/prefer-promise-reject-errors': 'warn',
            'sw-deprecation-rules/no-compat-conditions': ['error'],
            'sw-core-rules/enforce-async-component-registers': 'error',
            'sw-deprecation-rules/no-empty-listeners': ['error', 'enableFix'],
            'sw-deprecation-rules/no-vue-options-api': 'off',
        },
    },
    {
        ...prettier,
        files: ['**/*.js', '**/*.ts', '**/*.tsx', '**/*.vue'],
    },
];
