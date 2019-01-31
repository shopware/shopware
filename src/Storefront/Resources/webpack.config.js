const webpack = require('webpack');
const { resolve } = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

const isDevMode = process.env.NODE_ENV !== 'production';
// ToDo - Get path from psh instead of traversing through the project directories
// Split configuration for development, production and hmr
const buildDirectory = resolve(__dirname, '../../../../../../public/build');

module.exports = {
    entry: './src/main.js',
    output: {
        path: buildDirectory,
        filename: 'main.bundle.js',
        publicPath: 'http://shopware.local:8080/'
    },
    devtool: 'source-map',
    mode: (isDevMode ? 'development' : 'production'),
    stats: {
        colors: true
    },
    performance: {
        hints: false
    },
    devServer: {
        contentBase: buildDirectory,
        overlay: {
            warnings: false,
            errors: true
        },
        hot: true,
        compress: true,
        disableHostCheck: true,
        host: '0.0.0.0',
        clientLogLevel: 'warning',
        headers: {
            'Access-Control-Allow-Origin': '*'
        }
    },
    module: {
        rules: [
            {
                test: /\.m?js$/,
                exclude: /(node_modules|bower_components)/,
                use: [{
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-env']
                    }
                }, {
                    loader: 'eslint-loader'
                }]
            },
            {
                test: /\.scss$/,
                use: [
                    (isDevMode ? 'style-loader' : MiniCssExtractPlugin.loader),
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
        new MiniCssExtractPlugin({
            filename: "main.bundle.css",
            chunkFilename: "main.bundle.css"
        }),
        new webpack.HotModuleReplacementPlugin()
    ]
};