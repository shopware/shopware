// This is a karma config file. For more details see
//   http://karma-runner.github.io/0.13/config/configuration-file.html
// we are also using it with karma-webpack
//   https://github.com/webpack/karma-webpack
const webpackConfig = require('../../build/webpack.test.conf');
const process = require('process');
const path = require('path');

const artifactsPath = path.join(__dirname, '../../../../../../build/artifacts');

module.exports = function (config) {
    config.set({
        // to run in additional browsers:
        // 1. install corresponding karma launcher
        //    http://karma-runner.github.io/0.13/config/browsers.html
        // 2. add it to the `browsers` array below.
        browsers: [ 'ChromeHeadlessNoSandbox' ],
        frameworks: [ 'mocha', 'sinon-chai', 'chai' ],
        reporters: [ 'spec', 'coverage', 'junit' ],
        files: [
            './index.js'
        ],
        client: {
            captureConsole: (process.env.TESTING_ENV === 'watch' ? true : false)
        },
        preprocessors: {
            './index.js': [ 'webpack', 'sourcemap' ]
        },
        webpack: webpackConfig,
        webpackMiddleware: {
            noInfo: true
        },
        customLaunchers: {
            ChromeHeadlessNoSandbox: {
                base: 'ChromeHeadless',
                flags: [
                    '--no-sandbox', // required to run without privileges in docker
                    '--user-data-dir=/tmp/chrome-test-profile',
                    '--disable-web-security'
                ]
            }
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
        }
    });
};
