require('./check-versions')();

const config = require('../config');
if (!process.env.NODE_ENV) {
    process.env.NODE_ENV = JSON.parse(config.dev.env.NODE_ENV)
}

const opn = require('opn');
const path = require('path');
const express = require('express');
const webpack = require('webpack');
const openInEditor = require('launch-editor-middleware');
const webpackConfig = process.env.NODE_ENV === 'testing'
    ? require('./webpack.prod.conf')
    : require('./webpack.dev.conf');

// default port where dev server listens for incoming traffic
const port = process.env.PORT || config.dev.port;

// automatically open browser, if not set will be false
const autoOpenBrowser = !!config.dev.autoOpenBrowser;

// Define HTTP proxies to your custom API backend
// https://github.com/chimurai/http-proxy-middleware
const proxyTable = config.dev.proxyTable;

const app = express();
const compiler = webpack(webpackConfig);

// Open files in phpstorm while using the dev mode, the sw-devmode-loader needs to be in place
app.use('/__open-in-editor', openInEditor('phpstorm'));

const devMiddleware = require('webpack-dev-middleware')(compiler, {
  publicPath: webpackConfig.output.publicPath,
});

const hotMiddleware = require('webpack-hot-middleware')(compiler, {
    log: () => {}
});

// force page reload when html-webpack-plugin template changes
compiler.hooks.compilation.tap('vue-webpack-template-reload-after-html-changes', (compilation) => {
    compilation.hooks.htmlWebpackPluginBeforeHtmlProcessing.tapAsync('vue-webpack-template-reload-after-html-changes', (data, cb) => {
        hotMiddleware.publish({ action: 'reload' });
        cb();
    });
});

// handle fallback for HTML5 history API
app.use(require('connect-history-api-fallback')());

// serve webpack bundle output
app.use(devMiddleware);

// enable hot-reload and state-preserving
// compilation error display
app.use(hotMiddleware);

// serve pure static assets
const staticPath = path.posix.join(config.dev.assetsPublicPath, config.dev.assetsSubDirectory);
app.use(staticPath, express.static('./static'));

const uri = 'http://localhost:' + port;

console.log('# Compiling Webpack configuration');
console.log(`Environment: ${process.env.NODE_ENV}`);
console.log(`Dev server URI: ${uri}`);
console.log(`Assets static path: ${staticPath}`);
console.log(`Automatically open browser: ${autoOpenBrowser}`);
console.log();

console.log('# Starting hot module reloading dev server');

devMiddleware.waitUntilValid(function () {
    console.log('Dev server listening at ' + uri + '\n');
});

module.exports = app.listen(port, function (err) {
    if (err) {
        console.log(err);
        return
    }

    // when env is testing, don't need open it
    if (autoOpenBrowser && process.env.NODE_ENV !== 'testing') {
        opn(uri)
    }
});
