/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.8.0 feature:ADMIN_EXTENSION_TOOLING
 *
 * Portable Jest config factory for Administration extensions. It carries the
 * Administration's test harness — the feature-flag environment, the
 * `shopware:*` virtual modules, the global `Shopware` setup and the transforms —
 * with every path resolved into the installed Administration, so an extension
 * spec runs against the same harness as the Administration's own specs. The
 * Administration's own Jest config composes this factory too, so the two
 * cannot drift apart.
 */

import { existsSync } from 'node:fs';
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const administrationRoot = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const require = createRequire(import.meta.url);

/** @param {...string} segments */
const fromAdministration = (...segments) => path.join(administrationRoot, ...segments);

/** @param {...string} segments */
const fromNodeModules = (...segments) => fromAdministration('node_modules', ...segments);

const componentImportsPath = fromAdministration('test', '_helper_', 'componentWrapper', 'component-imports.js');

/**
 * Admin files that use `import.meta.glob`, which only the Babel plugin can
 * transform. Everything else goes through the faster SWC transform.
 */
const META_GLOB_FILES = `(${[
    'module/index',
    'core/service/api/index',
    'app/mixin/index',
    'app/decorator/index',
    'app/plugin/index',
    'app/directive/index',
    'app/filter/index',
].join('|')})\\.[jt]sx?$`;

/**
 * Fails early with the step that fixes a missing harness, instead of letting
 * the first spec die on an unresolved setup file.
 */
function assertHarnessAvailable() {
    if (!existsSync(fromAdministration('test', '_setup'))) {
        throw new Error(
            `The Administration at ${administrationRoot} ships no Jest harness. ` +
                'The Composer package of shopware/administration excludes its test/ folder, ' +
                'so extension specs run against a platform checkout of Shopware.',
        );
    }

    if (!existsSync(componentImportsPath)) {
        throw new Error(
            'Missing required /test/_helper_/componentWrapper/component-imports.js file to run tests. ' +
                'Run `npm run unit-setup` before executing tests, or use `composer run admin:unit`.',
        );
    }
}

/**
 * Combines two pattern maps so that `preferred` is matched first and wins on
 * an identical key. Jest tries `moduleNameMapper` and `transform` patterns in
 * insertion order and uses the first match.
 *
 * @example
 * withPriority({ '^MyPlugin(.*)$': 'src$1' }, { '^src(.*)$': 'admin/src$1' });
 * // → { '^MyPlugin(.*)$': 'src$1', '^src(.*)$': 'admin/src$1' }
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
 * Accepts any Jest option. `rootDir` is required: the directory the extension's
 * specs and paths are relative to. Most options simply replace the default;
 * the ones that build up the harness are merged instead, so an extension
 * cannot drop it by accident:
 *
 * - `setupFiles`, `setupFilesAfterEnv`: run after the Administration's own,
 *   so `Shopware`, the feature flags and the virtual modules are ready.
 * - `moduleNameMapper`, `transform`, `moduleDirectories`: matched before the
 *   Administration's own, so an extension alias or dependency wins over the
 *   host's.
 * - `globals`: merged, the extension's values win.
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
        ...rest
    } = options;

    if (!rootDir) {
        throw new Error(
            'shopwareAdminJest requires the "rootDir" option. ' +
                'Pass the directory that contains your specs, e.g. ' +
                'shopwareAdminJest({ rootDir: import.meta.dirname }).',
        );
    }

    assertHarnessAvailable();

    // The setup files read these from the environment; Jest workers inherit it.
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
        },

        ...rest,

        // Bare imports an extension does not install itself resolve from the Administration.
        moduleDirectories: [
            ...new Set([
                ...moduleDirectories,
                'node_modules',
                fromNodeModules(),
            ]),
        ],

        globals: {
            adminPath: administrationRoot,
            ...globals,
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
            [META_GLOB_FILES]: [
                require.resolve('babel-jest'),
                {
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
            ],
            '^.+\\.[jt]sx?$': [
                require.resolve('@swc/jest'),
                {
                    jsc: {
                        parser: { syntax: 'typescript', decorators: true },
                        target: 'es2021',
                    },
                },
            ],
            '^.+(\\.twig|\\.html)$': fromAdministration('test', 'transformer', 'twigToVueTransformer.js'),
            '.*\\.(svg)$': fromAdministration('test', 'transformer', 'svgStringifyTransformer.js'),
            '^.+\\.vue$': fromAdministration('test', 'transformer', 'shopwareSetupVueTransformer.js'),
        }),

        transformIgnorePatterns: [
            '/node_modules/(?!(@shopware-ag/meteor-component-library|@shopware-ag/meteor-icon-kit|uuidv7|three|other)/)',
        ],

        // Pins every host package to the Administration's copy, so an extension spec and the
        // Administration code it mounts share one Vue, one test-utils config and one component library.
        moduleNameMapper: withPriority(moduleNameMapper, {
            '\\.(css|less|scss)$': fromAdministration('test', '_mocks_', 'styleMock.js'),
            '^src(.*)$': `${fromAdministration('src')}$1`,
            '^@shopware-ag/admin-extension-sdk/es/(.*)': `${fromNodeModules('@shopware-ag/admin-extension-sdk/umd')}/$1`,
            '^@shopware-ag/meteor-admin-sdk/es/(.*)': `${fromNodeModules('@shopware-ag/meteor-admin-sdk/umd')}/$1`,
            '^@shopware-ag/meteor-component-library$': fromNodeModules(
                '@shopware-ag/meteor-component-library/dist/common/index.js',
            ),
            '^@shopware-ag/meteor-component-library/dist/esm/(.*)$': `${fromNodeModules(
                '@shopware-ag/meteor-component-library/dist/common',
            )}/$1`,
            '^@vue/test-utils$': fromNodeModules('@vue/test-utils'),
            '^lodash-es$': fromNodeModules('lodash'),
            '^lodash-es/(.*)$': `${fromNodeModules('lodash')}/$1`,
            '^vue$': fromNodeModules('vue/dist/vue.cjs.js'),
        }),
    };
}

export default shopwareAdminJest;
