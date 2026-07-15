/**
 * @sw-package framework
 *
 * Transitional Twig linting for extensions that still use the legacy
 * component factory with `.html.twig` templates. New SFC-based extensions
 * can disable this preset via `shopwareAdminExtension({ legacyTwig: false })`.
 */

import pluginVue from 'eslint-plugin-vue';
import twigVue from 'eslint-plugin-twig-vue';

const vueParserSetup = pluginVue.configs['flat/recommended'].find((config) => config.name === 'vue/base/setup-for-vue');
const vueParser = vueParserSetup.languageOptions.parser;

export const defaultTwigFiles = [
    '**/*.html.twig',
    '**/*.vue.twig',
];

export function legacyTwigConfig(files = defaultTwigFiles) {
    return [
        ...pluginVue.configs['flat/recommended'].map((config) => ({
            ...config,
            files,
        })),
        {
            name: 'shopware/admin-extension/legacy-twig',
            files,
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
                // Pure formatting rules stay warnings on legacy templates:
                // measured as the dominant noise class on existing plugins,
                // while functional and deprecation rules remain errors.
                'vue/attribute-hyphenation': 'warn',
                'vue/attributes-order': 'warn',
                'vue/html-closing-bracket-newline': 'warn',
                'vue/html-closing-bracket-spacing': 'warn',
                'vue/html-self-closing': 'warn',
                'vue/max-attributes-per-line': 'warn',
                'vue/singleline-html-element-content-newline': 'warn',
            },
        },
    ];
}

export default legacyTwigConfig();
