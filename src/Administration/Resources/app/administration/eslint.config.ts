/**
 * @sw-package admin
 */

/**
 * ESLint flat-config entrypoint for Administration and Storefront administration sources.
 *
 * The config keeps legacy Shopware rule behavior while running on ESLint 9, including compatibility
 * patches for plugins that still expose pre-flat-config rule metadata.
 */

import path from 'node:path';
import { fileURLToPath } from 'node:url';
import js from '@eslint/js';
import tseslint from 'typescript-eslint';
import { fixupPluginRules } from '@eslint/compat';
import importX from 'eslint-plugin-import-x';
import jestPlugin from 'eslint-plugin-jest';
import prettier from 'eslint-config-prettier';
import globals from 'globals';
import inclusiveLanguage from 'eslint-plugin-inclusive-language';
import fileProgress from 'eslint-plugin-file-progress';
import filenameRules from 'eslint-plugin-filename-rules';
import vuejsAccessibility from 'eslint-plugin-vuejs-accessibility';
import listeners from 'eslint-plugin-listeners';
import json from '@eslint/json';

import swTestRules from 'eslint-plugin-sw-test-rules';
import twigVue from 'eslint-plugin-twig-vue';
// The factory is the single source of the base lint setup for admin AND
// extensions. pluginVue/swDeprecationRules must be the factory's own objects:
// ESLint refuses to redefine a plugin key with a different object reference,
// and the factory blocks register these plugins for overlapping files.
import shopwareAdminExtension, { pluginVue, swCoreRules, swDeprecationRules } from './extension-tooling/eslint.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// eslint-plugin-filename-rules doesn't define meta.schema, which ESLint 9 treats
// as "no options allowed". Patch the rule to accept an option.
const filenameRulesPatched = {
    ...filenameRules,
    rules: Object.fromEntries(
        Object.entries(filenameRules.rules).map(
            ([
                name,
                rule,
            ]) => [
                name,
                {
                    ...rule,
                    meta: {
                        ...rule.meta,
                        schema: rule.meta?.schema ?? [
                            {
                                oneOf: [
                                    { type: 'string' },
                                    { type: 'object' },
                                ],
                            },
                        ],
                    },
                },
            ],
        ),
    ),
};

const vueParserSetup = pluginVue.configs['flat/recommended'].find((c) => c.name === 'vue/base/setup-for-vue');
const vueParser = vueParserSetup.languageOptions.parser;

/**
 * Shared rule policy applied to normal source files and TypeScript-specific overrides.
 *
 * Keep cross-cutting Shopware rules here so JS, TS, Vue, and Twig sections only override parser or
 * file-type-specific behavior.
 */
