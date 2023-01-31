/**
 * This module creates an live reload server for the Shopware storefront.
 */

module.exports = function createLiveReloadServer() {
    return new Promise((resolve, reject) => {
        const webpack = require('webpack');
        const WebpackDevServer = require('webpack-dev-server');
        const webpackConfig = require('../../webpack.config');

        const compiler = webpack(webpackConfig);

        const devServerOptions = Object.assign({}, webpackConfig.devServer, {
            open: false,
            devMiddleware: {
                stats: {
                    colors: true,
                },
            },
        });

        // start the normal webpack dev server for hot reloading the files
        const server = new WebpackDevServer(compiler, devServerOptions);

        server.listen(devServerOptions.port, '0.0.0.0', (err) => {
            if (err) {
                reject(err);
            }

            console.log('Starting the hot reload server: \n');
        });

        compiler.hooks.done.tap('resolveServer', () => {
            resolve(server);
        });
    });
};

