const webpack = require('webpack');
const config = require('../config');
const { resolve } = require('path');
const FriendlyErrorsWebpackPlugin = require('friendly-errors-webpack-plugin');

const buildDirectory = resolve(process.env.PROJECT_ROOT, 'public/build');

module.exports = {
    mode: 'development',
    devtool: 'cheap-module-eval-source-map',
    devServer: {
        contentBase: buildDirectory,
        open: config.autoOpenBrowser,
        overlay: {
            warnings: false,
            errors: true
        },
        stats: {
            colors: true
        },
        quiet: true,
        hot: true,
        compress: true,
        disableHostCheck: true,
        port: config.devServerPort,
        host: '0.0.0.0',
        clientLogLevel: 'warning',
        headers: {
            'Access-Control-Allow-Origin': '*'
        }
    },
    module: {
        rules: [
            {
                test: /\.scss$/,
                use: [
                    { loader: 'style-loader' },
                    { loader: 'css-loader' },
                    {
                        loader: 'postcss-loader',
                        options: {
                            plugins: () => {
                                return [
                                    require('autoprefixer'),
                                    require('postcss-pxtorem')({
                                        propList: ['*']
                                    })
                                ];
                            }
                        }
                    },
                    { loader: 'sass-loader' }
                ]
            }
        ]
    },
    plugins: [
        new FriendlyErrorsWebpackPlugin(),
        new webpack.HotModuleReplacementPlugin()
    ]
};