const baseRules = {
    'file-progress/activate': 0,
    'max-len': [
        'error',
        125,
        { ignoreRegExpLiterals: true },
    ],
    'import/no-useless-path-segments': 0,
    // `.vue` needs the explicit extension (js/ts/tsx don't): nothing resolves `./sw-thing` to
    // `./sw-thing.vue` - not TypeScript's `*.vue` shim, not Vite's default `resolve.extensions`.
    'import/extensions': [
        'error',
        'ignorePackages',
        {
            js: 'never',
            ts: 'never',
            tsx: 'never',
            vue: 'always',
        },
    ],
    'no-console': [
        'error',
        {
            allow: [
                'warn',
                'error',
            ],
        },
    ],
    'no-warning-comments': [
        'error',
        { location: 'anywhere' },
    ],
    'inclusive-language/use-inclusive-words': 'error',
    'comma-dangle': [
        'error',
        'always-multiline',
    ],
    'sw-core-rules/require-position-identifier': [
        'error',
        {
            components: [
                'sw-button',
                'sw-card',
                'sw-tabs',
                'sw-extension-component-section',
            ],
        },
    ],
    'sw-core-rules/require-package-annotation': ['error'],
    'sw-core-rules/no-tc-translation': 'error',
    'sw-core-rules/valid-shopware-setup': 'error',
    // Must be an error: this is the only check on a directory-derived component name, and `npm run lint`
    // has no `--max-warnings`, so at warning level `Bad_Dir/index.vue` would pass CI.
    'sw-core-rules/native-setup-filename': 'error',
    'sw-deprecation-rules/private-feature-declarations': 'error',
    'no-restricted-exports': 'off',
    'filename-rules/match': [
        2,
        /^.*(?:\.js|\.ts|\.vue|\.html|\.html\.twig)$/,
    ],
    'vue/multi-word-component-names': [
        'error',
        {
            // Support for our `sw-some-component/index.vue` convention
            ignores: [
                'index.html',
                'index',
            ],
        },
    ],
    'func-names': 'off',
    'listeners/no-missing-remove-event-listener': 'error',
    'listeners/matching-remove-event-listener': 'error',
    'listeners/no-inline-function-event-listener': 'error',

    // From @shopware-ag/eslint-config-base (airbnb-base overrides)
    'no-multiple-empty-lines': [
        'error',
        { max: 2, maxEOF: 1 },
    ],
    'arrow-parens': 0,
    'arrow-body-style': 0,
    'generator-star-spacing': 0,
    'no-debugger': process.env.NODE_ENV === 'production' ? 2 : 0,
    indent: [
        'error',
        4,
        { SwitchCase: 1 },
    ],
    'no-use-before-define': [
        'error',
        { functions: false },
    ],
    'no-param-reassign': 0,
    'linebreak-style': [
        'error',
        'unix',
    ],
    'object-shorthand': 0,
    'no-useless-escape': 0,
    'no-prototype-builtins': 0,
    'object-curly-newline': [
        'error',
        { consistent: true },
    ],
    'no-underscore-dangle': 0,
    'prefer-destructuring': [
        'off',
        { object: true, array: false },
    ],
    'operator-linebreak': 0,
    'import/no-cycle': 0,
    'class-methods-use-this': 0,
    'no-unused-vars': [
        'error',
        { vars: 'all', args: 'after-used', ignoreRestSiblings: true, caughtErrors: 'all', caughtErrorsIgnorePattern: '^_' },
    ],
    'vue/prefer-import-from-vue': 'off',
    'vue/one-component-per-file': 'off',
};

