// This is a karma config file. For more details see
//   http://karma-runner.github.io/0.13/config/configuration-file.html
// we are also using it with karma-webpack
//   https://github.com/webpack/karma-webpack
const webpackConfig = require('../../build/webpack.test.conf');
const path = require('path');

const artifactsPath = path.join(__dirname, '../../../../../../../../../build/artifacts');

module.exports = function (config) {
    config.set({
        // to run in additional browsers:
        // 1. install corresponding karma launcher
        //    http://karma-runner.github.io/3.0/config/browsers.html
        // 2. add it to the `browsers` array below.
        browsers: ['headlessChrome'],
        browserNoActivityTimeout: 100000, // default 10,000ms
        browserDisconnectTolerance: 5, // default 0
        retryLimit: 5, // default 2
        frameworks: ['mocha', 'sinon-chai'],
        reporters: ['spec', 'coverage', 'junit'],
        files: [
            './index.js'
        ],
        customLaunchers: {
            headlessChrome: {
                base: 'Chrome',
                flags: [
                    '--no-sandbox', // required to run without privileges in docker
                    '--disable-gpu',
                    '--headless',
                    '--remote-debugging-port=9222'
                ]
            }
        },
        client: {
            captureConsole: (process.env.TESTING_ENV === 'watch')
        },
        preprocessors: {
            './index.js': ['webpack', 'sourcemap']
        },
        webpack: webpackConfig,
        webpackMiddleware: {
            stats: 'errors-only'
        },
        coverageReporter: {
            dir: artifactsPath,
            reporters: [
                { type: 'lcov', subdir: '.' },
                { type: 'clover', subdir: '.', file: 'administration.clover.xml' },
                { type: 'text-summary' }
            ]
        },
        junitReporter: {
            useBrowserName: false,
            outputDir: artifactsPath,
            outputFile: 'administration.junit.xml'
        },
        proxies: {
            '/api': {
                target: `${process.env.APP_URL}/api`,
                changeOrigin: true
            }
        }
    });
};
