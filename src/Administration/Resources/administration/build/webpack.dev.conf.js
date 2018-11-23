const utils = require('./utils');
const webpack = require('webpack');
const config = require('../config');
const merge = require('webpack-merge');
const FriendlyErrorsPlugin = require('friendly-errors-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

let baseWebpackConfig = require('./webpack.base.conf');

const pluginList = utils.getPluginDefinitions('var/config_administration_plugins.json');
baseWebpackConfig = utils.iteratePluginDefinitions(baseWebpackConfig, pluginList);
baseWebpackConfig = utils.injectIncludePathsToLoader(baseWebpackConfig, utils.getIncludePaths());

let mergedWebpackConfig = merge(baseWebpackConfig, {
    mode: 'development',
    node: {
        __filename: true
    },
    module: {
        rules: utils.styleLoaders({ sourceMap: config.dev.cssSourceMap })
    },
    optimization: {
        splitChunks: {
            minSize: 0
        }
    },
    // cheap-module-eval-source-map is faster for development
    devtool: '#cheap-module-eval-source-map',
    plugins: [
        new webpack.DefinePlugin({
            'process.env': config.dev.env
        }),
        new MiniCssExtractPlugin({
            filename: utils.assetsPath('css/[name].css')
        }),
        // https://github.com/glenjamin/webpack-hot-middleware#installation--usage
        new webpack.HotModuleReplacementPlugin(),
        new webpack.NoEmitOnErrorsPlugin(),
        // https://github.com/ampedandwired/html-webpack-plugin
        utils.injectHtmlPlugin(
            baseWebpackConfig,
            utils.loadFeatureFlags(process.env.ENV_FILE)
        ),
        new FriendlyErrorsPlugin()
    ]
});

if (config.dev.openInEditor) {
    mergedWebpackConfig = utils.injectSwDevModeLoader(mergedWebpackConfig);
}
module.exports = mergedWebpackConfig;
