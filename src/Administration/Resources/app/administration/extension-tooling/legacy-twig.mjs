/**
 * @sw-package framework
 *
 * Transitional Twig linting for extensions that still use the legacy
 * component factory. New SFC extensions can disable this preset.
 */

import pluginVue from 'eslint-plugin-vue';
import twigVue from 'eslint-plugin-twig-vue';

const vueParserSetup = pluginVue.configs['flat/recommended'].find((config) => config.name === 'vue/base/setup-for-vue');
const vueParser = vueParserSetup.languageOptions.parser;
const twigFiles = [
    '**/*.html.twig',
    '**/*.vue.twig',
];

export function legacyTwigConfig() {
    return [
        ...pluginVue.configs['flat/recommended'].map((config) => ({
            ...config,
            files: twigFiles,
        })),
        {
            name: 'shopware/admin-extension/legacy-twig',
            files: twigFiles,
            languageOptions: {
                parser: vueParser,
            },
            plugins: {
                twigVue,
            },
            processor: 'twigVue/twig-vue',
            rules: {
                'vue/html-indent': 'off',
                'vue/multiline-html-element-content-newline': 'off',
                'vue/no-multiple-template-root': 'off',
                'vue/no-template-shadow': 'off',
                'vue/no-unused-vars': 'off',
                'vue/no-v-html': 'off',
                'vue/valid-template-root': 'off',
            },
        },
    ];
}

export default legacyTwigConfig();
