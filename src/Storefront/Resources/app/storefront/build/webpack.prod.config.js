const TerserPlugin = require('terser-webpack-plugin');
const utils = require('./utils');

/**
 * -------------------------------------------------------
 * WEBPACK CONFIGURATIONS
 * -------------------------------------------------------
 * Impacts production mode only
 * https://webpack.js.org/configuration
 * -------------------------------------------------------
 */


/**
 * Optimizations configuration
 * https://webpack.js.org/configuration/optimization
 * @type {{}}
 */
const optimization = {
    runtimeChunk: {
        name: 'runtime',
    },
    splitChunks: {
        cacheGroups: {
            vendor: {
                test: utils.getPath('node_modules'),
                name: 'vendors',
                chunks: 'all',
            },
        },
    },
    minimizer: [
        new TerserPlugin({
            terserOptions: {
                ecma: 6,
                warnings: false,
            },
            cache: true,
            parallel: true,
            sourceMap: false,
        }),
    ],
};

/**
 * Export the webpack configuration
 */
module.exports = {
    mode: 'production',
    devtool: 'none',
    optimization,
};
