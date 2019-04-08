// For a detailed explanation regarding each configuration property, visit:
// https://jestjs.io/docs/en/configuration.html
const { join } = require('path');

const artifactsPath = join(process.env.PROJECT_ROOT, '/build/artifacts');

module.exports = {
    clearMocks: true,
    collectCoverage: true,
    coverageDirectory: artifactsPath,
    coverageReporters: [
        'lcov',
        'text',
        'clover'
    ],

    moduleFileExtensions: [
        'js'
    ],

    moduleNameMapper: {
        '\\.(css|less|scss)$': '<rootDir>/test/@tool/__mocks__/styleMock.js',
        '^src(.*)$': '<rootDir>/src$1'
    },

    reporters: [
        'default',
        ['jest-junit', {
            suiteName: 'Shopware Next Unit Tests',
            outputDirectory: artifactsPath,
            outputName: 'administration.junit.xml'
        }]
    ],

    transform: {
        '^.+\\.jsx?$': 'babel-jest',
        '^.+\\.twig$': '<rootDir>/test/@tool/twig-to-vue-transformer/index.js'
    },

    testMatch: [
        '<rootDir>/test/**/*.spec.js'
    ]
};
