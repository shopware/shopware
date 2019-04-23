if (!process.env.PROJECT_ROOT) {
    process.env.PROJECT_ROOT = '../../../../..';
}

const webpack = require('webpack');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const WebpackBar = require('webpackbar');
const StyleLintPlugin = require('stylelint-webpack-plugin');
const path = require('path');
const buildDirectory = path.resolve(process.env.PROJECT_ROOT, 'public');
const CopyPlugin = require('copy-webpack-plugin');
const publicPath = `${process.env.APP_URL}${(process.env.ENV === 'watch') ? ':9999' : ''}/`;
const babelrc = require('../.babelrc');

/**
 * helper function to get a path relative to the root folder
 *
 * @param dir
 * @return {string}
 */
function getPath(dir) {
    const basePath = path.join(__dirname, '..');
    if (dir) {
        return path.join(basePath, dir);
    }

    return basePath;
}

/**
 * -------------------------------------------------------
 * GENERAL WEBPACK CONFIGURATIONS
 * -------------------------------------------------------
 * Impacts all kind of environment modes (dev|watch|hot|prod)
 * Please be careful in case of modifiying this file
 * https://webpack.js.org/configuration
 * -------------------------------------------------------
 */

const context = getPath('src/script');

/**
 * Configuration of the applications entrypoints
 * https://webpack.js.org/configuration/entry-context#entry
 *
 * relative to the webpack context
 *
 * @type {{main: string}}
 */
const entries = {
    main: './base.js',
};

/**
 * Options how webpack should output the compiled build
 * https://webpack.js.org/configuration/output
 * @type {{path: *, filename: string, publicPath: string}}
 */
const output = {
    path: buildDirectory,
    filename: 'js/main.bundle.js',
    publicPath: publicPath,
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
                        fix: true,
                    },
                },
            ],
        },
        {
            test: /\.(woff(2)?|ttf|eot|svg)(\?v=\d+\.\d+\.\d+)?$/,
            use: [
                {
                    loader: 'file-loader',
                    options: {
                        name: '[name].[ext]',
                        outputPath: 'fonts',
                        publicPath: './../fonts',
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
    new WebpackBar({
        name: 'Shopware Next Storefront',
    }),
    new StyleLintPlugin({
        context: getPath('src/style'),
        syntax: 'scss',
        fix: true,
    }),
    new CopyPlugin([
        {
            from: getPath('assets/media'),
            to: 'img',
        },
    ]),
    new MiniCssExtractPlugin({
        filename: 'css/main.bundle.css',
        chunkFilename: 'css/main.bundle.css',
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
    alias: {
        src: getPath('src'),
        assets: getPath('assets'),
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
    stats: {
        colors: true,
    },
    target: 'web',
};
