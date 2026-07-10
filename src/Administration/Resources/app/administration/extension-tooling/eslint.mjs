/**
 * @sw-package framework
 *
 * Portable flat-config factory for Administration extensions. Dependencies
 * resolve from the installed Administration so default extensions do not need
 * their own package.json or duplicate lint dependencies.
 */

import eslintJs from '@eslint/js';
import globals from 'globals';
import pluginVue from 'eslint-plugin-vue';
import tseslint from 'typescript-eslint';
import swDeprecationRules from '../eslint-rules/deprecation-rules/index.js';
import swPluginRules from '../eslint-rules/plugin-rules/index.js';
import { legacyTwigConfig } from './legacy-twig.mjs';

const typescriptFiles = [
    '**/*.ts',
    '**/*.tsx',
];
const vueFiles = ['**/*.vue'];
const templateFiles = [
    ...vueFiles,
    '**/*.html.twig',
    '**/*.vue.twig',
];
const typedRules = Object.assign({}, ...tseslint.configs.recommendedTypeChecked.map((config) => config.rules ?? {}));
const vueParserSetup = pluginVue.configs['flat/recommended'].find((config) => config.name === 'vue/base/setup-for-vue');
const vueParser = vueParserSetup.languageOptions.parser;

export function shopwareAdminExtension(options = {}) {
    const { legacyTwig = true, ignores = [], tsconfigRootDir = process.cwd() } = options;

    const config = [
        {
            name: 'shopware/admin-extension/ignores',
            ignores: [
                '**/node_modules/**',
                '**/Resources/public/**',
                '**/dist/**',
                '**/build/**',
                ...ignores,
            ],
        },
        {
            ...eslintJs.configs.recommended,
            name: 'shopware/admin-extension/javascript',
            files: [
                '**/*.js',
                '**/*.mjs',
                '**/*.cjs',
            ],
        },
        ...tseslint.configs.recommendedTypeChecked.map((typescriptConfig, index) => ({
            ...typescriptConfig,
            name: 'shopware/admin-extension/typescript-' + index,
            files: typescriptFiles,
            languageOptions: {
                ...typescriptConfig.languageOptions,
                parserOptions: {
                    ...typescriptConfig.languageOptions?.parserOptions,
                    projectService: true,
                    tsconfigRootDir,
                },
            },
        })),
        ...pluginVue.configs['flat/recommended'].map((vueConfig, index) => ({
            ...vueConfig,
            name: 'shopware/admin-extension/vue-' + index,
            files: vueFiles,
        })),
        {
            name: 'shopware/admin-extension/vue-typescript',
            files: vueFiles,
            languageOptions: {
                parser: vueParser,
                parserOptions: {
                    parser: tseslint.parser,
                    projectService: true,
                    extraFileExtensions: ['.vue'],
                    tsconfigRootDir,
                },
            },
            plugins: {
                '@typescript-eslint': tseslint.plugin,
            },
            rules: {
                ...typedRules,
                'vue/html-indent': [
                    'error',
                    4,
                    { baseIndent: 1 },
                ],
            },
        },
        {
            name: 'shopware/admin-extension/runtime-contract',
            files: [
                '**/*.js',
                '**/*.mjs',
                '**/*.cjs',
                ...typescriptFiles,
                ...vueFiles,
            ],
            languageOptions: {
                ecmaVersion: 'latest',
                sourceType: 'module',
                globals: {
                    ...globals.browser,
                    Shopware: 'readonly',
                },
            },
            plugins: {
                'plugin-rules': swPluginRules,
            },
            rules: {
                'plugin-rules/no-src-imports': 'error',
                'no-restricted-imports': [
                    'error',
                    {
                        patterns: [
                            {
                                group: [
                                    'src',
                                    'src/*',
                                    '@administration/*',
                                    '**/src/Administration/Resources/app/administration/src/*',
                                ],
                                message: 'Use the global Shopware object instead of importing Administration internals.',
                            },
                        ],
                    },
                ],
            },
        },
        {
            name: 'shopware/admin-extension/template-deprecations',
            files: templateFiles,
            plugins: {
                'sw-deprecation-rules': swDeprecationRules,
            },
            rules: {
                'sw-deprecation-rules/no-deprecated-components': 'error',
                'sw-deprecation-rules/no-deprecated-component-usage': 'error',
            },
        },
    ];

    if (legacyTwig) {
        config.push(...legacyTwigConfig());
    }

    return config;
}

export { pluginVue, swDeprecationRules, swPluginRules, tseslint };

export default shopwareAdminExtension();
