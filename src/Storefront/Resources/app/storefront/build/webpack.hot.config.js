const webpack = require('webpack');
const { join } = require('path');
const { existsSync } = require('fs');
const FriendlyErrorsWebpackPlugin = require('friendly-errors-webpack-plugin');
const utils = require('./utils');

/**
 * -------------------------------------------------------
 * WEBPACK CONFIGURATIONS
 * -------------------------------------------------------
 * Impacts development hot mode
 * https://webpack.js.org/configuration
 * -------------------------------------------------------
 */

const themeFilesConfigPath = join(utils.getProjectRootPath(), 'var/theme-files.json');
if (!existsSync(themeFilesConfigPath)) {
    throw new Error(`File "${themeFilesConfigPath}" not found`);
}

// eslint-disable-next-line
const themeFiles = require(themeFilesConfigPath);

// Search for "overrides.scss" entry point in "theme-files.json" content
const overridesEntry = utils.getScssEntryByName(themeFiles.style, 'scss/overrides.scss');

/**
 * Additional SCSS resources for "sass-resources-loader"
 * https://www.npmjs.com/package/sass-resources-loader
 * @type {string[]}
 */
const scssResources = utils.getScssResources(
    [
        // Dumped theme variables
        join(utils.getProjectRootPath(), 'var/theme-variables.scss'),

        // Storefront & vendor variables + mixins + functions
        join(__dirname, '..', 'src/scss/variables.scss'),
    ],
    overridesEntry,
    'overrides.scss'
);

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
                    loader: 'style-loader',
                },
                {
                    loader: 'css-loader',
                },
                {
                    loader: 'postcss-loader', // needs to be AFTER css/style-loader and BEFORE sass-loader
                    options: {
                        config: {
                            path: join(__dirname, '..'),
                        },
                    },
                },
                {
                    loader: 'sass-loader',
                },
                // Provides our theme variables to the hot replacement mode
                {
                    loader: 'sass-resources-loader',
                    options: {
                        resources: scssResources,
                    },
                },
            ],
        },
        {
            test: /\.(woff(2)?|ttf|eot|svg|otf)$/,
            use: [
                {
                    loader: 'file-loader',
                    options: {
                        name: '[name].[ext]',
                        outputPath: 'fonts/',
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
    new FriendlyErrorsWebpackPlugin(),
    new webpack.HotModuleReplacementPlugin(),
];

/**
 * Options for the webpack-dev-server (e.g. for HMR mode)
 * https://webpack.js.org/configuration/dev-server#devserver
 * @type {{}}
 */
const devServer = {
    contentBase: utils.getBuildPath(),
    publicPath: utils.getPublicPath(),
    open: false,
    overlay: {
        warnings: false,
        errors: true,
    },
    stats: {
        colors: true,
    },
    quiet: true,
    hot: true,
    compress: false,
    disableHostCheck: true,
    port: 9999,
    host: '0.0.0.0',
    clientLogLevel: 'warning',
    headers: {
        'Access-Control-Allow-Origin': '*',
    },
};

/**
 * Export the webpack configuration
 */
const config = {
    devServer: devServer,
    devtool: 'cheap-module-eval-source-map',
    mode: 'development',
    module: modules,
    entry: {
        app: [utils.getPath('/src/scss/base.scss')],
        storefront: [],
    },
    plugins: plugins,
};

config.entry.storefront = [...themeFiles.script, ...themeFiles.style].map((file) => {
    return file.filepath;
});

module.exports = config;
