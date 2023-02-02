/**
 * @package admin
 */

// For a detailed explanation regarding each configuration property, visit:
// https://jestjs.io/docs/en/configuration.html
const { join, resolve } = require('path');

process.env.PROJECT_ROOT = process.env.PROJECT_ROOT || process.env.INIT_CWD || '.';
process.env.ADMIN_PATH = process.env.ADMIN_PATH || __dirname;
process.env.TZ = process.env.TZ || 'UTC';

module.exports = {
    cacheDirectory: '<rootDir>.jestcache',
    preset: '@shopware-ag/jest-preset-sw6-admin',
    globals: {
        adminPath: process.env.ADMIN_PATH,
        projectRoot: process.env.PROJECT_ROOT,
    },

    globalTeardown: '<rootDir>test/globalTeardown.js',

    testRunner: 'jest-jasmine2',

    coverageDirectory: join(process.env.PROJECT_ROOT, '/build/artifacts/jest'),

    collectCoverageFrom: [
        'src/**/*.js',
        'src/**/*.ts',
        '!src/**/*.spec.js',
        '!src/**/*.spec.ts',
    ],

    coverageReporters: [
        'text',
        'cobertura',
        'html-spa',
    ],

    setupFilesAfterEnv: [
        resolve(join(__dirname, '/test/_setup/prepare_environment.js')),
    ],

    transform: {
        // stringify svg imports
        '.*\\.(svg)$': '<rootDir>/test/transformer/svgStringifyTransformer.js',
    },

    transformIgnorePatterns: [
        'node_modules/(?!@shopware-ag/meteor-icon-kit|other)',
    ],

    moduleNameMapper: {
        '^test(.*)$': '<rootDir>/test$1',
        vue$: 'vue/dist/vue.common.dev.js',
        // Force module uuid to resolve with the CJS entry point, because Jest does not support package.json.exports.
        // See https://github.com/uuidjs/uuid/issues/451
        '^uuid$': require.resolve('uuid'),
        '^\@shopware-ag\/admin-extension-sdk\/es\/(.*)': '<rootDir>/node_modules/@shopware-ag/admin-extension-sdk/umd/$1',
        '^lodash-es$': 'lodash',
    },

    reporters: [
        'default',
        ['jest-junit', {
            suiteName: 'Shopware 6 Unit Tests',
            outputDirectory: join(process.env.PROJECT_ROOT, '/build/artifacts/jest'),
            outputName: 'administration.junit.xml',
        }],
    ],
};
