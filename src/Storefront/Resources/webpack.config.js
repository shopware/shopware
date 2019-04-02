let file = 'dev';

if (process.env.NODE_ENV === 'production') {
    file = 'prod';
} else if (process.env.MODE === 'hot') {
    file = 'hot';
}

const path = `./build/webpack.${file}.config.js`;

const webpackConfig = require('webpack-merge')(
    require('./build/webpack.base.config'),
    require(path)
);

console.log(`ℹ USING WEBPACK CONFIG FILE: ${path}`);
console.log('');

module.exports = webpackConfig;