const path = require('path');
const fs = require('fs');
const utils = require('./utils');
const config = require('../config');
const vueLoaderConfig = require('./vue-loader.conf');

const pluginList = utils.getPluginDefinitions('var/config_administration_plugins.json', true);

const pluginSourceDirectories = pluginList.reduce((accumulator, plugin) => {
    const srcPath = `${plugin.basePath}Resources/views/administration`;
    if (fs.existsSync(srcPath)) {
        accumulator.push(srcPath);
    }

    return accumulator;
}, []);

function resolve(dir) {
    return path.join(__dirname, '..', dir)
}

// Refactor the usage of eslint
const eslintDisable = (process.env.ESLINT_DISABLE === 'true');
const includeDirectories =  [...[ resolve('src'), resolve('test') ], ...pluginSourceDirectories];

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
        filename: utils.assetsPath('js/[name].js'),
        chunkFilename: utils.assetsPath('js/[name].js'),
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
            'scss': resolve('src/app/assets/scss'),
            'assets': resolve('static')
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
                include: includeDirectories,
                options: {
                    presets: [[
                        '@babel/preset-env', {
                            modules: false,
                            targets: {
                                node: "current"
                            }
                        }
                    ]],
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
                test: require.resolve('../src/core/common.js'),
                use: [{
                    loader: 'expose-loader',
                    options: 'Shopware'
                }]
            }
        ],
    },
};
