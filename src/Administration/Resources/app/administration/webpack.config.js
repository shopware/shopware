const webpack = require('webpack');
const webpackMerge = require('webpack-merge');
const FriendlyErrorsPlugin = require('friendly-errors-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const AssetsPlugin = require('assets-webpack-plugin');
const HtmlWebpackPlugin = require('html-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const OptimizeCSSAssetsPlugin = require('optimize-css-assets-webpack-plugin');
const WebpackCopyAfterBuildPlugin = require('@shopware-ag/webpack-copy-after-build');
const path = require('path');
const fs = require('fs');
const chalk = require('chalk');

/* eslint-disable */

console.log(chalk.yellow('# Compiling with Webpack configuration'));

const isDev = process.env.mode === 'development';
const isProd = process.env.mode !== 'development';

if (isDev) {
    console.log(chalk.yellow('# Development mode is activated \u{1F6E0}'));
    console.log(chalk.yellow(`BaseUrl for proxy is set to "${process.env.APP_URL}"`));
    process.env.NODE_ENV = 'development';
} else {
    console.log(chalk.yellow('# Production mode is activated \u{1F680}'));
    process.env.NODE_ENV = 'production';
}

// Error Handling when something is not defined
if (isDev && !process.env.ENV_FILE) {
    console.error(chalk.red('\n \u{26A0}️  You need to add the "ENV_FILE" as an environment variable for compiling the code. \u{26A0}️\n'));
    process.exit(1);
}

if (isDev && !process.env.APP_URL) {
    console.error(chalk.red('\n \u{26A0}️  You need to add the "APP_URL" as an environment variable for compiling the code. \u{26A0}️\n'));
    process.exit(1);
}

if (isDev && !process.env.HOST) {
    console.error(chalk.red('\n \u{26A0}️  You need to add the "HOST" as an environment variable for compiling the code. \u{26A0}️\n'));
    process.exit(1);
}

if (isDev && !process.env.PORT) {
    console.error(chalk.red('\n \u{26A0}️  You need to add the "PORT" as an environment variable for compiling the code. \u{26A0}️\n'));
    process.exit(1);
}

if (!process.env.PROJECT_ROOT) {
    console.error(chalk.red('\n \u{26A0}️  You need to add the "PROJECT_ROOT" as an environment variable for compiling the code. \u{26A0}️\n'));
    process.exit(1);
}

const pluginEntries = (() => {
    const pluginFile = path.resolve(process.env.PROJECT_ROOT, 'var/plugins.json');

    if (!fs.existsSync(pluginFile)) {
        throw new Error(`The file ${pluginFile} could not be found. Try bin/console bundle:dump to create this file.`);
    }

    const pluginDefinition = JSON.parse(fs.readFileSync(pluginFile, 'utf8'));

    return Object.entries(pluginDefinition)
        .filter(([name, definition]) => !!definition.administration && !!definition.administration.entryFilePath)
        .map(([name, definition]) => {
            console.log(chalk.green(`# Plugin "${name}": Injected successfully`));

            return {
                name,
                technicalName: definition.technicalName || name.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase(),
                basePath: path.resolve(process.env.PROJECT_ROOT, definition.basePath),
                path: path.resolve(process.env.PROJECT_ROOT, definition.basePath, definition.administration.path),
                filePath: path.resolve(process.env.PROJECT_ROOT, definition.basePath, definition.administration.entryFilePath),
                webpackConfig: definition.administration.webpack ? path.resolve(process.env.PROJECT_ROOT, definition.basePath, definition.administration.webpack) : null
            };
        });
})();

// console log break
console.log();

const webpackConfig = {
    mode: isDev ? 'development' : 'production',
    bail: isDev ? false : true,
    stats: {
        all: false,
        colors: true,
        modules: true,
        maxModules: 0,
        errors: true,
        warnings: true,
        entrypoints: true,
        timings: true,
        logging: 'warn'
    },

    performance: {
        hints: false
    },

    ...(() => {
        if (isDev) {
            return {
                devServer: {
                    host: process.env.HOST,
                    port: process.env.PORT,
                    disableHostCheck: true,
                    open: true,
                    proxy: {
                        '/api': {
                            target: process.env.APP_URL,
                            changeOrigin: true,
                            secure: false
                        }
                    },
                    contentBase: [
                        path.resolve(__dirname, 'static'),
                        path.resolve(__dirname, 'static'),
                        ...pluginEntries.map(plugin => path.resolve(plugin.path, '../static'))
                    ],
                    contentBasePublicPath: [
                        '/static',
                        '/administration/static',
                        ...pluginEntries.map((plugin) => `/${plugin.technicalName.replace(/-/g, '')}/static`)
                    ]
                },
                node: {
                    __filename: true
                }
            };
        }
    })(),

    devtool: isDev ? 'eval-source-map' : '#source-map',

    optimization: {
        moduleIds: 'hashed',
        chunkIds: 'named',
        runtimeChunk: { name: 'runtime' },
        splitChunks: {
            cacheGroups: {
                'runtime-vendor': {
                    chunks: 'all',
                    name: 'vendors-node',
                    test: path.join(__dirname, 'node_modules')
                }
            },
            minSize: 0
        },
        ...(() => {
            if (isProd) {
                return {
                    minimizer: [
                        new TerserPlugin({
                            terserOptions: {
                                warnings: false,
                                output: 6
                            },
                            cache: true,
                            parallel: true,
                            sourceMap: false
                        }),
                        new OptimizeCSSAssetsPlugin()
                    ]
                };
            }
        })()
    },

    entry: {
        commons: [`${path.resolve('src')}/core/shopware.js`],
        app: `${path.resolve('src')}/app/main.js`,
        // 'storefront': '/Users/demo/Sites/shopware/platform/src/Storefront/Resources/app/administration/src/main.js'
        ...(() => {
            return pluginEntries.reduce((acc, plugin) => {
                acc[plugin.technicalName] = plugin.filePath;

                return acc;
            }, {});
        })()
    },

    output: {
        path: path.resolve(__dirname, '../../public/'),
        filename: 'static/js/[name].js',
        chunkFilename: 'static/js/[name].js',
        publicPath: isDev ? '/' : `${process.env.APP_URL}/bundles/administration/`,
        globalObject: 'this'
    },

    // Sync with .eslintrc.js
    resolve: {
        extensions: ['.js', '.vue', '.json', '.less', '.twig'],
        alias: {
            vue$: 'vue/dist/vue.esm.js',
            src: path.join(__dirname, 'src'),
            // ???
            // deprecated tag:v6.4.0.0
            module: path.join(__dirname, 'src/module'),
            scss: path.join(__dirname, 'src/app/assets/scss'),
            assets: path.join(__dirname, 'static')
        }
    },

    module: {
        rules: [
            ((process.env.ESLINT_DISABLE === 'true' || isProd) ? {} :
                {
                    test: /\.(js|tsx?|vue)$/,
                    loader: 'eslint-loader',
                    exclude: /node_modules/,
                    enforce: 'pre',
                    include: [
                        path.resolve(__dirname, 'src'),
                        path.resolve(__dirname, 'test'),
                        ...pluginEntries.map(plugin => plugin.filePath)
                    ],
                    options: {
                        configFile: path.join(__dirname, '.eslintrc.js'),
                        formatter: require('eslint-friendly-formatter') // eslint-disable-line global-require
                    }
                }
            ),
            {
                test: /\.(html|twig)$/,
                loader: 'html-loader'
            },
            {
                test: /\.(js|tsx?|vue)$/,
                loader: 'babel-loader',
                include: [
                    path.resolve(__dirname, 'src'),
                    path.resolve(__dirname, 'test'),
                    ...pluginEntries.map(plugin => plugin.path)
                ],
                options: {
                    compact: true,
                    cacheDirectory: true,
                    presets: [[
                        '@babel/preset-env', {
                            modules: false,
                            targets: {
                                browsers: ['last 2 versions', 'edge >= 17']
                            }
                        }
                    ]]
                }
            },
            {
                test: /\.(png|jpe?g|gif|svg)(\?.*)?$/,
                exclude: [
                    path.join(__dirname, 'src/app/assets/icons/svg')
                ],
                loader: 'url-loader',
                options: {
                    limit: 10000,
                    name: 'static/img/[name].[ext]'
                }
            },
            {
                test: /\.svg$/,
                include: [
                    path.join(__dirname, 'src/app/assets/icons/svg')
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
                    name: 'static/fonts/[name].[hash:7].[ext]'
                }
            },
            {
                test: /\.worker\.(js|tsx?|vue)$/,
                use: {
                    loader: 'worker-loader',
                    options: {
                        inline: true
                    }
                }
            },
            {
                test: /\.css$/,
                use: [
                    'vue-style-loader',
                    MiniCssExtractPlugin.loader,
                    {
                        loader: 'css-loader',
                        options: {
                            sourceMap: true,
                            url: false
                        }
                    }
                ]
            },
            {
                test: /\.postcss$/,
                use: [
                    'vue-style-loader',
                    MiniCssExtractPlugin.loader,
                    {
                        loader: 'css-loader',
                        options: {
                            sourceMap: true,
                            url: false
                        }
                    }
                ]
            },
            {
                test: /\.less$/,
                use: [
                    'vue-style-loader',
                    MiniCssExtractPlugin.loader,
                    {
                        loader: 'css-loader',
                        options: {
                            sourceMap: true,
                            url: false
                        }
                    },
                    {
                        loader: 'less-loader',
                        options: {
                            javascriptEnabled: true,
                            sourceMap: true
                        }
                    }
                ]
            },
            {
                test: /\.sass$/,
                use: [
                    'vue-style-loader',
                    MiniCssExtractPlugin.loader,
                    {
                        loader: 'css-loader',
                        options: {
                            sourceMap: true,
                            url: false
                        }
                    },
                    {
                        loader: 'sass-loader',
                        options: {
                            indentedSyntax: true,
                            sourceMap: true
                        }
                    }
                ]
            },
            {
                test: /\.scss$/,
                use: [
                    'vue-style-loader',
                    MiniCssExtractPlugin.loader,
                    {
                        loader: 'css-loader',
                        options: {
                            sourceMap: true,
                            url: false
                        }
                    },
                    {
                        loader: 'sass-loader',
                        options: {
                            sourceMap: true
                        }
                    }
                ]
            },
            {
                test: /\.stylus$/,
                use: [
                    'vue-style-loader',
                    MiniCssExtractPlugin.loader,
                    {
                        loader: 'css-loader',
                        options: {
                            sourceMap: true,
                            url: false
                        }
                    },
                    {
                        loader: 'stylus-loader',
                        options: {
                            sourceMap: true
                        }
                    }
                ]
            },
            {
                test: /\.styl$/,
                use: [
                    'vue-style-loader',
                    MiniCssExtractPlugin.loader,
                    {
                        loader: 'css-loader',
                        options: {
                            sourceMap: true,
                            url: false
                        }
                    },
                    {
                        loader: 'stylus-loader',
                        options: {
                            sourceMap: true
                        }
                    }
                ]
            }
        ]
    },

    plugins: [
        new webpack.DefinePlugin({
            'process.env': {
                NODE_ENV: isDev ? '"development"' : '"production"'
            }
        }),
        new MiniCssExtractPlugin({
            filename: 'static/css/[name].css'
        }),
        ...(() => {
            const WebpackCopyAfterBuildPlugins = pluginEntries.map((plugin) => {
                const pluginPath = path.resolve(plugin.path, '../../../public/administration');

                return new WebpackCopyAfterBuildPlugin({
                    files: [{
                        chunkName: plugin.technicalName,
                        to: `${pluginPath}/${plugin.technicalName}.js`
                    }],
                    options: {
                        absolutePath: true,
                        sourceMap: true,
                        transformer: (path) => {
                            return path.replace('static/', '');
                        }
                    }
                });
            });

            const CopyWebpackPlugins = pluginEntries.reduce((acc, plugin) => {
                const assetPath = path.resolve(plugin.path, '../static');

                if (fs.existsSync(assetPath)) {
                    acc.push(
                        // copy custom static assets
                        new CopyWebpackPlugin({
                            patterns: [
                                {
                                    from: assetPath,
                                    to: path.resolve(plugin.basePath, 'Resources/public/static/'),
                                    globOptions: {
                                        ignore: ['.*']
                                    }
                                }
                            ]
                        })
                    );
                }

                return acc;
            }, []);

            return [...WebpackCopyAfterBuildPlugins, ...CopyWebpackPlugins];
        })(),
        ...(() => {
            if (isProd) {
                return [
                // copy custom static assets
                    new CopyWebpackPlugin({
                        patterns: [
                            {
                                from: path.resolve('.', 'static'),
                                to: 'static',
                                globOptions: {
                                    ignore: ['.*']
                                }
                            }
                        ]
                    })
                ];
            }

            if (isDev) {
                return [
                // https://github.com/glenjamin/webpack-hot-middleware#installation--usage
                    new webpack.HotModuleReplacementPlugin(),
                    new webpack.NoEmitOnErrorsPlugin(),
                    // https://github.com/ampedandwired/html-webpack-plugin
                    new HtmlWebpackPlugin({
                        filename: 'index.html',
                        template: 'index.html.tpl',
                        templateParameters: {
                            featureFlags: (() => {
                                const getFeatureFlagNames = (sourceFolder) => {
                                    const flagsPath = path.join(sourceFolder, '/config_js_features.json');

                                    if (!fs.existsSync(flagsPath)) {
                                        return '{}';
                                    }

                                    return fs.readFileSync(flagsPath);
                                }

                                return getFeatureFlagNames(path.join(__dirname, '../../../../../../var'));
                            })(),
                            // TODO: NEXT-7581 - Implement a version dump in the backend and read here the version file
                            apiVersion: 3
                        },
                        inject: false
                    }),
                    new FriendlyErrorsPlugin(),
                    new AssetsPlugin({
                        filename: 'sw-plugin-dev.json',
                        fileTypes: ['js', 'css'],
                        includeAllFileTypes: false,
                        fullPath: true,
                        useCompilerPath: true,
                        prettyPrint: true,
                        keepInMemory: true,
                        processOutput: function filterAssetsOutput(output) {
                            const filteredOutput = { ...output };

                            ['', 'app', 'commons', 'runtime', 'vendors-node'].forEach((key) => {
                                delete filteredOutput[key];
                            });

                            return JSON.stringify(filteredOutput);
                        }
                    })
                ];
            }
        })()

    ]
};

const pluginWebpackConfigs = [];
pluginEntries.forEach(plugin => {
    if (!plugin.webpackConfig) {
        return;
    }

    const pluginWebpackConfigFn = require(path.resolve(plugin.webpackConfig));
    console.log(chalk.green(`# Plugin "${plugin.name}": Extends the webpack config successfully`));

    pluginWebpackConfigs.push(pluginWebpackConfigFn({
        basePath: plugin.basePath,
        env: process.env.NODE_ENV,
        config: webpackConfig,
        name: plugin.name,
        technicalName: plugin.technicalName,
        plugin
    }));
});

const mergedWebpackConfig = webpackMerge([webpackConfig, ...pluginWebpackConfigs]);

module.exports = mergedWebpackConfig;
