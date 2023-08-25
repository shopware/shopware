/**
 * @package admin
 */

// For a detailed explanation regarding each configuration property, visit:
// https://jestjs.io/docs/en/configuration.html
const { join, resolve } = require('path');

process.env.PROJECT_ROOT = process.env.PROJECT_ROOT || process.env.INIT_CWD || '.';
process.env.ADMIN_PATH = process.env.ADMIN_PATH || __dirname;
process.env.TZ = process.env.TZ || 'UTC';

process.env.JEST_CACHE_DIR = process.env.JEST_CACHE_DIR ? `${process.env.JEST_CACHE_DIR}_vue3` : '<rootDir>.jestcache_vue3';

const isCi = (() => {
    return process.argv.some((arg) => arg === '--ci');
})();

if (isCi) {
    // eslint-disable-next-line no-console
    console.info('Run Jest in CI mode');
} else {
    // eslint-disable-next-line no-console
    console.info('Run Jest in local mode');
}

module.exports = {
    cacheDirectory: process.env.JEST_CACHE_DIR,
    preset: '@shopware-ag/jest-preset-sw6-admin',
    globals: {
        adminPath: process.env.ADMIN_PATH,
        projectRoot: process.env.PROJECT_ROOT,
    },

    globalTeardown: '<rootDir>test/globalTeardown.js',

    testRunner: 'jest-jasmine2',

    coverageDirectory: join(process.env.PROJECT_ROOT, '/build/artifacts/vue3/jest'),

    collectCoverageFrom: [
        'src/**/*.js',
        'src/**/*.ts',
        '!src/**/*.spec.js',
        '!src/**/*.spec.vue3.js',
    ],

    coverageReporters: [
        'text',
        'cobertura',
        'html-spa',
    ],

    setupFilesAfterEnv: [
        resolve(join(__dirname, '/test/_setup/prepare_vue3_environment.js')),
    ],

    transform: {
        // stringify svg imports
        '.*\\.(svg)$': '<rootDir>/test/transformer/svgStringifyTransformer.js',
    },

    transformIgnorePatterns: [
        '/node_modules/(?!(@shopware-ag/meteor-icon-kit|uuidv7|@vue/compat|other)/)',
    ],

    moduleNameMapper: {
        '^test(.*)$': '<rootDir>/test$1',
        '^\@shopware-ag\/admin-extension-sdk\/es\/(.*)': '<rootDir>/node_modules/@shopware-ag/admin-extension-sdk/umd/$1',
        '^lodash-es$': 'lodash',
        vue$: '@vue/compat/dist/vue.cjs.js',
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
    ],

    testMatch: [
        '<rootDir>/src/**/*.spec.vue3.js',
    ],

    testEnvironmentOptions: {
        customExportConditions: ['node', 'node-addons'],
    },
};
