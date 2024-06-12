/**
 * This module creates a live reload server for the Shopware storefront.
 */

module.exports = function createLiveReloadServer(socketPath) {
    return new Promise((resolve, reject) => {
        const webpack = require('webpack');
        const WebpackDevServer = require('webpack-dev-server');
        const webpackConfig = require('../../webpack.config');
        webpackConfig[0].devServer.ipc = socketPath;

        const compiler = webpack(webpackConfig);

        const devServerOptions = Object.assign({}, webpackConfig[0].devServer, {
            open: false,
            devMiddleware: {
                stats: {
                    colors: true,
                },
            },
        });

        // start the normal webpack dev server for hot reloading the files
        const server = new WebpackDevServer(devServerOptions, compiler);

        (async () => {
            try {
                await server.start();
            } catch (error) {
                reject(error);
            }

            // eslint-disable-next-line no-console
            console.log('Starting the hot reload server: \n');
        })();

        compiler.hooks.done.tap('resolveServer', () => {
            resolve(server);
        });
    });
};
