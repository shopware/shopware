/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.8.0 feature:ADMIN_EXTENSION_TOOLING
 *
 * Shared jest config that is used by the admin and extensions.
 * New capabilities added in admin are automatically available in extensions as well.
 */

import { existsSync } from 'node:fs';
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const administrationRoot = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const require = createRequire(import.meta.url);

/**
 * Resolves a path inside the installed Administration, so harness paths stay
 * absolute and work from any extension `rootDir`.
 *
 * @example
 * fromAdministration('test', '_setup'); // → <administration>/test/_setup
 *
 * @param {...string} segments
 */
const fromAdministration = (...segments) => path.join(administrationRoot, ...segments);

/**
 * Resolves a package from the Administration's `node_modules`, which the
 * harness depends on even when the extension does not install it.
 *
 * @example
 * fromNodeModules('@vue', 'test-utils'); // → <administration>/node_modules/@vue/test-utils
 *
 * @param {...string} segments
 */
const fromNodeModules = (...segments) => fromAdministration('node_modules', ...segments);

/** Files the harness reads that every installation generates itself, with the command that does it. */
const GENERATED_FILES = [
    {
        path: fromAdministration('test', '_helper_', 'componentWrapper', 'component-imports.js'),
        generate: `\`npm run unit-setup\` in ${administrationRoot}`,
    },
    {
        path: fromAdministration('test', '_mocks_', 'entity-schema.json'),
        generate: '`bin/console administration:generate-entity-schema-types`',
    },
];

/**
 * Options merged into the harness instead of replacing it, so an extension can
 * add to the setup files, mappings and transforms without dropping the host's.
 */
const MERGED_OPTIONS = [
    'setupFiles',
    'setupFilesAfterEnv',
    'moduleNameMapper',
    'transform',
    'moduleDirectories',
    'globals',
    'testEnvironmentOptions',
];

/**
 * Allow list of jest config keys that an extension may replace with its own value.
 * This prevents silent config merge failures.
 */
const REPLACEABLE_OPTIONS = [
    'bail',
    'cache',
    'cacheDirectory',
    'clearMocks',
    'collectCoverage',
    'collectCoverageFrom',
    'coverageDirectory',
    'coveragePathIgnorePatterns',
    'coverageProvider',
    'coverageReporters',
    'coverageThreshold',
    'displayName',
    'extensionsToTreatAsEsm',
    'fakeTimers',
    'globalSetup',
    'globalTeardown',
    'maxWorkers',
    'modulePathIgnorePatterns',
    'reporters',
    'resetMocks',
    'restoreMocks',
    'roots',
    'runner',
    'snapshotFormat',
    'snapshotSerializers',
    'testMatch',
    'testPathIgnorePatterns',
    'testRegex',
    'testTimeout',
    'transformIgnorePatterns',
    'verbose',
    'watchPathIgnorePatterns',
    'workerIdleMemoryLimit',
];

/** Every option `shopwareAdminJest` accepts; anything else throws. */
const ACCEPTED_OPTIONS = new Set([
    'rootDir',
    ...MERGED_OPTIONS,
    ...REPLACEABLE_OPTIONS,
]);

/**
 * Fails early with the command that generates a missing file, instead of
 * letting the first spec die on an unresolved import.
 *
 * @example
 * assertGeneratedFilesExist(); // throws, listing each missing file with the command that generates it
 */
function assertGeneratedFilesExist() {
    const missing = GENERATED_FILES.filter((file) => !existsSync(file.path));

    if (missing.length > 0) {
        throw new Error(
            [
                'The Administration Jest harness is missing generated files:',
                ...missing.map((file) => `- ${file.path}: run ${file.generate}`),
            ].join('\n'),
        );
    }
}

/**
 * Rejects every option outside the allowlist, so an extension cannot swap out
 * a part of the harness, such as the test environment, without noticing.
 *
 * @example
 * assertAcceptedOptions({ rootDir: '/extension', testTimeout: 20000 }); // passes
 * assertAcceptedOptions({ rootDir: '/extension', testEnvironment: 'node' }); // throws, naming "testEnvironment"
 *
 * @param {Record<string, unknown>} options
 */
function assertAcceptedOptions(options) {
    const rejected = Object.keys(options).filter((key) => !ACCEPTED_OPTIONS.has(key));

    if (rejected.length === 0) {
        return;
    }

    throw new Error(
        `shopwareAdminJest does not accept the ${rejected.length === 1 ? 'option' : 'options'} ` +
            `${rejected.map((key) => `"${key}"`).join(', ')}. ` +
            'The harness owns testEnvironment and resolver, because it.activeFeatureFlags() and ' +
            'the Vite import handling depend on them. ' +
            `Accepted options: ${[...ACCEPTED_OPTIONS].join(', ')}.`,
    );
}

