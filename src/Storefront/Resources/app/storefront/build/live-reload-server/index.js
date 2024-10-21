/* eslint no-console: 0 */

/**
 * This module creates a live reload server for the Shopware storefront.
 */
module.exports = function createLiveReloadServer(sslOptions) {
    return new Promise((resolve, reject) => {
        const webpack = require('webpack');
        const WebpackDevServer = require('webpack-dev-server');
        const webpackConfig = require('../../webpack.config');

        const compiler = webpack(webpackConfig);

        let serverConfig = {
            type: 'http',
        };
        if (Object.keys(sslOptions).length !== 0) {
            serverConfig = {
                type: 'https',
                options: sslOptions,
            };
        }

        const devServerOptions = Object.assign({}, webpackConfig[0].devServer, {
            open: false,
            host: '0.0.0.0',
            server: serverConfig,
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

            console.log('Starting the hot reload server: \n');
        })();

        compiler.hooks.done.tap('resolveServer', () => {
            resolve(server);
        });
    });
};
