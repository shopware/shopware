/**
 * @sw-package framework
 *
 * Transitional Twig linting for extensions that still use the legacy
 * component factory with `.html.twig` templates. New SFC-based extensions
 * can disable this preset via `shopwareAdminExtension({ legacyTwig: false })`.
 */

import pluginVue from 'eslint-plugin-vue';
import twigVue from 'eslint-plugin-twig-vue';

/**
 * Borrows the parser eslint-plugin-vue configures in its own internal
 * `vue/base/setup-for-vue` config. That name is private layout, not a
 * documented entry point, so a major eslint-plugin-vue bump can rename it away.
 * Throw with the reason instead of dereferencing `undefined` and surfacing a
 * bare TypeError in the editor.
 */
export function resolveVueParser() {
    const vueParserSetup = pluginVue.configs['flat/recommended'].find((config) => config.name === 'vue/base/setup-for-vue');

    if (!vueParserSetup) {
        throw new Error(
            'eslint-plugin-vue no longer exposes the internal "vue/base/setup-for-vue" config the Administration ' +
                'extension tooling borrows its Vue parser from. A major eslint-plugin-vue bump likely renamed it — ' +
                'update the lookup in extension-tooling/legacy-twig.mjs.',
        );
    }

    return vueParserSetup.languageOptions.parser;
}

const vueParser = resolveVueParser();

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
