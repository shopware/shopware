const webpack = require('webpack');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const WebpackBar = require('webpackbar');
const StyleLintPlugin = require('stylelint-webpack-plugin');
const babelrc = require('../.babelrc');
const utils = require('./utils');

const outputFolder = utils.getOutputPath();
const buildDirectory = utils.getBuildPath();

/**
 * -------------------------------------------------------
 * GENERAL WEBPACK CONFIGURATIONS
 * -------------------------------------------------------
 * Impacts all kind of environment modes (dev|watch|hot|prod)
 * Please be careful in case of modifying this file
 * https://webpack.js.org/configuration
 * -------------------------------------------------------
 */

const context = utils.getPath('src');

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
 * Option to disable ESLint during storefront build process.
 * @type {boolean}
 */
const { ESLINT_DISABLE = 'false', MODE = 'dev' } = process.env;

const jsRules = {
    test: /\.m?js$/,
    exclude: /(node_modules|bower_components|vendors)\/(?!(are-you-es5|eslint-plugin-cypress|fs-extra|nunito-fontface|query-string|split-on-first)\/).*/,
    use: [
        {
            loader: 'babel-loader',
            options: babelrc,
        },
    ],
};

if (MODE !== 'hot' || ESLINT_DISABLE !== 'true') {
    jsRules.use.push({
        loader: 'eslint-loader',
        options: {
            configFile: utils.getPath('.eslintrc.js'),
            fix: true,
        },
    });
}

/**
 * Webpack module configuration and how them will be treated
 * https://webpack.js.org/configuration/module
 * @type {{rules: *[]}}
 */
const modules = {
    rules: [
        jsRules,
        {
            test: /\.(woff(2)?|ttf|eot|svg)(\?v=\d+\.\d+\.\d+)?$/,
            include: [
                utils.getPath('vendor/Inter-3.5/font'),
            ],
            use: [
                {
                    loader: 'file-loader',
                    options: {
                        name: '[name].[ext]',
                        outputPath: 'assets/font',
                        publicPath: '../assets/font',
                    },
                },
            ],
        },
        {
            test: /\.(jp(e)g|png|gif|svg)(\?v=\d+\.\d+\.\d+)?$/,
            exclude: [
                utils.getPath('vendor/Inter-3.5/font'),
            ],
            use: [
                {
                    loader: 'file-loader',
                    options: {
                        name: '[name].[ext]',
                        outputPath: 'assets/img',
                        publicPath: '../assets/img',
                    },
                },
            ],
        },
        // Expose jQuery to the global scope for plugins which don't want to use Webpack
        {
            test: require.resolve('jquery/dist/jquery.slim'),
            use: [{
                loader: 'expose-loader',
                options: 'jQuery',
            }, {
                loader: 'expose-loader',
                options: '$',
            }],
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
        Popper: ['popper.js', 'default'],
    }),
    new WebpackBar({
        name: 'Shopware 6 Storefront',
    }),
    new StyleLintPlugin({
        context: utils.getPath('src/scss'),
        syntax: 'scss',
        fix: true,
    }),
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
const optimization = {
    moduleIds: 'hashed',
    chunkIds: 'named',
    runtimeChunk: {
        name: 'runtime',
    },
    splitChunks: {
        minSize: 0,
        minChunks: 1,
        cacheGroups: {
            'vendor-node': {
                enforce: true,
                test: utils.getPath('node_modules'),
                name: 'vendor-node',
                chunks: 'all',
            },
            'vendor-shared': {
                enforce: true,
                test: (content) => {
                    if (!content.resource) {
                        return false;
                    }
                    if (content.resource.includes(utils.getPath('src/plugin-system'))
                        || content.resource.includes(utils.getPath('src/helper'))
                        || content.resource.includes(utils.getPath('src/utility'))
                        || content.resource.includes(utils.getPath('src/service'))) {
                        return true;
                    }
                    return false;
                },
                name: 'vendor-shared',
                chunks: 'all',
            },
        },
    },
};

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
    extensions: [ '.js', '.jsx', '.json', '.less', '.sass', '.scss', '.twig' ],
    modules: [
        // statically add the storefront node_modules folder, so sw plugins can resolve it
        utils.getPath('node_modules'),
    ],
    alias: {
        src: utils.getPath('src'),
        assets: utils.getPath('assets'),
        jquery: 'jquery/dist/jquery.slim',
        scss: utils.getPath('src/scss'),
        vendor: utils.getPath('vendor'),
    },
};

/**
 * Export the webpack configuration
 */
module.exports = {
    cache: true,
    devServer: devServer,
    devtool: 'inline-cheap-source-map',
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
