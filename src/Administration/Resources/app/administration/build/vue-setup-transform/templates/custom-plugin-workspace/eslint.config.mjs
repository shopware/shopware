/**
 * @sw-package framework
 *
 * Local custom-plugin ESLint bridge for Shopware Administration Vue SFCs.
 */

import pluginVue from '../src/Administration/Resources/app/administration/node_modules/eslint-plugin-vue/lib/index.js';
import tsParser from '../src/Administration/Resources/app/administration/node_modules/@typescript-eslint/parser/dist/index.js';
import swCoreRules from '../src/Administration/Resources/app/administration/eslint-rules/core-rules/index.js';

// `vue/base/setup-for-vue` is eslint-plugin-vue's own internal config name, not a documented entry
// point, so a major bump can rename it away. Fail with the reason instead of letting the next line
// dereference `undefined` and surface as a bare TypeError in the editor. See README.md.
const vueParserSetup = pluginVue.configs['flat/recommended'].find((config) => {
    return config.name === 'vue/base/setup-for-vue';
});

if (!vueParserSetup) {
    throw new Error(
        'eslint-plugin-vue no longer exposes the internal "vue/base/setup-for-vue" config this template ' +
            'borrows its parser from. Update custom/eslint.config.mjs from the current template in ' +
            'src/Administration/Resources/app/administration/build/vue-setup-transform/templates/custom-plugin-workspace.',
    );
}

export default [
    {
        files: ['plugins/**/*.vue'],
        plugins: {
            'sw-core-rules': swCoreRules,
            vue: pluginVue,
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
            // The filename becomes the component's template tag and its public override target, so a
            // non-kebab name (`Bad_Name.vue`, single-word `widget.vue`) registers a working but
            // unconventional component. The Administration enforces this at `error`; a plugin workspace
            // needs the same guard, since nothing else reports the name a plugin author's file will claim.
            'sw-core-rules/native-setup-filename': 'error',
            // The prop/setup name collision is the one native-setup mistake with silent consequences: the
            // runtime strips declared prop keys from returned state, so the binding is deleted and the
            // template reads `undefined` - no build error, no crash. The transform cannot catch it (a prop
            // type can be a named type it cannot resolve), so this rule is the only guard, and a plugin
            // workspace needs it just as much as the Administration does.
            'vue/no-dupe-keys': 'error',
        },
    },
];
