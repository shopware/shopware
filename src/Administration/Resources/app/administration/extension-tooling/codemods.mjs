/**
 * @sw-package framework
 *
 * Flat-config factory for `admin:upgrade-extensions`. It parses every file kind
 * an Administration extension can contain but enables only the codemod rules it
 * is given, so an upgrade run never applies the formatting or code-quality fixes
 * of the regular lint preset.
 */

import tseslint from 'typescript-eslint';
import twigVue from 'eslint-plugin-twig-vue';
import swCoreRules from 'eslint-plugin-sw-core-rules';
import swDeprecationRules from 'eslint-plugin-sw-deprecation-rules';
import { defaultTwigFiles, resolveVueParser } from './legacy-twig.mjs';

const scriptFiles = [
    '**/*.js',
    '**/*.mjs',
    '**/*.cjs',
    '**/*.ts',
    '**/*.tsx',
];
const vueFiles = ['**/*.vue'];

/**
 * Both lists hold `{ rule, options? }` entries.
 *
 * - `scriptRules`: run on JavaScript, TypeScript and the script part of `.vue` files.
 * - `templateRules`: run on `.vue` templates and legacy `.html.twig` templates. Template rules need
 *   the Vue parser services, so they must never reach a plain script file.
 */
export function shopwareAdminCodemods({ scriptRules = [], templateRules = [] } = {}) {
    const vueParser = resolveVueParser();
    const plugins = {
        'sw-core-rules': swCoreRules,
        'sw-deprecation-rules': swDeprecationRules,
    };
    const enable = (entries) =>
        Object.fromEntries(entries.map(({ rule, options }) => [rule, options ? ['error', options] : 'error']));

    return [
        {
            name: 'shopware/admin-codemods/ignores',
            ignores: [
                '**/node_modules/**',
                '**/Resources/public/**',
                '**/dist/**',
                '**/.shopware/**',
                '**/*.spec.js',
                '**/*.spec.mjs',
                '**/*.spec.cjs',
                '**/*.spec.ts',
                '**/*.spec.tsx',
            ],
        },
        {
            name: 'shopware/admin-codemods/linter-options',
            // An upgrade must not touch the extension's own disable comments for rules it does not run.
            linterOptions: { reportUnusedDisableDirectives: 'off' },
        },
        {
            name: 'shopware/admin-codemods/scripts',
            files: scriptFiles,
            languageOptions: { parser: tseslint.parser },
            plugins,
            rules: enable(scriptRules),
        },
        {
            name: 'shopware/admin-codemods/vue',
            files: vueFiles,
            languageOptions: {
                parser: vueParser,
                parserOptions: { parser: tseslint.parser, extraFileExtensions: ['.vue'] },
            },
            plugins,
            rules: enable([...scriptRules, ...templateRules]),
        },
        {
            name: 'shopware/admin-codemods/twig',
            files: defaultTwigFiles,
            languageOptions: { parser: vueParser },
            plugins: { ...plugins, twigVue },
            processor: 'twigVue/twig-vue',
            rules: enable(templateRules),
        },
    ];
}
