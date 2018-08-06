// This is the webpack config used for unit tests.
const utils = require('./utils');
const webpack = require('webpack');
const merge = require('webpack-merge');
const baseConfig = require('./webpack.base.conf');

const webpackConfig = merge(baseConfig, {
    // use inline sourcemap for karma-sourcemap-loader
    module: {
        rules: utils.styleLoaders()
    },
    mode: 'development',
    devtool: '#inline-source-map',
    plugins: [
        new webpack.DefinePlugin({
            'process.env': require('../config/test.env')
        })
    ]
});

// no need for app entry during tests
delete webpackConfig.entry;

module.exports = webpackConfig;
