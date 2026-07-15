/**
 * @sw-package framework
 *
 * Portable flat-config factory for Administration extensions. All parsers,
 * plugins, and rule packages resolve from the installed Administration's own
 * node_modules, so extensions never install lint dependencies themselves.
 */

import eslintJs from '@eslint/js';
import globals from 'globals';
import pluginVue from 'eslint-plugin-vue';
import tseslint from 'typescript-eslint';
import swDeprecationRules from 'eslint-plugin-sw-deprecation-rules';
import swPluginRules from 'eslint-plugin-plugin-rules';
import { legacyTwigConfig, defaultTwigFiles } from './legacy-twig.mjs';

const javascriptFilePatterns = [
    '**/*.js',
    '**/*.mjs',
    '**/*.cjs',
];
const typescriptFilePatterns = [
    '**/*.ts',
    '**/*.tsx',
];
const vueFilePatterns = ['**/*.vue'];
const templateFilePatterns = [
    ...vueFilePatterns,
    ...defaultTwigFiles,
];
const typedRules = Object.assign({}, ...tseslint.configs.recommendedTypeChecked.map((config) => config.rules ?? {}));
const vueParserSetup = pluginVue.configs['flat/recommended'].find((config) => config.name === 'vue/base/setup-for-vue');
const vueParser = vueParserSetup.languageOptions.parser;

/**
 * Creates the shared flat config for Administration extensions.
 *
 * - `tsconfigRootDir` (required): the directory ESLint resolves tsconfigs
 *   from — the project root for generated root configs, the plugin's admin
 *   folder for shim-based configs.
 * - `extensionRoots`: relative paths (from the config file location) that
 *   scope every file glob. The generated root config passes the discovered
 *   extension sources so the Twig-Vue processor never touches files outside
 *   Administration extensions (e.g. real server-side Twig templates).
 * - `legacyTwig`: lint `.html.twig` component templates through the Twig-Vue
 *   processor. Disable for SFC-only extensions.
 * - `internalApiSeverity`: severity for the API-boundary rules (usage of
 *   `@deprecated` members). Internal plugins that intentionally consume
 *   internal APIs may lower this in their own config.
 * - `ignores`: additional global ignore patterns.
 */
export function shopwareAdminExtension(options = {}) {
    const { tsconfigRootDir, extensionRoots = [], legacyTwig = true, internalApiSeverity = 'error', ignores = [] } = options;

    if (!tsconfigRootDir) {
        throw new Error(
            'shopwareAdminExtension requires the "tsconfigRootDir" option. ' +
                'Pass the directory that contains your eslint config, e.g. ' +
                'shopwareAdminExtension({ tsconfigRootDir: import.meta.dirname }).',
        );
    }

    const scope = (patterns) => {
        if (extensionRoots.length === 0) {
            return patterns;
        }

        return extensionRoots.flatMap((extensionRoot) =>
            patterns.map((pattern) => `${extensionRoot.replace(/\/+$/, '')}/${pattern}`),
        );
    };

    const config = [
        {
            name: 'shopware/admin-extension/ignores',
            ignores: [
                '**/node_modules/**',
                '**/Resources/public/**',
                '**/dist/**',
                '**/vendor/**',
                ...ignores,
            ],
        },
        {
            ...eslintJs.configs.recommended,
            name: 'shopware/admin-extension/javascript',
            files: scope(javascriptFilePatterns),
        },
        ...tseslint.configs.recommendedTypeChecked.map((typescriptConfig, index) => ({
            ...typescriptConfig,
            name: `shopware/admin-extension/typescript-${index}`,
            files: scope(typescriptFilePatterns),
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
            name: `shopware/admin-extension/vue-${index}`,
            files: scope(vueFilePatterns),
        })),
        {
            name: 'shopware/admin-extension/vue-typescript',
            files: scope(vueFilePatterns),
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
            files: scope([
                ...javascriptFilePatterns,
                ...typescriptFilePatterns,
                ...vueFilePatterns,
            ]),
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
            name: 'shopware/admin-extension/api-boundary',
            files: scope([
                ...typescriptFilePatterns,
                ...vueFilePatterns,
            ]),
            rules: {
                '@typescript-eslint/no-deprecated': internalApiSeverity,
            },
        },
        {
            name: 'shopware/admin-extension/template-deprecations',
            files: scope(templateFilePatterns),
            plugins: {
                'sw-deprecation-rules': swDeprecationRules,
            },
            rules: {
                'sw-deprecation-rules/no-deprecated-components': internalApiSeverity,
                'sw-deprecation-rules/no-deprecated-component-usage': internalApiSeverity,
            },
        },
    ];

    if (legacyTwig) {
        config.push(...legacyTwigConfig(scope(defaultTwigFiles)));
    }

    return config;
}

export { legacyTwigConfig, pluginVue, swDeprecationRules, swPluginRules, tseslint };

export default shopwareAdminExtension;
