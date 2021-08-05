// For a detailed explanation regarding each configuration property, visit:
// https://jestjs.io/docs/en/configuration.html
const path = require('path');
const { join } = require('path');

process.env.PROJECT_ROOT = process.env.PROJECT_ROOT || process.env.INIT_CWD;

const artifactsPath = join(process.env.PROJECT_ROOT, '/build/artifacts/jest');

module.exports = {

    // The directory where Jest should store its cached dependency information
    // cacheDirectory: "/private/var/folders/00/scx5qv4s0fzdh47cvtj6xrj80000gn/T/jest_dx",

    // Automatically clear mock calls and instances between every test
    clearMocks: true,

    globalTeardown: '<rootDir>test/globalTeardown.js',

    // The directory where Jest should output its coverage files
    collectCoverage: true,
    coverageDirectory: artifactsPath,
    coverageReporters: [
        'lcov',
        'text',
        'clover',
        'cobertura',
    ],

    collectCoverageFrom: [
        'src/**',
        'src/**/*.js',
        '!src/main.js',
        '!src/scss/**',
        '!src/vendor/**',
    ],

    // Fail testsuite if coverage is below given percentage
    coverageThreshold: {
        './src/helper': {
            statements: 85,
            branches: 85,
            functions: 85,
        },
    },

    // Automatically reset mock state between every test
    resetMocks: true,

    // Automatically restore mock state between every test
    restoreMocks: true,

    // The root directory that Jest should scan for tests and modules within
    rootDir: path.resolve(__dirname),

    // This option allows the use of a custom resolver.
    moduleNameMapper: {
        '^src/(.*)$': '<rootDir>/src/$1',
    },

    reporters: [
        'default',
        ['jest-junit', {
            suiteName: 'Shopware 6 Storefront Unit Tests',
            outputDirectory: artifactsPath,
            outputName: 'storefront.junit.xml',
        }],
    ],

    // The glob patterns Jest uses to detect test files
    testMatch: [
        '!**/test/e2e/**',
        '**/test/**/*.test.js',
        '**/test/*.test.js',
    ],

    transform: {
        '^.+\\.js$': 'babel-jest',
        '^.+\\.html$': 'html-loader-jest',
    },

    setupFiles: [
        '<rootDir>/jest.init.js',
    ],
};
