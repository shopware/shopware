const path = require('path');
const utils = require('../build/utils');
const vueLoaderConfig = require('../build/vue-loader.conf');
const webpackMerge = require('webpack-merge');

function resolve(dir) {
    return path.join(__dirname, '..', dir);
}
const baseConfig = {
    resolve: {
        extensions: ['.js', '.vue', '.json', '.less', '.twig'],
        alias: {
            vue$: 'vue/dist/vue.esm.js',
            src: resolve('src'),
            atom: resolve('src/app/common/atom'),
            molecule: resolve('src/app/common/molecule'),
            module: resolve('src/module')
        }
    },
    module: {
        rules: [
            {
                test: /\.(js|vue)$/,
                loader: 'eslint-loader',
                enforce: 'pre',
                include: [resolve('src'), resolve('test')],
                options: {
                    formatter: require('eslint-friendly-formatter')
                }
            },
            {
                test: /\.vue/,
                loader: 'vue-loader',
                options: vueLoaderConfig
            },
            {
                test: /\.(html|twig)$/,
                // include: [ resolve('src'), resolve('test') ],
                loader: 'html-loader'
            },
            {
                test: /\.js$/,
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
        ]
    }
};

module.exports = webpackMerge(baseConfig, {
    module: {
        rules: utils.styleLoaders({ sourceMap: false })
    }
});
