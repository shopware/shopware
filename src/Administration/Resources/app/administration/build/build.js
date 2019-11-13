require('./check-versions')();

// Force the environment
process.env.NODE_ENV = 'production';

const rm = require('rimraf');
const path = require('path');
const chalk = require('chalk');
const webpack = require('webpack');
const config = require('../config');
const webpackConfig = require('./webpack.prod.conf');

console.log('Building project for production...');

rm(path.join(config.build.assetsRoot, config.build.assetsSubDirectory), (removeDirectoryError) => {
    if (removeDirectoryError) {
        throw removeDirectoryError;
    }

    webpack(webpackConfig, (webpackError, stats) => {
        if (webpackError) {
            console.error(webpackError.stack || err);
            if (webpackError.details) {
                console.error(webpackError.details);
            }
            return;
        }

        const info = stats.toJson();
        if (stats.hasErrors()) {
            console.error(info.errors);
        }

        if (stats.hasWarnings()) {
            console.warn(info.warnings);
        }

        console.log(stats.toString({
            colors: true,
            modules: false,
            children: false,
            chunks: false,
            chunkModules: false
        }));

        console.log(chalk.cyan('  Build complete.\n'));
    });
});
