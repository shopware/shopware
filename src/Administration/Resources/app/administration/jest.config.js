// For a detailed explanation regarding each configuration property, visit:
// https://jestjs.io/docs/en/configuration.html
const { join, resolve } = require('path');

module.exports = {
    preset: '@shopware-ag/jest-preset-sw6-admin',
    globals: {
        adminPath: process.env.ADMIN_PATH
    },

    coverageDirectory: join(process.env.PROJECT_ROOT, '/build/artifacts'),

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
            outputDirectory: join(process.env.PROJECT_ROOT, '/build/artifacts'),
            outputName: 'administration.junit.xml'
        }]
    ]
};
