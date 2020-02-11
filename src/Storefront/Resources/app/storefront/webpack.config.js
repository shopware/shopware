const merge = require('webpack-merge');
const WebpackPluginInjector = require('@shopware-ag/webpack-plugin-injector');

let file = 'dev';

if (process.env.NODE_ENV === 'production') {
    file = 'prod';
} else if (process.env.MODE === 'hot') {
    file = 'hot';
}

const path = `./build/webpack.${file}.config.js`;

let webpackConfig = merge(
    require('./build/webpack.base.config'),
    require(path) // eslint-disable-line
);

if (process.env.MODE !== 'hot') {
    const injector = new WebpackPluginInjector('var/plugins.json', webpackConfig, 'storefront');
    webpackConfig = injector.webpackConfig;
}

console.log(`ℹ USING WEBPACK CONFIG FILE: ${path}`);
console.log('');

module.exports = webpackConfig;
