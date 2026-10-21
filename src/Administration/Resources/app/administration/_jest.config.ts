/**
 * @sw-package framework
 */

/**
 * Jest configuration shared by Administration unit tests and Storefront administration tests.
 *
 * The test harness itself (environment, setup files, transforms, host module mapping) comes from the
 * extension-tooling factory, which extensions compose as well. This file adds what only the platform
 * runs: the Storefront roots, the specs and coverage of the build-time helpers such as
 * `vue-setup-transform`, CI reporting and coverage.
 */

// For a detailed explanation regarding each configuration property, visit:
// https://jestjs.io/docs/en/configuration.html
import type { Config } from 'jest';
import { existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { shopwareAdminJest } from './extension-tooling/jest.mjs';

const __dirname = dirname(fileURLToPath(import.meta.url));

process.env.PROJECT_ROOT = process.env.PROJECT_ROOT || process.env.INIT_CWD || '.';

process.env.JEST_CACHE_DIR = process.env.JEST_CACHE_DIR || '<rootDir>.jestcache';

const isCi = (() => {
    return process.argv.some((arg) => arg === '--ci');
})();
const isDocker = existsSync('/.dockerenv');

// The extension-tooling e2e specs scaffold a project and run the real vue-tsc/ESLint
// toolchain, so they are slow and turn red on any unrelated type or lint breakage.
// Gated to a dedicated nightly job (see admin.yml) instead of every pull request.
const runExtensionToolingE2e = process.env.EXTENSION_TOOLING_E2E === '1';

if (isCi) {
    // eslint-disable-next-line no-console
    console.info('Run Jest in CI mode');
} else {
    // eslint-disable-next-line no-console
    console.info('Run Jest in local mode');
}

const config: Config = shopwareAdminJest({
    rootDir: __dirname,
    roots: [
        '<rootDir>',
        '<rootDir>/../../../../Storefront/Resources/app/administration',
    ],
    cacheDirectory: process.env.JEST_CACHE_DIR,
    globals: {
        projectRoot: process.env.PROJECT_ROOT,
    },

    globalTeardown: '<rootDir>test/globalTeardown.js',

    // Worker configuration - prevent OOM kills while maximizing parallelism
    // Memory limit per worker to prevent SIGSEGV crashes from memory pressure
    workerIdleMemoryLimit: '1GB',
    // Full CPU parallelism can cause worker OOM kills in constrained CI/Docker runners.
    maxWorkers: process.env.JEST_MAX_WORKERS || (isDocker ? '100%' : '50%'),
    testTimeout: process.env.JEST_TEST_TIMEOUT ? Number(process.env.JEST_TEST_TIMEOUT) : isCi || isDocker ? 10000 : 5000,
    collectCoverage: isCi,
    clearMocks: true,
    restoreMocks: true,

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

    // Platform-only mappings, matched before the harness mappings of the factory.
    moduleNameMapper: {
        '^lodash-es/debounce$': '<rootDir>/test/_mocks_/lodash-es-debounce.js',
        '^test(.*)$': '<rootDir>/test$1',
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

    testMatch: [
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

    testPathIgnorePatterns: [
        '/node_modules/',
        ...(runExtensionToolingE2e ? [] : ['<rootDir>/scripts/extensionTooling/e2e\\.spec/']),
    ],
});

export default config;
