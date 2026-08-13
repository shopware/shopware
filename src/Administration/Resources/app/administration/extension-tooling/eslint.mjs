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
import swCoreRules from 'eslint-plugin-sw-core-rules';
import { legacyTwigConfig, defaultTwigFiles, resolveVueParser } from './legacy-twig.mjs';

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
const specFilePatterns = [
    '**/*.spec.ts',
    '**/*.spec.tsx',
    '**/*.spec.js',
];
const templateFilePatterns = [
    ...vueFilePatterns,
    ...defaultTwigFiles,
];
/** Extensions consume the Administration through the global Shopware object, never through its sources. */
const NO_ADMIN_INTERNALS_RULE = [
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
];
const typedRules = Object.assign({}, ...tseslint.configs.recommendedTypeChecked.map((config) => config.rules ?? {}));
const vueParser = resolveVueParser();

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
 * - `internalApiSeverity`: umbrella severity for the API-boundary rules
 *   (usage of `@deprecated` members). Internal plugins that intentionally
 *   consume internal APIs may lower this in their own config.
 * - `ignores`: additional global ignore patterns.
 *
 * Host options — the Administration's own config composes this factory too
 * (so the base rules cannot drift apart); these knobs decouple the pieces a
 * host needs to own itself. Extension defaults reproduce the umbrella knob:
 *
 * - `deprecatedApiSeverity`: severity of `@typescript-eslint/no-deprecated`
 *   alone (defaults to `internalApiSeverity`).
 * - `templateDeprecationSeverity`: severity of the `sw-deprecation-rules`
 *   template rules alone (defaults to `internalApiSeverity`). A host that
 *   needs rule *options* re-declares the entries in a later config block —
 *   flat-config rule entries replace wholesale.
 * - `srcImportBoundary`: `false` disables the "never import Administration
 *   internals" rules — only sensible for the Administration itself, which IS
 *   `src`.
 * - `specFiles`: `'untyped'` (default) parses spec files standalone with the
 *   type-checked rules off; `'typed'` omits that block entirely, for hosts
 *   whose tsconfig covers spec files with jest types.
 */
export function shopwareAdminExtension(options = {}) {
    const {
        tsconfigRootDir,
        extensionRoots = [],
        legacyTwig = true,
        internalApiSeverity = 'error',
        ignores = [],
        deprecatedApiSeverity = internalApiSeverity,
        templateDeprecationSeverity = internalApiSeverity,
        srcImportBoundary = true,
        specFiles = 'untyped',
    } = options;

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

    // No program covers spec files, so type-aware rules have nothing to resolve
    // them against: specs are parsed standalone with the jest globals available
    // and the type-checked rules switched off.
    const specFilesConfig = {
        ...tseslint.configs.disableTypeChecked,
        name: 'shopware/admin-extension/spec-files',
        files: scope(specFilePatterns),
        languageOptions: {
            ...tseslint.configs.disableTypeChecked.languageOptions,
            globals: { ...globals.jest },
        },
    };

    const config = [
        {
            name: 'shopware/admin-extension/ignores',
            ignores: [
                '**/node_modules/**',
                '**/Resources/public/**',
                '**/dist/**',
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
            name: 'shopware/admin-extension/native-setup',
            files: scope(vueFilePatterns),
            languageOptions: {
                // Compile-time macros the Shopware setup transform removes: they
                // are never real runtime values, so they are declared globals to
                // keep no-undef from flagging them.
                globals: {
                    swDefinePublic: 'readonly',
                    swDefineOverride: 'readonly',
                    useSwPreviousState: 'readonly',
                    useSwProps: 'readonly',
                    useSwContext: 'readonly',
                },
            },
            plugins: {
                'sw-core-rules': swCoreRules,
            },
            rules: {
                // Native-setup correctness guards. vue/no-dupe-keys — the third
                // native-setup guard — is already error via vue/essential.
                'sw-core-rules/valid-shopware-setup': 'error',
                'sw-core-rules/native-setup-filename': 'error',
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
                'plugin-rules/no-src-imports': srcImportBoundary ? 'error' : 'off',
                'no-restricted-imports': srcImportBoundary ? NO_ADMIN_INTERNALS_RULE : 'off',
            },
        },
        {
            name: 'shopware/admin-extension/api-boundary',
            files: scope([
                ...typescriptFilePatterns,
                ...vueFilePatterns,
            ]),
            rules: {
                '@typescript-eslint/no-deprecated': deprecatedApiSeverity,
            },
        },
        {
            name: 'shopware/admin-extension/template-deprecations',
            files: scope(templateFilePatterns),
            plugins: {
                'sw-deprecation-rules': swDeprecationRules,
            },
            rules: {
                'sw-deprecation-rules/no-deprecated-components': templateDeprecationSeverity,
                'sw-deprecation-rules/no-deprecated-component-usage': templateDeprecationSeverity,
            },
        },
        // TypeScript's project service cannot type-check `.vue` SFCs — it does
        // not run the Vue language plugin — so on a `.vue` it resolves the
        // script to `any`. That makes every type-aware rule useless there: the
        // `no-unsafe-*` family floods correct components with false positives,
        // and `@typescript-eslint/no-deprecated` cannot resolve a symbol to read
        // its `@deprecated` tag in the first place. They cannot be kept on
        // selectively either — with the type program off (needed to stop the
        // flood) any surviving type-aware rule throws "requires type
        // information". `vue-tsc` is the real type-checker for `.vue` (the check
        // runs it separately); the AST-based template rules (e.g.
        // sw-deprecation-rules for deprecated components) stay on via the blocks
        // above. Same reasoning as the spec-files block; placed last so it
        // overrides the type-aware rules the earlier `.vue` blocks turned on.
        {
            ...tseslint.configs.disableTypeChecked,
            name: 'shopware/admin-extension/vue-untyped',
            files: scope(vueFilePatterns),
        },
        {
            // The parser does not link `{{ }}` template interpolations back to
            // the `<script setup>` bindings they read (directive and attribute
            // usage is linked; interpolation is not — not even with
            // vue/script-setup-uses-vars), so no-unused-vars false-positives on
            // any binding used only in an interpolation — most of them. Nothing
            // separates that from a genuinely unused binding, so the rule is off
            // for `.vue`. This does drop unused-binding coverage there: the
            // editor (Volar) still greys unused setup bindings, but the tooling's
            // own check does not flag them, and vue-tsc cannot stand in because
            // the injected host type surface forbids enabling its
            // `noUnusedLocals`. Restoring it properly needs the same SFC type
            // support the type-aware rules await.
            name: 'shopware/admin-extension/vue-template-usage',
            files: scope(vueFilePatterns),
            rules: {
                'no-unused-vars': 'off',
                '@typescript-eslint/no-unused-vars': 'off',
            },
        },
        ...(specFiles === 'typed' ? [] : [specFilesConfig]),
    ];

    if (legacyTwig) {
        config.push(...legacyTwigConfig(scope(defaultTwigFiles)));
    }

    return config;
}

export { legacyTwigConfig, pluginVue, swCoreRules, swDeprecationRules, swPluginRules, tseslint };

export default shopwareAdminExtension;
