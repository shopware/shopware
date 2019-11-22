const FriendlyErrorsWebpackPlugin = require('friendly-errors-webpack-plugin');

/**
 * -------------------------------------------------------
 * WEBPACK CONFIGURATIONS
 * -------------------------------------------------------
 * Impacts development mode (dev|watch)
 * https://webpack.js.org/configuration
 * -------------------------------------------------------
 */

/**
 * Webpack plugins
 * https://webpack.js.org/configuration/plugins/#plugins
 * @type {*[]}
 */
const plugins = [
    new FriendlyErrorsWebpackPlugin(),
];

/**
 * Export the webpack configuration
 */
module.exports = {
    devtool: 'cheap-module-eval-source-map',
    mode: 'development',
    plugins: plugins,
};