/**
 * Combines two pattern maps so that `preferred` is matched first and wins on
 * an identical key. Jest tries `moduleNameMapper` and `transform` patterns in
 * insertion order and uses the first match.
 *
 * @example
 * withPriority({ '^MyPlugin/(.*)$': 'src/$1' }, { '^src/(.*)$': 'admin/src/$1' });
 * // → { '^MyPlugin/(.*)$': 'src/$1', '^src/(.*)$': 'admin/src/$1' }
 *
 * @template T
 * @param {Record<string, T> | undefined} preferred
 * @param {Record<string, T>} fallback
 * @returns {Record<string, T>}
 */
function withPriority(preferred = {}, fallback) {
    return { ...preferred, ...fallback, ...preferred };
}

/**
 * Creates the Jest config for Administration specs.
 *
 * `rootDir` is required: the directory the extension's specs and paths are
 * relative to. The options that build up the harness are merged, so an
 * extension cannot drop it by accident:
 *
 * - `setupFiles`, `setupFilesAfterEnv`: run after the Administration's own,
 *   so `Shopware`, the feature flags and the virtual modules are ready.
 * - `moduleNameMapper`, `transform`, `moduleDirectories`: matched before the
 *   Administration's own, so an extension alias or dependency wins over the
 *   host's.
 * - `globals`, `testEnvironmentOptions`: merged, the extension's values win,
 *   except `globals.adminPath` and `globals['vue-jest'].tsConfig`, which stay pinned to this Administration.
 *
 * The options in `REPLACEABLE_OPTIONS`, such as `testMatch`, `reporters` or
 * `transformIgnorePatterns` (default: none), replace the default. Any other option throws,
 * including `testEnvironment` and `resolver`, which the harness owns.
 *
 * @example
 * // jest.config.mjs of an extension
 * import { shopwareAdminJest } from './.shopware/jest.mjs';
 *
 * export default shopwareAdminJest({
 *     rootDir: import.meta.dirname,
 *     moduleNameMapper: { '^MyPlugin/(.*)$': '<rootDir>/src/$1' },
 * });
 *
 * @param {import('jest').Config} options
 * @returns {import('jest').Config}
 */
