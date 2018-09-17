const fs = require('fs');
const utils = require('./utils');
const webpack = require('webpack');
const config = require('../config');
const merge = require('webpack-merge');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const UglifyJsPlugin = require('uglifyjs-webpack-plugin');
const OptimizeCSSAssetsPlugin = require('optimize-css-assets-webpack-plugin');
const WebpackCopyAfterBuildPlugin = require('./plugins/copy-after-build');
const env = process.env.NODE_ENV === 'testing'
    ? require('../config/test.env')
    : config.build.env;

let baseWebpackConfig = require('./webpack.base.conf');

const pluginList = utils.getPluginDefinitions('var/config_administration_plugins.json');
baseWebpackConfig = utils.iteratePluginDefinitions(baseWebpackConfig, pluginList, false);
baseWebpackConfig = utils.injectIncludePathsToLoader(baseWebpackConfig, utils.getIncludePaths());

const webpackConfig = merge(baseWebpackConfig, {
    mode: 'production',
    module: {
        rules: utils.styleLoaders({
            sourceMap: config.build.productionSourceMap
        })
    },
    /* optimization: {
        minimizer: [
            new UglifyJsPlugin({
                uglifyOptions: {
                    compress: {
                        warnings: false
                    }
                },
                cache: true,
                parallel: true,
                sourceMap: true
            }),
            new OptimizeCSSAssetsPlugin()
        ]
    }, */
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
                from:utils.resolve('static'),
                to: config.build.assetsSubDirectory,
                ignore: [ '.*' ]
            }
        ])
    ]
});

if (config.build.productionGzip) {
    const CompressionWebpackPlugin = require('compression-webpack-plugin');

    webpackConfig.plugins.push(
        new CompressionWebpackPlugin({
            asset: '[path].gz[query]',
            algorithm: 'gzip',
            test: new RegExp(
                '\\.(' +
                config.build.productionGzipExtensions.join('|') +
                ')$'
            ),
            threshold: 10240,
            minRatio: 0.8
        })
    )
}

if (pluginList.length) {
    pluginList.forEach((plugin) => {
        const pluginName = plugin.name;
        const basePath = plugin.basePath;
        const pluginPath = `${basePath}Resources/public/`;
        const assetPath = `${basePath}Resources/views/administration/static`;
        const publicStaticPath = `${basePath}Resources/public/static/`;

        webpackConfig.plugins.push(
            new WebpackCopyAfterBuildPlugin({
                files: [{
                    chunkName: pluginName,
                    to: `${pluginPath}/${pluginName}.js`
                }],
                options: {
                    absolutePath: true,
                    sourceMap: true
                }
            })
        );

        if(fs.existsSync(assetPath)) {
            webpackConfig.plugins.push(
                // copy custom static assets
                new CopyWebpackPlugin([
                    {
                        from: assetPath,
                        to: publicStaticPath,
                        ignore: [ '.*' ]
                    }
                ])
            );
        }
    });
}

if (config.build.bundleAnalyzerReport) {
    const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin;
    webpackConfig.plugins.push(new BundleAnalyzerPlugin());
}

module.exports = webpackConfig;
