const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const OptimizeCSSAssetsPlugin = require("optimize-css-assets-webpack-plugin");
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
 * Webpack module configuration and how them will be treated
 * https://webpack.js.org/configuration/module
 * @type {{rules: *[]}}
 */
const modules = {
    rules: [
        {
            test: /\.scss$/,
            use: [
                {
                    loader: MiniCssExtractPlugin.loader // extract css files from the js code
                },
                {
                    loader: 'css-loader'
                },
                {
                    loader: 'postcss-loader' // needs to be AFTER css/style-loader and BEFORE sass-loader
                },
                {
                    loader: 'sass-loader'
                }
            ]
        }
    ]
};

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
                warnings: false
            },
            cache: true,
            parallel: true,
            sourceMap: true
        }),
        new OptimizeCSSAssetsPlugin({})
    ]
};

/**
 * Export the webpack configuration
 */
module.exports = {
    devtool: 'source-map',
    mode: 'production',
    module: modules,
    optimization: optimization
};
