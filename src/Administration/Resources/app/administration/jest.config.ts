/**
 * @sw-package framework
 */

/**
 * Jest configuration shared by Administration unit tests and Storefront administration tests.
 *
 * It includes build-time TypeScript helpers such as `vue-setup-transform` so transform tests run in
 * the same project-level module aliases and coverage collection as application code.
 */

// For a detailed explanation regarding each configuration property, visit:
// https://jestjs.io/docs/en/configuration.html
import type { Config } from 'jest';
import { existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));

process.env.PROJECT_ROOT = process.env.PROJECT_ROOT || process.env.INIT_CWD || '.';
process.env.ADMIN_PATH = process.env.ADMIN_PATH || __dirname;
process.env.TZ = process.env.TZ || 'UTC';

// Tests run in Node/jsdom, so browser data freshness is irrelevant here. Without this, browserslist's
// stale caniuse-lite warning (triggered via vue-jest -> babel preset-env target resolution) is escalated
// to a test failure by the console.warn guard in prepare_environment.js once the lockfile data ages 6 months.
process.env.BROWSERSLIST_IGNORE_OLD_DATA = process.env.BROWSERSLIST_IGNORE_OLD_DATA || 'true';

// Check if ADMIN_PATH/test/_helper_/component-imports.js exists
if (!existsSync(join(process.env.ADMIN_PATH, '/test/_helper_/componentWrapper/component-imports.js'))) {
    throw new Error(
        'Missing required /test/_helper_/componentWrapper/component-imports.js file to run tests. Run `npm run unit-setup` before executing tests, or use `composer run admin:unit`.',
    );
}

process.env.JEST_CACHE_DIR = process.env.JEST_CACHE_DIR || '<rootDir>.jestcache';

const isCi = (() => {
    return process.argv.some((arg) => arg === '--ci');
})();

// The extension-tooling e2e specs run the real ESLint/tsc toolchain against fixture projects and
// take minutes, which unbalances sharded unit runs. They run separately via `npm run unit:e2e`.
const e2eSpecPattern = '<rootDir>/scripts/**/e2e.spec/**/*.spec.ts';
const isDocker = existsSync('/.dockerenv');

if (isCi) {
    // eslint-disable-next-line no-console
    console.info('Run Jest in CI mode');
} else {
    // eslint-disable-next-line no-console
    console.info('Run Jest in local mode');
}

