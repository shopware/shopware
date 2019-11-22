// see http://vuejs-templates.github.io/webpack for documentation.
const path = require('path');
const process = require('process');

let appPath = process.argv.slice(2)[0];
const appEnv = process.env.APP_ENV;

module.exports = {
    build: {
        env: require('./prod.env'), // eslint-disable-line
        index: path.resolve(__dirname, '../src/app/index.html'),
        assetsRoot: path.resolve(__dirname, '../../../public/'),
        assetsSubDirectory: 'static',
        assetsPublicPath: `${appPath}/bundles/administration/`,
        productionSourceMap: true,
        productionGzipExtensions: ['js', 'css'],
        // Run the build command with an extra argument to
        // View the bundle analyzer report after build finishes:
        // `npm run build --report`
        // Set to `true` or `false` to always turn it on or off
        bundleAnalyzerReport: process.env.npm_config_report,
        performanceHints: false
    },
    dev: {
        env: require('./dev.env'), // eslint-disable-line
        port: 8080,
        autoOpenBrowser: true,
        assetsSubDirectory: 'static',
        assetsPublicPath: '/',
        performanceHints: false,
        openInEditor: (appEnv === 'default'),
        editor: 'phpstorm',
        // CSS Sourcemaps off by default because relative paths are "buggy"
        // with this option, according to the CSS-Loader README
        // (https://github.com/webpack/css-loader#sourcemaps)
        // In our experience, they generally work as expected,
        // just be aware of this issue when enabling this option.
        cssSourceMap: false
    }
};
