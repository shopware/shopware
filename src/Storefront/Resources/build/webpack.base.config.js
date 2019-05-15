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
const publicPath = `${process.env.APP_URL}${(process.env.MODE === 'hot') ? ':9999' : ''}/`;
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

const outPutFolder = '.';
const assetOutPutFolder = `${outPutFolder}/assets`;

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
    filename: `${outPutFolder}/js/app.js`,
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
            include: [
                getPath('assets/font'),
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
                getPath('assets/font'),
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
        name: 'Shopware Next Storefront',
    }),
    new StyleLintPlugin({
        context: getPath('src/style'),
        syntax: 'scss',
        fix: true,
    }),
    new CopyPlugin([
        {
            from: getPath('assets'),
            to: assetOutPutFolder,
        },
    ]),
    new MiniCssExtractPlugin({
        filename: `${outPutFolder}/css/app.css`,
        chunkFilename: `${outPutFolder}/css/app.css`,
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
        jquery: 'jquery/dist/jquery.slim',
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
