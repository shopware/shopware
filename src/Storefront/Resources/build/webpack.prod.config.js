const config = require('../config');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = {
    mode: 'production',
    devtool: config.productionSourceMap ? 'source-map' : false,
    module: {
        rules: [
            {
                test: /\.scss$/,
                use: [
                    MiniCssExtractPlugin.loader,
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
    optimization: {
        minimizer: [new TerserPlugin({
            terserOptions: {
                ecma: 6,
                warnings: false
            },
            cache: true,
            parallel: true,
            sourceMap: config.productionSourceMap
        })]
    },
    plugins: [
        new MiniCssExtractPlugin({
            filename: "main.bundle.css",
            chunkFilename: "main.bundle.css"
        })
    ]
};