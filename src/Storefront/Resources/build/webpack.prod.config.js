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
                    loader: MiniCssExtractPlugin.loader
                },
                {
                    loader: 'css-loader'
                },
                {
                    loader: 'postcss-loader'
                },
                {
                    loader: 'sass-loader'
                }
            ]
        }
    ]
};

/**
 * Webpack plugins
 * https://webpack.js.org/configuration/plugins/#plugins
 * @type {*[]}
 */
const plugins = [
    new MiniCssExtractPlugin({
        filename: "css/main.bundle.css",
        chunkFilename: "css/main.bundle.css"
    })
];

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
    optimization: optimization,
    plugins: plugins
};