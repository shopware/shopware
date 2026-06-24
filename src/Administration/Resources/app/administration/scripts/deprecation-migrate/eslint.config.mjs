import globals from 'globals';
import tseslint from 'typescript-eslint';
import vueParser from 'vue-eslint-parser';
import swCoreRules from 'eslint-plugin-sw-core-rules';
import swDeprecationRules from 'eslint-plugin-sw-deprecation-rules';
import twigVue from 'eslint-plugin-twig-vue';

const plugins = {
    'sw-core-rules': swCoreRules,
    'sw-deprecation-rules': swDeprecationRules,
};

const templateDeprecationRules = {
    'sw-core-rules/no-tc-translation': 'error',
    'sw-deprecation-rules/no-deprecated-components': [
        'error',
        { fix: true },
    ],
    'sw-deprecation-rules/no-deprecated-component-usage': [
        'error',
        'enableFix',
    ],
    'sw-deprecation-rules/no-deprecated-template-events': [
        'error',
        'enableFix',
    ],
    'sw-deprecation-rules/no-deprecated-template-blocks': [
        'error',
        'enableFix',
    ],
    'sw-deprecation-rules/no-deprecated-snippet-keys': 'error',
    'sw-deprecation-rules/no-deprecated-js-api': [
        'error',
        'enableFix',
    ],
};

const scriptDeprecationRules = {
    'sw-core-rules/no-tc-translation': 'error',
    'sw-deprecation-rules/no-deprecated-snippet-keys': 'error',
    'sw-deprecation-rules/no-deprecated-js-api': [
        'error',
        'enableFix',
    ],
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

export default [
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
    {
        files: [
            '**/*.js',
            '**/*.vue',
        ],
        plugins,
        languageOptions: {
            ...languageOptions,
            parser: vueParser,
            parserOptions: {
                ecmaVersion: 'latest',
                sourceType: 'module',
                parser: tseslint.parser,
            },
        },
        rules: templateDeprecationRules,
    },
    {
        files: [
            'src/**/*.html.twig',
            'test/eslint/**/*.html.twig',
        ],
        plugins,
        languageOptions: {
            ...languageOptions,
            parser: vueParser,
            parserOptions: {
                ecmaVersion: 'latest',
                sourceType: 'module',
                parser: tseslint.parser,
            },
        },
        processor: {
            meta: { name: 'twig-vue', version: '1.0.0' },
            ...twigVue.processors['twig-vue'],
        },
        rules: templateDeprecationRules,
    },
    {
        files: ['**/*.ts'],
        plugins,
        languageOptions: {
            ...languageOptions,
            parser: tseslint.parser,
        },
        rules: scriptDeprecationRules,
    },
];
