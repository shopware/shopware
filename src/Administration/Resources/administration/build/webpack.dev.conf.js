var utils = require('./utils');
var webpack = require('webpack');
var config = require('../config');
var merge = require('webpack-merge');
var baseWebpackConfig = require('./webpack.base.conf');
var HtmlWebpackPlugin = require('html-webpack-plugin');
var FriendlyErrorsPlugin = require('friendly-errors-webpack-plugin');
var plugins = {};

// Temporarily save the app entry point and remove it from the entry definition to sort the object the way we need it.
// We need the following order
// - commons
// - [n] plugins
// - app
var appEntry = baseWebpackConfig.entry.app;
delete baseWebpackConfig.entry.app;

/**
 * Try to load plugin definition file
 */
try {
    plugins = require('../../../../../var/config_administration_plugins.json');

    // add hot-reload related code to entry chunks
    Object.keys(plugins).forEach(function (pluginName) {
        baseWebpackConfig.entry[pluginName] = plugins[pluginName];
    });
} catch(e) {}

baseWebpackConfig.entry.app = appEntry;

Object.keys(baseWebpackConfig.entry).forEach(function (name) {
  baseWebpackConfig.entry[name] = ['./build/dev-client'].concat(baseWebpackConfig.entry[name])
});

var chunks = Object.keys(baseWebpackConfig.entry).map((entry) => {
    return entry;
});

const mergedWebpackConfig = merge(baseWebpackConfig, {
  module: {
    rules: utils.styleLoaders({ sourceMap: config.dev.cssSourceMap })
  },
  // cheap-module-eval-source-map is faster for development
  devtool: '#cheap-module-eval-source-map',
  plugins: [
    new webpack.DefinePlugin({
      'process.env': config.dev.env
    }),
    // https://github.com/glenjamin/webpack-hot-middleware#installation--usage
    new webpack.HotModuleReplacementPlugin(),
    new webpack.NoEmitOnErrorsPlugin(),
    // https://github.com/ampedandwired/html-webpack-plugin
    new HtmlWebpackPlugin({
      filename: 'index.html',
      template: 'index.html',
      inject: true,
      chunksSortMode: 'manual',
      chunks: chunks
    }),
    new FriendlyErrorsPlugin()
  ]
});

module.exports = mergedWebpackConfig;
