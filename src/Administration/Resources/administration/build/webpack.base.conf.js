const path = require('path');
const utils = require('./utils');
const config = require('../config');
const vueLoaderConfig = require('./vue-loader.conf');

function resolve(dir) {
    return path.join(__dirname, '..', dir)
}

// Refactor the usage of eslint
const eslintDisable = (process.env.ESLINT_DISABLE === 'true');

module.exports = {
    performance: {
        hints: process.env.NODE_ENV === 'production'
            ? config.build.performanceHints
            : config.dev.performanceHints
    },
    entry: {
        commons: [ resolve('src') + '/core/common.js', resolve('src') + '/core/shopware.js' ],
        app: resolve('src') + '/app/main.js'
    },
    output: {
        path: config.build.assetsRoot,
        filename: '[name].js',
        publicPath: process.env.NODE_ENV === 'production'
            ? config.build.assetsPublicPath
            : config.dev.assetsPublicPath
    },
    resolve: {
        extensions: [ '.js', '.vue', '.json', '.less', '.twig' ],
        alias: {
            'vue$': 'vue/dist/vue.esm.js',
            'src': resolve('src'),
            'module': resolve('src/module'),
            'less': resolve('src/app/assets/less'),
            'assets': resolve('static')
        }
    },
    module: {
        rules: [
            (eslintDisable === true ? {} : {
                test: /\.(js|tsx?|vue)$/,
                loader: 'eslint-loader',
                enforce: "pre",
                include: [ resolve('src'), resolve('test') ],
                options: {
                    formatter: require('eslint-friendly-formatter')
                }
            }),
            {
                test: /\.vue$/,
                loader: 'vue-loader',
                options: vueLoaderConfig
            },
            {
                test: /\.(html|twig)$/,
                loader: 'html-loader'
            },
            {
                test: /\.(js|tsx?|vue)$/,
                loader: 'babel-loader',
                include: [resolve('src'), resolve('test')],
                options: {
                    presets: [['env', { modules: false }]]
                }
            },
            {
                test: /\.(png|jpe?g|gif|svg)(\?.*)?$/,
                loader: 'url-loader',
                options: {
                    limit: 10000,
                    name: utils.assetsPath('img/[name].[ext]')
                }
            },
            {
                test: /\.(woff2?|eot|ttf|otf)(\?.*)?$/,
                loader: 'url-loader',
                options: {
                    limit: 10000,
                    name: utils.assetsPath('fonts/[name].[hash:7].[ext]')
                }
            },
            {
                test: require.resolve('../node_modules/vue/dist/vue.esm.js'),
                use: [{
                    loader: 'expose-loader',
                    options: 'VueJS'
                }]
            },
            {
                test: require.resolve('../src/core/common.js'),
                use: [{
                    loader: 'expose-loader',
                    options: 'Shopware'
                }]
            }
        ],
    },
};
