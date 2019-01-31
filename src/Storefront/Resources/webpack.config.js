const config = require('./config');
const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin;

const webpackConfig = require('webpack-merge')(
    require('./build/webpack.base.config'),
    require(`./build/webpack.${process.env.NODE_ENV !== 'production' ? 'dev' : 'prod'}.config.js`)
);

if (config.analyzeBundle) {
    webpackConfig.plugins.push(new BundleAnalyzerPlugin());
}

module.exports = webpackConfig;