export function shopwareAdminJest(options = {}) {
    const {
        rootDir,
        setupFiles = [],
        setupFilesAfterEnv = [],
        moduleNameMapper,
        transform,
        moduleDirectories = [],
        globals,
        testEnvironmentOptions,
        ...replaced
    } = options;

    if (!rootDir) {
        throw new Error(
            'shopwareAdminJest requires the "rootDir" option. ' +
                'Pass the directory that contains your specs, e.g. ' +
                'shopwareAdminJest({ rootDir: import.meta.dirname }).',
        );
    }

    assertAcceptedOptions(options);
    assertGeneratedFilesExist();

    // The setup files read these from the environment; Jest workers inherit it.
    // Overrides an inherited ADMIN_PATH: the setup files must read the feature flags of the Administration
    // whose harness runs.
    process.env.ADMIN_PATH = administrationRoot;
    process.env.TZ = process.env.TZ || 'UTC';
    // Tests run in Node/jsdom, so browser data freshness is irrelevant here. Without this, browserslist's
    // stale caniuse-lite warning (triggered via vue-jest -> babel preset-env target resolution) is escalated
    // to a test failure by the console.warn guard in prepare_environment.js once the lockfile data ages 6 months.
    process.env.BROWSERSLIST_IGNORE_OLD_DATA = process.env.BROWSERSLIST_IGNORE_OLD_DATA || 'true';

    return {
        rootDir,
        moduleFileExtensions: [
            'js',
            'ts',
            'vue',
            'json',
        ],

        resolver: fromAdministration('test', '_helper_', 'jest-resolver.js'),
        // Backs `it.activeFeatureFlags()`; extends the jsdom environment.
        testEnvironment: fromAdministration('test', '_setup', 'feature-flag-test-environment.js'),
        testEnvironmentOptions: {
            customExportConditions: [
                'node',
                'node-addons',
            ],
            ...testEnvironmentOptions,
        },

        // Empty, because the JS/TS transformer passes CommonJS dependencies through and converts only ES modules.
        transformIgnorePatterns: [],

        ...replaced,

        // Bare imports an extension does not install itself resolve from the Administration.
        moduleDirectories: [
            ...new Set([
                ...moduleDirectories,
                'node_modules',
                fromNodeModules(),
            ]),
        ],

        // Pinned like ADMIN_PATH: setup-shopware.js must load the same Administration the ^src/ mapping resolves to.
        // vue-jest otherwise reads the cwd tsconfig; the Administration's sets downlevelIteration for its forced ES5 target.
        globals: {
            ...globals,
            adminPath: administrationRoot,
            'vue-jest': {
                ...globals?.['vue-jest'],
                tsConfig: fromAdministration('tsconfig.json'),
            },
        },

        setupFiles: [
            // Ahead of every other setup file: `setup-shopware.js` loads the mixin registry, which imports
            // `shopware:utils`, and a virtual module has to be registered before anything requires it.
            fromAdministration('test', '_helper_', 'virtual-shopware-modules', 'register-mocks.js'),
            fromAdministration('test', '_setup', 'jsdom-polyfills.js'),
            ...setupFiles,
        ],

        setupFilesAfterEnv: [
            // Registers every known feature flag, so `triggerDeprecationOrThrow` warns before a major and
            // throws once the major flag is active, instead of reporting an unknown flag.
            fromAdministration('test', '_setup', 'setup-feature-flags.js'),
            fromAdministration('test', '_setup', 'setup-shopware.js'),
            require.resolve('jest-expect-message'),
            fromAdministration('test', '_setup', 'prepare_environment.js'),
            // `it.deprecated()` and `it.activeFeatureFlags()`.
            fromAdministration('test', '_setup', 'jest-extensions.ts'),
            ...setupFilesAfterEnv,
        ],

        transform: withPriority(transform, {
            // Babel for files that use `import.meta.glob`, which only its meta-glob plugin rewrites; SWC for the rest.
            '^.+\\.[jt]sx?$': [
                fromAdministration('test', 'transformer', 'metaGlobAwareTransformer.js'),
                {
                    babel: {
                        presets: [
                            require.resolve('@babel/preset-typescript'),
                            [
                                require.resolve('@babel/preset-env'),
                                { targets: { node: 'current' } },
                            ],
                        ],
                        plugins: [
                            require.resolve('babel-plugin-shopware-vite-meta-glob'),
                        ],
                    },
                    swc: {
                        jsc: {
                            parser: { syntax: 'typescript', decorators: true },
                            target: 'es2021',
                        },
                    },
                },
            ],
            '^.+(\\.twig|\\.html)$': fromAdministration('test', 'transformer', 'twigToVueTransformer.js'),
            '.*\\.(svg)$': fromAdministration('test', 'transformer', 'svgStringifyTransformer.js'),
            '^.+\\.vue$': fromAdministration('test', 'transformer', 'shopwareSetupVueTransformer.js'),
        }),

        // Pins Vue, @vue/test-utils, Pinia, vue-i18n, vue-router, lodash and the Meteor packages to the Administration's
        // copy, so an extension spec and the Administration code it mounts share one instance of each,
        // e.g. one active Pinia.
        moduleNameMapper: withPriority(moduleNameMapper, {
            '\\.(css|less|scss)$': fromAdministration('test', '_mocks_', 'styleMock.js'),
            '^src/(.*)$': `${fromAdministration('src')}/$1`,
            '^@shopware-ag/admin-extension-sdk/es/(.*)': `${fromNodeModules('@shopware-ag/admin-extension-sdk/umd')}/$1`,
            '^@shopware-ag/meteor-admin-sdk/es/(.*)': `${fromNodeModules('@shopware-ag/meteor-admin-sdk/umd')}/$1`,
            '^@shopware-ag/meteor-component-library$': fromNodeModules(
                '@shopware-ag/meteor-component-library/dist/common/index.js',
            ),
            '^@shopware-ag/meteor-component-library/dist/esm/(.*)$': `${fromNodeModules(
                '@shopware-ag/meteor-component-library/dist/common',
            )}/$1`,
            '^@vue/test-utils$': fromNodeModules('@vue/test-utils'),
            '^pinia$': fromNodeModules('pinia'),
            '^vue-i18n$': fromNodeModules('vue-i18n'),
            '^vue-router$': fromNodeModules('vue-router'),
            '^lodash-es$': fromNodeModules('lodash'),
            '^lodash-es/(.*)$': `${fromNodeModules('lodash')}/$1`,
            '^vue$': fromNodeModules('vue/dist/vue.cjs.js'),
        }),
    };
}

export default shopwareAdminJest;
