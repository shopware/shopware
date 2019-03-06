const webpackConfig = require('webpack-merge')(
    require('./build/webpack.base.config'),
    require(`./build/webpack.${process.env.NODE_ENV !== 'production' ? 'dev' : 'prod'}.config.js`)
);

module.exports = webpackConfig;