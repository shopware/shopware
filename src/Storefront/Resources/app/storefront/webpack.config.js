const merge = require('webpack-merge');
const fs = require('fs');
const { resolve } = require('path');
let WebpackPluginInjector;

if (fs.existsSync(resolve('../../../../Administration/Resources/app/common/webpack-plugin-injector/index.js'))) {
    WebpackPluginInjector = require('../../../../Administration/Resources/app/common/webpack-plugin-injector');
} else {
    WebpackPluginInjector = require('../../../../administration/Resources/app/common/webpack-plugin-injector');
}

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