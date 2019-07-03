const webpack = require('webpack');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const WebpackBar = require('webpackbar');
const StyleLintPlugin = require('stylelint-webpack-plugin');
const CopyPlugin = require('copy-webpack-plugin');
const babelrc = require('../.babelrc');
const utils = require('./utils');

const outputFolder = utils.getOutputPath();
const buildDirectory = utils.getBuildPath();
const assetOutPutFolder = `${outputFolder}/assets`;

/**
 * -------------------------------------------------------
 * GENERAL WEBPACK CONFIGURATIONS
 * -------------------------------------------------------
 * Impacts all kind of environment modes (dev|watch|hot|prod)
 * Please be careful in case of modifiying this file
 * https://webpack.js.org/configuration
 * -------------------------------------------------------
 */

const context = utils.getPath('src/script');

/**
 * Configuration of the applications entry points
 * https://webpack.js.org/configuration/entry-context#entry
 *
 * relative to the webpack context
 *
 * @type {{main: string}}
 */
const entries = {
    app: './base.js',
};

/**
 * Options how webpack should output the compiled build
 * https://webpack.js.org/configuration/output
 * @type {{path: *, filename: string, publicPath: string}}
 */
const output = {
    path: buildDirectory,
    filename: `${outputFolder}/js/[name].js`,
    publicPath: utils.getPublicPath(),
    chunkFilename: `${outputFolder}/js/[name].js`,
};

/**
 * Webpack module configuration and how them will be treated
 * https://webpack.js.org/configuration/module
 * @type {{rules: *[]}}
 */
const modules = {
    rules: [
        {
            test: /\.m?js$/,
            exclude: /(node_modules|bower_components|vendors)/,
            use: [
                {
                    loader: 'babel-loader',
                    options: babelrc,
                },
                {
                    loader: 'eslint-loader',
                    options: {
                        configFile: utils.getPath('.eslintrc.js'),
                        fix: true,
                    },
                },
            ],
        },
        {
            test: /\.(woff(2)?|ttf|eot|svg)(\?v=\d+\.\d+\.\d+)?$/,
            include: [
                utils.getPath('assets/font'),
            ],
            use: [
                {
                    loader: 'file-loader',
                    options: {
                        name: '[name].[ext]',
                        outputPath: `${assetOutPutFolder}/font`,
                        publicPath: '../assets/font',
                    },
                },
            ],
        },
        {
            test: /\.(jp(e)g|png|gif|svg)(\?v=\d+\.\d+\.\d+)?$/,
            exclude: [
                utils.getPath('assets/font'),
            ],
            use: [
                {
                    loader: 'file-loader',
                    options: {
                        name: '[name].[ext]',
                        outputPath: `${assetOutPutFolder}/img`,
                        publicPath: '../assets/img',
                    },
                },
            ],
        },
    ],
};

/**
 * Webpack plugins
 * https://webpack.js.org/configuration/plugins/#plugins
 * @type {*[]}
 */
const plugins = [
    new webpack.NoEmitOnErrorsPlugin(),
    new webpack.ProvidePlugin({
        $: require.resolve('jquery/dist/jquery.slim'),
        jQuery: require.resolve('jquery/dist/jquery.slim'),
        'window.jQuery': require.resolve('jquery/dist/jquery.slim'),
    }),
    new WebpackBar({
        name: 'Shopware 6 Storefront',
    }),
    new StyleLintPlugin({
        context: utils.getPath('src/style'),
        syntax: 'scss',
        fix: true,
    }),
    new CopyPlugin([
        {
            from: utils.getPath('assets'),
            to: assetOutPutFolder,
        },
    ]),
    new MiniCssExtractPlugin({
        filename: `${outputFolder}/css/[name].css`,
        chunkFilename: `${outputFolder}/css/[name].css`,
    }),
];

/**
 * Optimizations configuration
 * https://webpack.js.org/configuration/optimization
 * @type {{}}
 */
const optimization = {};

/**
 * Options for the webpack-dev-server (e.g. for HMR mode)
 * https://webpack.js.org/configuration/dev-server#devserver
 * @type {{}}
 */
const devServer = {};

/**
 * Options for the import resolver
 * https://webpack.js.org/configuration/resolve
 * @type {{}}}
 */
const resolve = {
    extensions: ['.js', '.jsx', '.json', '.less', '.sass', '.scss', '.twig'],
    modules: [
        // statically add the storefront node_modules folder, so sw plugins can resolve it
        utils.getPath('node_modules'),
    ],
    alias: {
        src: utils.getPath('src'),
        assets: utils.getPath('assets'),
        jquery: 'jquery/dist/jquery.slim',
        scss: utils.getPath('src/style'),
    },
};

/**
 * Export the webpack configuration
 */
module.exports = {
    cache: true,
    devServer: devServer,
    devtool: 'inline-cheap-source-map',
    entry: entries,
    context: context,
    mode: 'development',
    module: modules,
    name: 'shopware-next-storefront',
    optimization: optimization,
    output: output,
    performance: {
        hints: false,
    },
    plugins: plugins,
    resolve: resolve,
    stats: 'minimal',
    target: 'web',
};
