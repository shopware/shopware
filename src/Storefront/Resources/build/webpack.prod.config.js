const TerserPlugin = require('terser-webpack-plugin');

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
    minimizer: [
        new TerserPlugin({
            terserOptions: {
                ecma: 6,
                warnings: false,
            },
            cache: true,
            parallel: true,
            sourceMap: true,
        }),
    ],
};

/**
 * Export the webpack configuration
 */
module.exports = {
    devtool: 'source-map',
    mode: 'production',
    optimization: optimization,
};
