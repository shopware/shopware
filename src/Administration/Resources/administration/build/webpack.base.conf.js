/* eslint-disable import/no-extraneous-dependencies */
const path = require('path');
const VueLoaderPlugin = require('vue-loader/lib/plugin');
const utils = require('./utils');
const config = require('../config');
const vueLoaderConfig = require('./vue-loader.conf');

function resolve(dir) {
    return path.join(__dirname, '..', dir);
}

// Refactor the usage of eslint
const eslintDisable = (process.env.ESLINT_DISABLE === 'true');
const includeDirectories = [...[resolve('src'), resolve('test')]];

module.exports = {
    performance: {
        hints: process.env.NODE_ENV === 'production'
            ? config.build.performanceHints
            : config.dev.performanceHints
    },
    optimization: {
        moduleIds: 'hashed',
        chunkIds: 'named',
        runtimeChunk: {
            name: 'runtime'
        },
        splitChunks: {
            cacheGroups: {
                'runtime-vendor': {
                    test: utils.resolve('node_modules'),
                    name: 'vendors-node',
                    chunks: 'all'
                }
            }
        }
    },
    entry: {
        commons: [`${resolve('src')}/core/common.js`, `${resolve('src')}/core/shopware.js`],
        app: `${resolve('src')}/app/main.js`
    },
    output: {
        path: config.build.assetsRoot,
        filename: utils.assetsPath('js/[name].js'),
        chunkFilename: utils.assetsPath('js/[name].js'),
        publicPath: process.env.NODE_ENV === 'production'
            ? config.build.assetsPublicPath
            : config.dev.assetsPublicPath,
        globalObject: 'this'
    },
    resolve: {
        extensions: ['.js', '.vue', '.json', '.less', '.twig'],
        alias: {
            vue$: require.resolve('vue/dist/vue.esm.js'),
            src: resolve('src'),
            module: resolve('src/module'),
            scss: resolve('src/app/assets/scss'),
            assets: resolve('static')
        }
    },
    module: {
        rules: [
            (eslintDisable === true ? {} : {
                test: /\.(js|tsx?|vue)$/,
                loader: 'eslint-loader',
                exclude: /node_modules/,
                enforce: 'pre',
                include: includeDirectories,
                options: {
                    configFile: resolve('.eslintrc.js'),
                    formatter: require('eslint-friendly-formatter') // eslint-disable-line global-require
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
                include: includeDirectories,
                options: {
                    compact: true,
                    presets: [[
                        '@babel/preset-env', {
                            modules: false,
                            targets: {
                                node: 'current',
                                ie: 11
                            }
                        }
                    ]]
                }
            },
            {
                test: /\.(png|jpe?g|gif|svg)(\?.*)?$/,
                exclude: [
                    resolve('src/app/assets/icons/svg')
                ],
                loader: 'url-loader',
                options: {
                    limit: 10000,
                    name: utils.assetsPath('img/[name].[ext]')
                }
            },
            {
                test: /\.svg$/,
                include: [
                    resolve('src/app/assets/icons/svg')
                ],
                loader: 'svg-inline-loader',
                options: {
                    removeSVGTagAttrs: false
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
                test: require.resolve('../src/core/common.js'),
                use: [{
                    loader: 'expose-loader',
                    options: 'Shopware'
                }]
            },
            {
                test: /\.worker\.(js|tsx?|vue)$/,
                use: {
                    loader: 'worker-loader',
                    options: {
                        inline: true
                    }
                }
            }
        ]
    },
    plugins: [
        new VueLoaderPlugin()
    ]
};
