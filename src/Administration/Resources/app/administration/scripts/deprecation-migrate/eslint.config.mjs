import globals from 'globals';
import tseslint from 'typescript-eslint';
import vueParser from 'vue-eslint-parser';
import swCoreRules from 'eslint-plugin-sw-core-rules';
import swDeprecationRules from 'eslint-plugin-sw-deprecation-rules';
import twigVue from 'eslint-plugin-twig-vue';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { SETTINGS_KEY } = require('../../eslint-rules/deprecation-rules/registry/run-level');

// `deprecation:migrate --level` passes the run level of the deprecation rules
const settings = {
    [SETTINGS_KEY]: { level: process.env.SHOPWARE_ADMIN_DEPRECATION_LEVEL ?? 'safe' },
};

const plugins = {
    'sw-core-rules': swCoreRules,
    'sw-deprecation-rules': swDeprecationRules,
};

const scriptDeprecationRules = {
    'sw-core-rules/no-tc-translation': 'error',
    'sw-deprecation-rules/no-deprecated-snippet-keys': 'error',
    'sw-deprecation-rules/no-deprecated-js-api': 'error',
    'sw-deprecation-rules/no-deprecated-api-usage': 'error',
    'sw-deprecation-rules/no-deprecated-assets': 'error',
};

const templateDeprecationRules = {
    ...scriptDeprecationRules,
    'sw-deprecation-rules/no-deprecated-components': 'error',
    'sw-deprecation-rules/no-deprecated-component-usage': 'error',
    'sw-deprecation-rules/no-deprecated-component-option-usage': 'error',
    'sw-deprecation-rules/no-deprecated-template-events': 'error',
    'sw-deprecation-rules/no-deprecated-template-blocks': 'error',
};

const languageOptions = {
    ecmaVersion: 'latest',
    sourceType: 'module',
    globals: {
        ...globals.browser,
        ...globals.node,
        ...globals.jest,
        Shopware: true,
        VueJS: true,
    },
};

const vueLanguageOptions = {
    ...languageOptions,
    parser: vueParser,
    parserOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
        parser: tseslint.parser,
    },
};

export default [
    {
        ignores: [
            // The registry defines the deprecated keys and APIs it migrates.
            'src/app/deprecation-registry/**',
            '**/*.fixtures.js',
        ],
    },
    {
        files: [
            '**/*.js',
            '**/*.vue',
        ],
        plugins,
        settings,
        languageOptions: vueLanguageOptions,
        rules: templateDeprecationRules,
    },
    {
        files: ['src/**/*.html.twig'],
        plugins,
        settings,
        languageOptions: vueLanguageOptions,
        processor: {
            meta: { name: 'twig-vue', version: '1.0.0' },
            ...twigVue.processors['twig-vue'],
        },
        rules: templateDeprecationRules,
    },
    {
        files: ['**/*.ts'],
        plugins,
        settings,
        languageOptions: {
            ...languageOptions,
            parser: tseslint.parser,
        },
        rules: scriptDeprecationRules,
    },
];
