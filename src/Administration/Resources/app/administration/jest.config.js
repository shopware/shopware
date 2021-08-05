// For a detailed explanation regarding each configuration property, visit:
// https://jestjs.io/docs/en/configuration.html
const { join, resolve } = require('path');

process.env.PROJECT_ROOT = process.env.PROJECT_ROOT || process.env.INIT_CWD;
process.env.ADMIN_PATH = process.env.ADMIN_PATH || __dirname;

console.debug(process.env.PROJECT_ROOT);

module.exports = {
    preset: '@shopware-ag/jest-preset-sw6-admin',
    globals: {
        adminPath: process.env.ADMIN_PATH,
        projectRoot: process.env.PROJECT_ROOT
    },

    globalTeardown: '<rootDir>test/globalTeardown.js',

    coverageDirectory: join(process.env.PROJECT_ROOT, '/build/artifacts/jest'),

    collectCoverageFrom: [
        'src/**/*.js'
    ],

    coverageReporters: [
        'text',
        'cobertura',
        'html-spa'
    ],

    setupFilesAfterEnv: [
        resolve(join(__dirname, '/test/_setup/prepare_environment.js'))
    ],

    moduleNameMapper: {
        '^test(.*)$': '<rootDir>/test$1',
        vue$: 'vue/dist/vue.common.dev.js'
    },

    reporters: [
        'default',
        ['jest-junit', {
            suiteName: 'Shopware 6 Unit Tests',
            outputDirectory: join(process.env.PROJECT_ROOT, '/build/artifacts/jest'),
            outputName: 'administration.junit.xml'
        }]
    ]
};
