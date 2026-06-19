/**
 * @sw-package framework
 *
 * Local custom-plugin ESLint bridge for Shopware Administration Vue SFCs.
 */

import pluginVue from '../src/Administration/Resources/app/administration/node_modules/eslint-plugin-vue/lib/index.js';
import tsParser from '../src/Administration/Resources/app/administration/node_modules/@typescript-eslint/parser/dist/index.js';
import swCoreRules from '../src/Administration/Resources/app/administration/eslint-rules/core-rules/index.js';

const vueParserSetup = pluginVue.configs['flat/recommended'].find((config) => {
    return config.name === 'vue/base/setup-for-vue';
});

export default [
    {
        files: ['plugins/**/*.vue'],
        plugins: {
            'sw-core-rules': swCoreRules,
        },
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            parser: vueParserSetup.languageOptions.parser,
            parserOptions: {
                parser: {
                    ts: tsParser,
                    tsx: tsParser,
                },
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
            'sw-core-rules/valid-shopware-setup': 'error',
        },
    },
];