export default [
    // Global ignores (from .eslintignore)
    {
        ignores: [
            'build/*.js',
            'config/*.js',
            'eslint.config.ts',
            'jest.config.js',
            'jest.config.ts',
            'scripts/**/*',
            '!scripts/extensionTooling/',
            '!scripts/extensionTooling/**/*',
            '!scripts/codemods/',
            '!scripts/codemods/sfc-migration/',
            '!scripts/codemods/sfc-migration/**/*',
            // Codemod inputs are intentionally old-style Options API components.
            'scripts/codemods/sfc-migration/__fixtures__/**/*',
            // Declaration-only type surface; admin-types imports the gitignored
            // generated entity schema, and spec-types references jest, so both
            // must stay outside the admin's own typed-lint program.
            'extension-tooling/admin-types.d.ts',
            'extension-tooling/spec-types.d.ts',
            'test/eslint/error-reference.html.twig',
            '**/*.spec.vue2.js',
            'build/vue-setup-transform/**/*.d.ts',
            '**/*.fixtures.js',
            // Hand-written declaration files under build/ sit outside the tsconfig program (a sibling
            // .ts of the same name shadows them), so the typed parser cannot resolve them.
            'build/**/*.d.ts',
        ],
    },

    { ...js.configs.recommended, ignores: ['**/*.json'] },

    // The shared extension factory supplies the typed-lint bootstrap
    // (tseslint recommendedTypeChecked via projectService) and the common rule
    // sets, so the admin and extension configs cannot drift apart. Host
    // options: the admin IS src (no src-import boundary), tracks deprecated
    // usage through sw-deprecation-rules instead of
    // @typescript-eslint/no-deprecated, type-checks its spec files, and runs
    // its own stricter twig pipeline below instead of the lenient legacy one.
    ...shopwareAdminExtension({
        tsconfigRootDir: __dirname,
        legacyTwig: false,
        srcImportBoundary: false,
        deprecatedApiSeverity: 'off',
        specFiles: 'typed',
    }),

    // Vue plugin setup (global) + parser for .vue files
    ...pluginVue.configs['flat/recommended']
        .filter((c) => c.name === 'vue/base/setup' || c.name === 'vue/base/setup-for-vue')
        .map((c) => ({ ...c, ignores: ['**/*.json'] })),
    // Vue rules scoped to JS, Vue, and Twig files only (not TS)
    ...pluginVue.configs['flat/recommended']
        .filter((c) => c.name !== 'vue/base/setup' && c.name !== 'vue/base/setup-for-vue')
        .map((c) => ({
            ...c,
            files: [
                '**/*.js',
                '**/*.vue',
                '**/*.html.twig',
            ],
        })),

    // Base config for all files
    {
        ignores: ['**/*.json'],
        plugins: {
            import: importX,
            'inclusive-language': fixupPluginRules(inclusiveLanguage),
            'file-progress': fixupPluginRules(fileProgress),
            'filename-rules': fixupPluginRules(filenameRulesPatched),
            // Deliberately not fixup-wrapped: the wrapper would be a second
            // object under the keys the factory already registers, and the
            // rules only use context APIs that still exist in ESLint 9.
            'sw-core-rules': swCoreRules,
            'sw-deprecation-rules': swDeprecationRules,
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
                            extensions: [
                                '.js',
                                '.ts',
                                '.vue',
                                '.json',
                                '.less',
                                '.twig',
                            ],
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

    {
        files: ['**/*.vue'],
        languageOptions: {
            parser: vueParser,
            parserOptions: {
                parser: {
                    ts: tseslint.parser,
                    tsx: tseslint.parser,
                },
                extraFileExtensions: ['.vue'],
                sourceType: 'module',
                // The only .vue files in the admin sources are the jest-transform
                // fixtures under app/adapter/_mocks_, and the admin tsconfig does
                // not pull them into its program (.vue is not a TS-resolved
                // extension). Keep them out of the project service so the factory's
                // type-aware .vue block has nothing to resolve them against and
                // does not error. Extensions carry their own typed .vue programs
                // and get the full type-aware coverage through that same block.
                projectService: false,
            },
            globals: {
                swDefinePublic: 'readonly',
                swDefineOverride: 'readonly',
                useSwPreviousState: 'readonly',
                useSwProps: 'readonly',
                useSwContext: 'readonly',
            },
        },
        rules: {
            // No type program backs these fixtures, so the type-aware rules the
            // factory turns on for .vue would throw "requires type information" —
            // switch the whole type-checked set back off here.
            ...tseslint.configs.disableTypeChecked.rules,
            // Same reason: without a program, no-unused-vars cannot see the
            // fixtures' bindings reliably, so leave unused-var coverage to the
            // factory's typed extension path.
            'no-unused-vars': 'off',
            '@typescript-eslint/no-unused-vars': 'off',
            // If a binding shares the same name as a prop, the binding gets silently undefined. Erroring in ESLint will make that issue loud in most cases (not for imported prop types)
            'vue/no-dupe-keys': 'error',
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
            'vue/component-definition-name-casing': [
                'error',
                'kebab-case',
            ],
            'vue/no-boolean-default': [
                'error',
                'default-false',
            ],
            'vue/order-in-components': [
                'error',
                {
                    order: [
                        'el',
                        'name',
                        'parent',
                        'functional',
                        [
                            'template',
                            'render',
                        ],
                        'inheritAttrs',
                        [
                            'provide',
                            'inject',
                        ],
                        'emits',
                        'extends',
                        'mixins',
                        'model',
                        [
                            'components',
                            'directives',
                            'filters',
                        ],
                        [
                            'props',
                            'propsData',
                        ],
                        'data',
                        'metaInfo',
                        'computed',
                        'watch',
                        'LIFECYCLE_HOOKS',
                        'methods',
                        [
                            'delimiters',
                            'comments',
                        ],
                        'renderError',
                    ],
                },
            ],
            'vue/no-deprecated-destroyed-lifecycle': 'error',
            'vue/no-deprecated-events-api': 'error',
            'vue/require-slots-as-functions': 'error',
            'vue/no-deprecated-props-default-this': 'error',
            'sw-deprecation-rules/no-compat-conditions': ['error'],
            'sw-deprecation-rules/no-empty-listeners': [
                'error',
                'enableFix',
            ],
            'sw-deprecation-rules/no-vue-options-api': 'off',
        },
    },

    // Twig template files: Vue parser + twig-vue processor
    {
        files: [
            'src/**/*.html.twig',
            'test/eslint/**/*.html.twig',
        ],
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
                Object.entries(vuejsAccessibility.configs['flat/recommended'][1].rules).map(([rule]) => [
                    rule,
                    'warn',
                ]),
            ),
            'no-warning-comments': [
                'error',
                { location: 'anywhere' },
            ],
            'vue/component-name-in-template-casing': [
                'error',
                'kebab-case',
                {
                    registeredComponentsOnly: true,
                    ignores: [],
                },
            ],
            'vue/html-indent': [
                'error',
                4,
                { baseIndent: 0 },
            ],
            'no-multiple-empty-lines': [
                'error',
                { max: 1 },
            ],
            'vue/attribute-hyphenation': 'error',
            'vue/multiline-html-element-content-newline': 'off',
            'vue/html-self-closing': [
                'error',
                {
                    html: { void: 'never', normal: 'never', component: 'always' },
                    svg: 'always',
                    math: 'always',
                },
            ],
            'vue/no-parsing-error': [
                'error',
                { 'nested-comment': false },
            ],
            'vue/valid-v-slot': [
                'error',
                { allowModifiers: true },
            ],
            'vue/v-slot-style': 'error',
            'vue/attributes-order': 'error',
            'vue/no-deprecated-slot-attribute': ['error'],
            'vue/no-deprecated-slot-scope-attribute': ['error'],
            'sw-deprecation-rules/no-deprecated-components': [
                'error',
                {
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
                },
            ],
            'sw-deprecation-rules/no-deprecated-component-usage': [
                'error',
                'enableFix',
            ],
            'sw-deprecation-rules/no-sw-tabs-usage': 'error',
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
    {
        files: ['src/**/sw-settings-rule-tree-item/sw-settings-rule-tree-item.html.twig'],
        rules: {
            'vue/no-multi-spaces': 'off',
        },
    },
    {
        // Mouse handlers have focus and keydown counterparts these rules do not recognise
        files: [
            'src/app/**/sw-admin-menu/sw-admin-menu.html.twig',
            'src/app/**/sw-admin-menu-item/sw-admin-menu-item.html.twig',
        ],
        rules: {
            'vuejs-accessibility/click-events-have-key-events': 'off',
            'vuejs-accessibility/mouse-events-have-key-events': 'off',
            'vuejs-accessibility/no-static-element-interactions': 'off',
        },
    },

    // Test files
    {
        files: [
            '**/*.spec.js',
            '**/*.spec.ts',
            '**/*.spec/*.js',
            '**/*.spec/*.ts',
            '**/fixtures/*.js',
            'test/**/*.js',
            'test/**/*.ts',
        ],
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
            'sw-test-rules/stabilize-feature-flag': [
                'error',
                {
                    // Handcrafted list of stabilized (shipped) feature flags; each is auto-removed from
                    // it.activeFeatureFlags activations. Add a flag here once its major has shipped.
                    stabilizedFlags: [
                        'v6.5.0.0',
                        'v6.6.0.0',
                        'v6.7.0.0',
                    ],
                },
            ],
            'max-len': 0,
            'sw-deprecation-rules/private-feature-declarations': 0,
            'jest/expect-expect': [
                'error',
                {
                    assertFunctionNames: [
                        'expect',
                        'expect*',
                    ],
                },
            ],
            'jest/no-standalone-expect': [
                'error',
                {
                    additionalTestBlockFunctions: [
                        'it.activeFeatureFlags',
                        'it.deprecated',
                    ],
                },
            ],
            'jest/no-duplicate-hooks': 'error',
            'jest/no-test-return-statement': 'error',
            'jest/prefer-hooks-in-order': 'error',
            'jest/prefer-hooks-on-top': 'error',
            'jest/prefer-to-be': 'error',
            'jest/require-top-level-describe': 'error',
            'jest/prefer-to-contain': 'error',
            'jest/prefer-to-have-length': 'error',
            'jest/consistent-test-it': [
                'error',
                { fn: 'it', withinDescribe: 'it' },
            ],
            'jest/valid-expect': [
                'error',
                { maxArgs: 2 },
            ],
            'jest/no-disabled-tests': 'error',
            'func-names': 'off',
        },
    },
    {
        files: [
            '**/*.spec.js',
            '**/*.spec.ts',
            '**/*.spec/*.spec.js',
            '**/*.spec/*.spec.ts',
        ],
        rules: {
            'sw-test-rules/test-file-max-lines-warning': [
                'warn',
                { max: 500 },
            ],
            'sw-test-rules/test-file-max-lines-error': [
                'error',
                { max: 1000 },
            ],
        },
    },

    // TypeScript rules on top of the factory's typed-lint bootstrap. The
    // factory already spreads tseslint recommendedTypeChecked with
    // projectService — `project` must not reappear anywhere: parserOptions
    // merge per key across flat configs, and typescript-estree throws when
    // both are set.
    {
        files: [
            '**/*.ts',
            '**/*.tsx',
        ],
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
            '@typescript-eslint/no-unused-vars': [
                'error',
                { caughtErrors: 'all', caughtErrorsIgnorePattern: '^_' },
            ],
            '@typescript-eslint/prefer-promise-reject-errors': 'warn',
            'sw-deprecation-rules/no-compat-conditions': ['error'],
            'sw-core-rules/enforce-async-component-registers': 'error',
            'sw-deprecation-rules/no-empty-listeners': [
                'error',
                'enableFix',
            ],
            'sw-deprecation-rules/no-vue-options-api': 'off',
        },
    },

    {
        files: ['build/vue-setup-transform/**/*.ts'],
        rules: {
            'sw-deprecation-rules/private-feature-declarations': 'off',
        },
    },
    {
        ...prettier,
        files: [
            '**/*.js',
            '**/*.ts',
            '**/*.tsx',
            '**/*.vue',
        ],
    },
    {
        files: [
            'extension-tooling/**/*.mjs',
            'scripts/extensionTooling/**/*.ts',
            'scripts/codemods/sfc-migration/**/*.ts',
        ],
        rules: {
            'filename-rules/match': 'off',
            'import/extensions': 'off',
            'no-console': 'off',
            'sw-deprecation-rules/private-feature-declarations': 'off',
        },
    },
    {
        files: ['scripts/codemods/sfc-migration/**/*.ts'],
        rules: {
            // The codemod emits and documents TODO(sfc-migration) markers by design.
            'no-warning-comments': 'off',
        },
    },

    // Snippet JSON files: parse as JSON and flag entries that duplicate a global.default translation
    {
        files: ['src/**/snippet/*.json'],
        language: 'json/json',
        plugins: {
            json,
            'sw-core-rules': swCoreRules,
        },
        rules: {
            'sw-core-rules/require-global-default-use': 'error',
        },
    },
];
