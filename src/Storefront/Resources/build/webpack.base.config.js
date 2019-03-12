const webpack = require('webpack');
const WebpackBar = require('webpackbar');
const StyleLintPlugin = require('stylelint-webpack-plugin');
const {resolve} = require('path');
const buildDirectory = resolve(process.env.PROJECT_ROOT, 'public');
const CopyPlugin = require('copy-webpack-plugin');

const publicPath = `${process.env.APP_URL}${(process.env.ENV === 'watch') ? ':9999' : ''}/`;

/**
 * -------------------------------------------------------
 * GENERAL WEBPACK CONFIGURATIONS
 * -------------------------------------------------------
 * Impacts all kind of environment modes (dev|watch|hot|prod)
 * Please be careful in case of modifiying this file
 * https://webpack.js.org/configuration
 * -------------------------------------------------------
 */

/**
 * Configuration of the applications entrypoints
 * https://webpack.js.org/configuration/entry-context#entry
 * @type {{main: string}}
 */
const entries = {
    main: './asset/script/base.js'
};

/**
 * Options how webpack should output the compiled build
 * https://webpack.js.org/configuration/output
 * @type {{path: *, filename: string, publicPath: string}}
 */
const output = {
    path: buildDirectory,
    filename: 'js/main.bundle.js',
    publicPath: publicPath
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
                    options: {
                        presets: ['@babel/preset-env']
                    }
                },
                {
                    loader: 'eslint-loader'
                }
            ]
        },
        {
            test: /\.(woff(2)?|ttf|eot|svg)(\?v=\d+\.\d+\.\d+)?$/,
            use: [
                {
                    loader: 'file-loader',
                    options: {
                        name: '[name].[ext]',
                        outputPath: 'css/fonts',
                        publicPath: '/css/fonts'
                    }
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
    new webpack.NoEmitOnErrorsPlugin(),
    new WebpackBar({
        name: 'Shopware Next Storefront'
    }),
    new StyleLintPlugin(),
    new CopyPlugin([
        {
            from: 'asset/img',
            to: 'img'
        }
    ])
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
 * Export the webpack configuration
 */
module.exports = {
    cache: true,
    devServer: devServer,
    devtool: 'inline-cheap-source-map',
    entry: entries,
    mode: 'development',
    module: modules,
    name: 'shopware-next-storefront',
    optimization: optimization,
    output: output,
    performance: {
        hints: false
    },
    plugins: plugins,
    stats: {
        colors: true
    },
    target: 'web'
};