const config: Config = {
    roots: [
        '<rootDir>',
        '<rootDir>/../../../../Storefront/Resources/app/administration',
    ],
    cacheDirectory: process.env.JEST_CACHE_DIR,
    globals: {
        adminPath: process.env.ADMIN_PATH,
        projectRoot: process.env.PROJECT_ROOT,
    },

    globalTeardown: '<rootDir>test/globalTeardown.js',

    resolver: '<rootDir>/test/_helper_/jest-resolver.js',

    // Use default jest-circus runner (Jest 30+), removed deprecated jest-jasmine2
    testEnvironment: '<rootDir>/test/_setup/feature-flag-test-environment.js',

    // Worker configuration - prevent OOM kills while maximizing parallelism
    // Memory limit per worker to prevent SIGSEGV crashes from memory pressure.
    // Keep the default conservative for constrained Docker setups; CI raises it via env.
    workerIdleMemoryLimit: process.env.JEST_WORKER_IDLE_MEMORY_LIMIT || '1GB',
    // Full CPU parallelism can cause worker OOM kills in constrained CI/Docker runners.
    maxWorkers: process.env.JEST_MAX_WORKERS || (isDocker ? '100%' : '50%'),
    testTimeout: process.env.JEST_TEST_TIMEOUT ? Number(process.env.JEST_TEST_TIMEOUT) : isCi || isDocker ? 10000 : 5000,
    collectCoverage: isCi,
    // V8 coverage is much cheaper than babel-plugin-istanbul instrumentation on top of @swc/jest
    coverageProvider: 'v8',
    clearMocks: true,
    restoreMocks: true,
    moduleFileExtensions: [
        'js',
        'ts',
        'vue',
        'json',
    ],

    // Performance optimizations
    // Skip node_modules transformation where possible (already handled by transformIgnorePatterns)
    // Cache transformed files aggressively
    cache: true,
    // Use native ESM where possible for faster execution
    extensionsToTreatAsEsm: ['.ts'],
    // Shard support for parallel CI execution (use with --shard flag)
    // Example: npm run unit -- --shard=1/4

    coverageDirectory: join(process.env.PROJECT_ROOT, '/build/artifacts/jest'),

    collectCoverageFrom: [
        'src/**/*.js',
        'src/**/*.ts',
        '!src/**/*.spec.js',
        '!src/**/*.spec/**',
        '!**/*.d.ts',
        '<rootDir>/../../../../Storefront/Resources/app/administration/src/**/*.js',
        '<rootDir>/../../../../Storefront/Resources/app/administration/src/**/*.ts',
        '!<rootDir>/../../../../Storefront/Resources/app/administration/src/**/*.spec.js',
        '!<rootDir>/../../../../Storefront/Resources/app/administration/src/**/*.spec/**',

        // Exception in the build dir for vite plugins
        'build/vite-plugins/**/*.ts',
        '!build/vite-plugins/**/*.spec.ts',
        'build/vue-setup-transform/**/*.ts',
        '!build/vue-setup-transform/**/*.spec.ts',
        '!build/vue-setup-transform/**/index.spec/**',

        // The extension tooling ships as production code (it runs in a shop via
        // composer/bin/console), so its coverage is measured like any other.
        // test-helpers.ts is fixture plumbing, not a covered source.
        'scripts/extensionTooling/**/*.ts',
        '!scripts/extensionTooling/**/*.spec.ts',
        '!scripts/extensionTooling/**/*.spec/**',
        '!scripts/extensionTooling/test-helpers.ts',
    ],

    coverageReporters: [
        isCi ? 'text-summary' : 'text',
        'cobertura',
        'html-spa',
    ],

    setupFiles: [
        resolve(join(__dirname, '/test/_setup/jsdom-polyfills.js')),
    ],

    setupFilesAfterEnv: [
        resolve(join(__dirname, '/test/_setup/setup-feature-flags.js')),
        resolve(join(__dirname, '/test/_setup/setup-shopware.js')),
        'jest-expect-message',
        resolve(join(__dirname, '/test/_setup/prepare_environment.js')),
        resolve(join(__dirname, '/test/_setup/jest-extensions.ts')),
    ],

    transform: {
        // Files using import.meta.glob need the Babel plugin for transformation
        '(module/index|core/service/api/index|app/mixin/index|app/decorator/index|app/plugin/index|app/directive/index|app/filter/index)\\.[jt]sx?$':
            [
                'babel-jest',
                {
                    presets: [
                        '@babel/preset-typescript',
                        [
                            '@babel/preset-env',
                            { targets: { node: 'current' } },
                        ],
                    ],
                    plugins: [
                        'shopware-vite-meta-glob',
                    ],
                },
            ],
        '^.+\\.[jt]sx?$': [
            '@swc/jest',
            {
                jsc: {
                    parser: { syntax: 'typescript', decorators: true },
                    target: 'es2021',
                },
            },
        ],
        '^.+(\\.twig|\\.html)$': '<rootDir>/test/transformer/twigToVueTransformer.js',
        '.*\\.(svg)$': '<rootDir>/test/transformer/svgStringifyTransformer.js',
        '^.+\\.vue$': '<rootDir>/test/transformer/shopwareSetupVueTransformer.js',
    },

    transformIgnorePatterns: [
        '/node_modules/(?!(@shopware-ag/meteor-component-library|@shopware-ag/meteor-icon-kit|uuidv7|other)/)',
    ],

    moduleNameMapper: {
        '\\.(css|less|scss)$': '<rootDir>/test/_mocks_/styleMock.js',
        '^src(.*)$': '<rootDir>/src$1',
        '^lodash-es/debounce$': '<rootDir>/test/_mocks_/lodash-es-debounce.js',
        '^test(.*)$': '<rootDir>/test$1',
        '^@shopware-ag/admin-extension-sdk/es/(.*)': '<rootDir>/node_modules/@shopware-ag/admin-extension-sdk/umd/$1',
        '^@shopware-ag/meteor-admin-sdk/es/(.*)': '<rootDir>/node_modules/@shopware-ag/meteor-admin-sdk/umd/$1',
        '^@shopware-ag/meteor-component-library$':
            '<rootDir>/node_modules/@shopware-ag/meteor-component-library/dist/common/index.js',
        '^@shopware-ag/meteor-component-library/dist/esm/(.*)$':
            '<rootDir>/node_modules/@shopware-ag/meteor-component-library/dist/common/$1',
        '^@vue/test-utils$': '<rootDir>/node_modules/@vue/test-utils',
        '^lodash-es$': 'lodash',
        '^lodash-es/(.*)$': 'lodash/$1',
        '^vue$': 'vue/dist/vue.cjs.js',
    },

    reporters: isCi
        ? [
              [
                  'jest-silent-reporter',
                  {
                      useDots: true,
                      showWarnings: true,
                      showPaths: true,
                  },
              ],
              [
                  'jest-junit',
                  {
                      suiteName: 'Shopware 6 Unit Tests',
                      outputDirectory: join(process.env.PROJECT_ROOT, '/build/artifacts/jest'),
                      outputName: 'administration.junit.xml',
                  },
              ],
          ]
        : [
              'default',
              '<rootDir>/test/_helper_/failedSpecFileReporter.js',
          ],

    testMatch:
        process.env.JEST_E2E === '1'
            ? [e2eSpecPattern]
            : [
                  '<rootDir>/src/**/*.spec.js',
                  '<rootDir>/src/**/*.spec.ts',
                  '<rootDir>/src/**/*.spec/*.spec.js',
                  '<rootDir>/src/**/*.spec/*.spec.ts',
                  '<rootDir>/../../../../Storefront/Resources/app/administration/src/**/*.spec.js',
                  '<rootDir>/../../../../Storefront/Resources/app/administration/src/**/*.spec.ts',
                  '<rootDir>/../../../../Storefront/Resources/app/administration/src/**/*.spec/*.spec.js',
                  '<rootDir>/../../../../Storefront/Resources/app/administration/src/**/*.spec/*.spec.ts',
                  '<rootDir>/eslint-rules/**/*.spec.js',
                  '<rootDir>/build/vite-plugins/**/*.spec.ts',
                  '<rootDir>/build/vite-plugins/**/*.spec.js',
                  '<rootDir>/build/vue-setup-transform/**/*.spec.ts',
                  '<rootDir>/test/_helper_/**/*.spec.ts',
                  '<rootDir>/test/_setup/**/*.spec.ts',
                  '!<rootDir>/src/**/*.spec.vue2.js',
                  '<rootDir>/scripts/**/*.spec.ts',
              ],

    // Jest does not expand <rootDir> in negated testMatch globs, so the e2e exclusion
    // lives here as a regex against absolute paths instead.
    testPathIgnorePatterns:
        process.env.JEST_E2E === '1'
            ? ['/node_modules/']
            : [
                  '/node_modules/',
                  '/e2e\\.spec/',
              ],

    testEnvironmentOptions: {
        customExportConditions: [
            'node',
            'node-addons',
        ],
    },
};

export default config;
