const fs = require('fs');
const webpack = require('webpack');
const merge = require('webpack-merge');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const OptimizeCSSAssetsPlugin = require('optimize-css-assets-webpack-plugin');
const WebpackPluginInjector = require('@shopware/webpack-plugin-injector');
const config = require('../config');
const utils = require('./utils');
const env = process.env.NODE_ENV === 'testing'
    ? require('../config/test.env')
    : config.build.env;

const baseWebpackConfig = require('./webpack.base.conf');

let webpackConfig = merge(baseWebpackConfig, {
    mode: 'production',
    entry: {
        commons: [
            // Ensure vue-loader's runtime components will always be packaged into vendors-node, even if no single file
            // components are present. This is required because when packaging a plugin using administration:build, the
            // SplitChunksPlugin configuration in webpack.base.conf.js will force the vue-loader runtime into the
            // vendors-node chunk, which will prevent it from being packaged in the plugin bundle, which will in turn
            // prevent that plugin bundle from loading on a Shopware instance which does not have the vue-loader runtime
            // in its vendors-node bundle. Because of this, stock Shopware must ship the runtime.
            require.resolve('vue-loader/lib/runtime/componentNormalizer.js')
        ]
    },
    module: {
        rules: utils.styleLoaders({
            sourceMap: config.build.productionSourceMap
        })
    },
    optimization: {
        splitChunks: {
            minSize: 0
        },
        minimizer: [
            new TerserPlugin({
                terserOptions: {
                    ecma: 6,
                    warnings: false
                },
                cache: true,
                parallel: true,
                sourceMap: false
            }),
            new OptimizeCSSAssetsPlugin()
        ]
    },
    devtool: config.build.productionSourceMap ? '#source-map' : false,
    output: {
        path: config.build.assetsRoot,
        filename: utils.assetsPath('js/[name].js'),
        chunkFilename: utils.assetsPath('js/[name].js')
    },
    plugins: [
        // http://vuejs.github.io/vue-loader/en/workflow/production.html
        new webpack.DefinePlugin({
            'process.env': env
        }),
        // extract css into its own file
        new MiniCssExtractPlugin({
            filename: utils.assetsPath('css/[name].css')
        }),
        // copy custom static assets
        new CopyWebpackPlugin([
            {
                from: utils.resolve('static'),
                to: config.build.assetsSubDirectory,
                ignore: ['.*']
            }
        ])
    ]
});

// Inject plugins into webpack config
const injector = new WebpackPluginInjector('var/plugins.json', webpackConfig, 'administration');
webpackConfig = merge(injector.webpackConfig);

if (config.build.bundleAnalyzerReport) {
    const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin; // eslint-disable-line
    webpackConfig.plugins.push(new BundleAnalyzerPlugin());
}

module.exports = webpackConfig;
