const webpack = require('webpack');
const { resolve } = require('path');
const process = require('process');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

const isDevMode = process.env.NODE_ENV !== 'production';

// ToDo - Get path from psh instead of traversing through the project directories
const buildDirectory = resolve(__dirname, '../../../../../../public/build');

module.exports = {
    entry: {
        app: './src/app.js'
    },
    output: {
        path: buildDirectory,
        filename: 'app.bundle.js'
    },
    devtool: 'source-map',
    mode: (isDevMode ? 'development' : 'production'),
    stats: {
        colors: true
    },
    module: {
        rules: [
            {
                test: /\.m?js$/,
                exclude: /(node_modules|bower_components)/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-env']
                    }
                }
            },
            {
                test: /\.scss$/,
                use: [
                    (isDevMode ? 'style-loader' : MiniCssExtractPlugin.loader),
                    'css-loader',
                    'sass-loader'
                ]
            }
        ]
    },
    plugins: [
        new MiniCssExtractPlugin({
            filename: "app.css",
            chunkFilename: "app.css"
        })
    ]
};