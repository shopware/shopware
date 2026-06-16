/**
 * @sw-package framework
 */

// For a detailed explanation regarding each configuration property, visit:
// https://jestjs.io/docs/en/configuration.html
const { existsSync } = require('fs');
const { join, resolve } = require('path');

process.env.PROJECT_ROOT = process.env.PROJECT_ROOT || process.env.INIT_CWD || '.';
process.env.ADMIN_PATH = process.env.ADMIN_PATH || __dirname;
process.env.TZ = process.env.TZ || 'UTC';

// Check if ADMIN_PATH/test/_helper_/component-imports.js exists
if (!existsSync(join(process.env.ADMIN_PATH, '/test/_helper_/componentWrapper/component-imports.js'))) {
    // eslint-disable-next-line max-len
    throw new Error('Missing required /test/_helper_/componentWrapper/component-imports.js file to run tests. Run `npm run unit-setup` before executing tests, or use `composer run admin:unit`.');
}

process.env.JEST_CACHE_DIR = process.env.JEST_CACHE_DIR || '<rootDir>.jestcache';

const isCi = (() => {
    return process.argv.some((arg) => arg === '--ci');
})();
const isDocker = existsSync('/.dockerenv');

if (isCi) {
    // eslint-disable-next-line no-console
    console.info('Run Jest in CI mode');
} else {
    // eslint-disable-next-line no-console
    console.info('Run Jest in local mode');
}

module.exports = {
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
    testEnvironment: 'jsdom',

    // Worker configuration - prevent OOM kills while maximizing parallelism
    // Memory limit per worker to prevent SIGSEGV crashes from memory pressure
    workerIdleMemoryLimit: '1GB',
    // Full CPU parallelism can cause worker OOM kills in constrained CI/Docker runners.
    maxWorkers: process.env.JEST_MAX_WORKERS || (isDocker ? '100%' : '50%'),
    testTimeout: process.env.JEST_TEST_TIMEOUT ? Number(process.env.JEST_TEST_TIMEOUT) : (isCi || isDocker ? 10000 : 5000),
    collectCoverage: isCi,
    clearMocks: true,
    restoreMocks: true,
    moduleFileExtensions: ['js', 'ts', 'vue', 'json'],

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
        resolve(join(__dirname, '/test/_setup/setup-shopware.js')),
        'jest-expect-message',
        resolve(join(__dirname, '/test/_setup/prepare_environment.js')),
    ],

    transform: {
        // Files using import.meta.glob need the Babel plugin for transformation
        '(module/index|core/service/api/index|app/mixin/index|app/decorator/index|app/plugin/index|app/directive/index|app/filter/index)\\.[jt]sx?$': ['babel-jest', {
            presets: [
                '@babel/preset-typescript',
                ['@babel/preset-env', { targets: { node: 'current' } }],
            ],
            plugins: [
                'shopware-vite-meta-glob',
            ],
        }],
        '^.+\\.[jt]sx?$': ['@swc/jest', {
            jsc: {
                parser: { syntax: 'typescript', decorators: true },
                target: 'es2021',
            },
        }],
        '^.+(\\.twig|\\.html)$': '<rootDir>/test/transformer/twigToVueTransformer.js',
        '.*\\.(svg)$': '<rootDir>/test/transformer/svgStringifyTransformer.js',
        '^.+\\.vue$': '@vue/vue3-jest',
    },

    transformIgnorePatterns: [
        '/node_modules/(?!(@shopware-ag/meteor-component-library|@shopware-ag/meteor-icon-kit|uuidv7|other)/)',
    ],

    moduleNameMapper: {
        '\\.(css|less|scss)$': '<rootDir>/test/_mocks_/styleMock.js',
        '^src(.*)$': '<rootDir>/src$1',
        '^lodash-es/debounce$': '<rootDir>/test/_mocks_/lodash-es-debounce.js',
        '^test(.*)$': '<rootDir>/test$1',
        '^\@shopware-ag\/admin-extension-sdk\/es\/(.*)': '<rootDir>/node_modules/@shopware-ag/admin-extension-sdk/umd/$1',
        '^\@shopware-ag\/meteor-admin-sdk\/es\/(.*)': '<rootDir>/node_modules/@shopware-ag/meteor-admin-sdk/umd/$1',
        '^@shopware-ag/meteor-component-library$': '<rootDir>/node_modules/@shopware-ag/meteor-component-library/dist/common/index.js',
        '^@shopware-ag/meteor-component-library/dist/esm/(.*)$': '<rootDir>/node_modules/@shopware-ag/meteor-component-library/dist/common/$1',
        '^@vue/test-utils$': '<rootDir>/node_modules/@vue/test-utils',
        '^lodash-es$': 'lodash',
        '^lodash-es/(.*)$': 'lodash/$1',
        vue$: 'vue/dist/vue.cjs.js',
    },

    reporters: isCi ? [
        [
            'jest-silent-reporter',
            {
                useDots: true,
                showWarnings: true,
                showPaths: true,
            },
        ],
        ['jest-junit', {
            suiteName: 'Shopware 6 Unit Tests',
            outputDirectory: join(process.env.PROJECT_ROOT, '/build/artifacts/jest'),
            outputName: 'administration.junit.xml',
        }],
    ] : [
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
        '!<rootDir>/src/**/*.spec.vue2.js',
        '<rootDir>/scripts/**/*.spec.ts',
    ],

    testEnvironmentOptions: {
        customExportConditions: ['node', 'node-addons'],
    },
};
