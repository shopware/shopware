// This is a karma config file. For more details see
//   http://karma-runner.github.io/0.13/config/configuration-file.html
// we are also using it with karma-webpack
//   https://github.com/webpack/karma-webpack
const webpackConfig = require('../../build/webpack.test.conf');
const process = require('process');

module.exports = function (config) {
    config.set({
        // to run in additional browsers:
        // 1. install corresponding karma launcher
        //    http://karma-runner.github.io/0.13/config/browsers.html
        // 2. add it to the `browsers` array below.
        browsers: [ 'ChromeHeadless', 'PhantomJS' ],
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
        coverageReporter: {
            dir: './coverage',
            reporters: [
                { type: 'lcov', subdir: '.' },
                { type: 'text-summary' }
            ]
        }
    })
};
