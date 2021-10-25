const webpack = require('webpack');
const webpackMerge = require('webpack-merge');
const FriendlyErrorsPlugin = require('friendly-errors-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const AssetsPlugin = require('assets-webpack-plugin');
const HtmlWebpackPlugin = require('html-webpack-plugin');
const ESLintPlugin = require('eslint-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const OptimizeCSSAssetsPlugin = require('optimize-css-assets-webpack-plugin');
const WebpackCopyAfterBuildPlugin = require('@shopware-ag/webpack-copy-after-build');
const ForkTsCheckerWebpackPlugin = require('fork-ts-checker-webpack-plugin');
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
if (isDev && !process.env.APP_URL) {
    console.error(chalk.red('\n \u{26A0}️  You need to add the "APP_URL" as an environment variable for compiling the code. \u{26A0}️\n'));
    process.exit(1);
}

if (isDev && !process.env.HOST) {
    process.env.HOST = '0.0.0.0';
    console.debug('HOST not defined. Using 0.0.0.0 as default');
}

if (isDev && !process.env.PORT) {
    process.env.PORT = 8080;
    console.debug(`PORT not defined. Using ${process.env.PORT} as default`);
}

if (!process.env.PROJECT_ROOT) {
    console.error(chalk.red('\n \u{26A0}️  You need to add the "PROJECT_ROOT" as an environment variable for compiling the code. \u{26A0}️\n'));
    process.exit(1);
}

/**
 * Create an array with information about all injected plugins.
 *
 * The given structure looks like this:
 * [
 *   {
 *      name: 'SwagExtensionStore',
 *      technicalName: 'swag-extension-store',
 *      basePath: '/Users/max.muster/Sites/shopware/custom/plugins/SwagExtensionStore/src',
 *      path: '/Users/max.muster/Sites/shopware/custom/plugins/SwagExtensionStore/src/Resources/app/administration/src',
 *      filePath: '/Users/max.muster/Sites/shopware/custom/plugins/SwagExtensionStore/src/Resources/app/administration/src/main.js',
 *      webpackConfig: '/Users/max.muster/Sites/shopware/custom/plugins/SwagExtensionStore/src/Resources/app/administration/build/webpack.config.js'
 *   },
 *    ...
 * ]
 */
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
                webpackConfig: definition.administration.webpack ? path.resolve(process.env.PROJECT_ROOT, definition.basePath, definition.administration.webpack) : null,
            };
        });
})();

/**
 * Provide global instance for the plugin assets to support multi-compiler-mode.
 *
 * The assets are needed in the watcher to load the plugins asynchronously in the boot process.
 * In the built files we get this information from the config route. In the watcher we need the
 * live-reloaded files. To get the right url for these files we create a `sw-plugin-dev.json` file
 * which contains all paths to the plugins.
 *
 */
const assetsPluginInstance = new AssetsPlugin({
    filename: 'sw-plugin-dev.json',
    fileTypes: ['js', 'css'],
    includeAllFileTypes: false,
    fullPath: true,
    path: path.resolve(__dirname, 'v_dist'),
    prettyPrint: true,
    keepInMemory: true,
    processOutput: function filterAssetsOutput(output) {
        const filteredOutput = { ...output };

        ['', 'app', 'commons', 'runtime', 'vendors-node'].forEach((key) => {
            delete filteredOutput[key];
        });

        return JSON.stringify(filteredOutput);
    },
});

// console log break
console.log();

/**
 * This is the base webpack configuration which will be used from the core and the plugins.
 * It contains the necessary configuration expect the entries and the output.
 */
const baseConfig = ({ pluginPath, pluginFilepath }) => ({
    mode: isDev ? 'development' : 'production',
    bail: !isDev,
    stats: {
        all: false,
        colors: true,
        modules: true,
        maxModules: 0,
        errors: true,
        warnings: true,
        entrypoints: true,
        timings: true,
        logging: 'warn',
    },

    performance: {
        hints: false,
    },

    devtool: isDev ? 'eval-source-map' : '#source-map',

    optimization: {
        moduleIds: 'hashed',
        chunkIds: 'named',
        ...(() => {
            if (isProd) {
                return {
                    minimizer: [
                        new TerserPlugin({
                            terserOptions: {
                                warnings: false,
                                output: 6,
                            },
                            cache: true,
                            parallel: true,
                            sourceMap: false,
                        }),
                        new OptimizeCSSAssetsPlugin(),
                    ],
                };
            }
        })(),
    },

    externals: {
        Shopware: 'Shopware',
    },

    // Sync with .eslintrc.js
    resolve: {
        extensions: ['.js', '.ts', '.vue', '.json', '.less', '.twig'],
        alias: {
            vue$: 'vue/dist/vue.esm.js',
            src: path.join(__dirname, 'src'),
            scss: path.join(__dirname, 'src/app/assets/scss'),
            assets: path.join(__dirname, 'static'),
        },
    },

    module: {
        rules: [
            {
                test: /\.(html|twig)$/,
                loader: 'html-loader',
            },
            {
                test: /\.(js|ts|tsx?|vue)$/,
                loader: 'babel-loader',
                include: [
                    /**
                     * Only needed for unit tests in plugins. It throws an ESLint error
                     * in production build
                     */
                    path.resolve(__dirname, 'src'),
                    fs.realpathSync(path.resolve(pluginPath, '..', 'src')),
                    path.resolve(pluginPath, '..', 'test'),
                ],
                options: {
                    compact: true,
                    cacheDirectory: true,
                    presets: [[
                        '@babel/preset-env', {
                            modules: false,
                            targets: {
                                browsers: ['last 2 versions', 'edge >= 17'],
                            },
                        },
                    ]],
                },
            },
            {
                test: /\.(png|jpe?g|gif|svg)(\?.*)?$/,
                exclude: [],
                loader: 'url-loader',
                options: {
                    limit: 10000,
                    name: 'static/img/[name].[ext]',
                },
            },
            {
                test: /\.svg$/,
                include: [],
                loader: 'svg-inline-loader',
                options: {
                    removeSVGTagAttrs: false,
                },
            },
            {
                test: /\.(woff2?|eot|ttf|otf)(\?.*)?$/,
                loader: 'url-loader',
                options: {
                    limit: 10000,
                    name: 'static/fonts/[name].[hash:7].[ext]',
                },
            },
            {
                test: /\.worker\.(js|tsx?|vue)$/,
                use: {
                    loader: 'worker-loader',
                    options: {
                        inline: true,
                    },
                },
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
                            url: false,
                        },
                    },
                ],
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
                            url: false,
                        },
                    },
                ],
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
                            url: false,
                        },
                    },
                    {
                        loader: 'less-loader',
                        options: {
                            javascriptEnabled: true,
                            sourceMap: true,
                        },
                    },
                ],
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
                            url: false,
                        },
                    },
                    {
                        loader: 'sass-loader',
                        options: {
                            indentedSyntax: true,
                            sourceMap: true,
                        },
                    },
                ],
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
                            url: false,
                        },
                    },
                    {
                        loader: 'sass-loader',
                        options: {
                            sourceMap: true,
                        },
                    },
                ],
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
                            url: false,
                        },
                    },
                    {
                        loader: 'stylus-loader',
                        options: {
                            sourceMap: true,
                        },
                    },
                ],
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
                            url: false,
                        },
                    },
                    {
                        loader: 'stylus-loader',
                        options: {
                            sourceMap: true,
                        },
                    },
                ],
            },
        ],
    },

    plugins: [
        new ForkTsCheckerWebpackPlugin({
            logger: {
                infrastructure: 'console',
                issues: 'console',
                devServer: false,
            }
        }),

        new webpack.DefinePlugin({
            'process.env': {
                NODE_ENV: isDev ? '"development"' : '"production"',
            },
        }),

        ...(() => {
            if (isDev) {
                return [assetsPluginInstance];
            }
            return [];
        })(),
    ],
});

/**
 * This is the webpack configuration for the core. This configuration adds a webpack-dev-server and generates the
 * html file for the admin watcher.
 *
 * Some specific core optimizations are also configured here.
 *
 * To get access to all plugin files in the watcher with a webpack-multi-compiler setup we are using a virtual
 * folder named `v_dist`. That is the root folder for all generated files in the watcher. Otherwise we don´t
 * have access to the plugin files.
 */
const coreConfig = {
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
                            secure: false,
                        },
                    },
                    contentBase: [
                        // 3 because it need to match the contentBasePublicPath index
                        path.resolve(__dirname, 'static'),
                        path.resolve(__dirname, 'static'),
                        path.resolve(__dirname, 'static'),
                        // the dev server is allowed to access the plugin folders
                        ...pluginEntries.map(plugin => path.resolve(plugin.path, '../static')),
                    ],
                    contentBasePublicPath: [
                        '/static',
                        '/administration/static',
                        '/bundles/administration/static',
                        // the dev server is allowed to access the plugin folders
                        ...pluginEntries.map((plugin) => `/bundles/${plugin.technicalName.replace(/-/g, '')}/static`),
                    ],
                },
                node: {
                    __filename: true,
                },
            };
        }
    })(),

    entry: {
        commons: [`${path.resolve('src')}/core/shopware.ts`],
        app: `${path.resolve('src')}/app/main.ts`,
    },

    output: {
        path: isDev
            // put all files in virtual dist folder when using watcher
            // to be able to access all files in multi-compiler-mode
            ? path.resolve(__dirname, 'v_dist/')
            : path.resolve(__dirname, '../../public/'),
        filename: isDev ? 'bundles/administration/static/js/[name].js' : 'static/js/[name].js',
        chunkFilename: isDev ? 'bundles/administration/static/js/[name].js' : 'static/js/[name].js',
        publicPath: isDev ? '/' : `${process.env.APP_URL}/bundles/administration/`,
        globalObject: 'this',
    },

    optimization: {
        runtimeChunk: { name: 'runtime' },
        splitChunks: {
            cacheGroups: {
                'runtime-vendor': {
                    chunks: 'all',
                    name: 'vendors-node',
                    test: path.join(__dirname, 'node_modules'),
                },
            },
            minSize: 0,
        },
    },

    plugins: [
        new MiniCssExtractPlugin({
            filename: isDev ? 'bundles/administration/static/css/[name].css' : 'static/css/[name].css',
        }),

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
                                    ignore: ['.*'],
                                },
                            },
                        ],
                    }),
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
                                const getFeatureFlagNames = (flagsPath) => {
                                    if (!fs.existsSync(flagsPath)) {
                                        return '{}';
                                    }

                                    return fs.readFileSync(flagsPath);
                                };

                                return getFeatureFlagNames(path.join(process.env.PROJECT_ROOT, 'var', 'config_js_features.json'));
                            })(),
                        },
                        inject: false,
                    }),
                    new FriendlyErrorsPlugin(),
                ];
            }
        })(),

    ],
};

/**
 * We iterate through all activated plugins and create a separate webpack configuration for each plugin. We use the
 * base configuration for this. Additionally we allow plugin developers to extend or modify their webpack configuration
 * when needed.
 *
 * The entry file and the output will be defined for each plugin so that the generated files are in the correct folders.
 */
const configsForPlugins = pluginEntries.map((plugin) => {
    const createdBaseConfig = baseConfig({ pluginFilepath: plugin.filePath, pluginPath: plugin.path });
    const pluginPath = path.resolve(plugin.path, '../../../public/administration');
    const assetPath = path.resolve(plugin.path, '../static');

    // add custom config optionally when it exists
    let customPluginConfig = {};

    if (plugin.webpackConfig) {
        console.log(chalk.green(`# Plugin "${plugin.name}": Extends the webpack config successfully`));

        const pluginWebpackConfigFn = require(path.resolve(plugin.webpackConfig));
        customPluginConfig = pluginWebpackConfigFn({
            basePath: plugin.basePath,
            env: process.env.NODE_ENV,
            config: createdBaseConfig,
            name: plugin.name,
            technicalName: plugin.technicalName,
            plugin,
        });
    }

    return webpackMerge([
        createdBaseConfig,
        {
            entry: {
                [plugin.technicalName]: plugin.filePath,
            },

            output: {
                path: isDev
                    // put all files in virtual dist folder when using watcher
                    // to be able to access all files in multi-compiler-mode
                    ? path.resolve(__dirname, `v_dist/bundles/${plugin.technicalName}/administration/`)
                    : path.resolve(plugin.path, '../../../public/'),
                publicPath: isDev ? `/bundles/${plugin.technicalName}/administration/` : '/bundles/administration/',
                // filenames aren´t in static folder when using watcher to match the build environment
                filename: isDev ? 'js/[name].js' : 'static/js/[name].js',
                chunkFilename: isDev ? 'js/[name].js' : 'static/js/[name].js',
                globalObject: 'this',
            },

            plugins: [
                new MiniCssExtractPlugin({
                    filename: isDev ? 'css/[name].css' : 'static/css/[name].css',
                }),

                new WebpackCopyAfterBuildPlugin({
                    files: [{
                        chunkName: plugin.technicalName,
                        to: `${pluginPath}/${plugin.technicalName}.js`,
                    }],
                    options: {
                        absolutePath: true,
                        sourceMap: true,
                        transformer: (path) => {
                            return path.replace('static/', '');
                        },
                    },
                }),

                ...(() => {
                    if (isProd) {
                        return [
                            new ESLintPlugin({
                                context: path.resolve(plugin.path),
                                useEslintrc: false,
                                baseConfig: {
                                    parser: 'babel-eslint',
                                    parserOptions: {
                                        sourceType: 'module'
                                    },
                                    plugins: [ 'plugin-rules' ],
                                    rules: {
                                        'plugin-rules/no-src-imports': 'error'
                                    }
                                }
                            }),
                        ];
                    }

                    return [];
                })(),

                ...(() => {
                    if (fs.existsSync(assetPath)) {
                        // copy custom static assets
                        return [
                            new CopyWebpackPlugin({
                                patterns: [
                                    {
                                        from: assetPath,
                                        to: path.resolve(plugin.basePath, 'Resources/public/static/'),
                                        globOptions: {
                                            ignore: ['.*'],
                                        },
                                    },
                                ],
                            }),
                        ];
                    }

                    return [];
                })(),
            ],
        },
        customPluginConfig,
    ]);
});

/**
 * We create the final core configuration by merging the baseConfig with the coreConfig
 */
const mergedCoreConfig = webpackMerge([baseConfig({
    pluginPath: path.resolve(__dirname, 'src'),
    pluginFilepath: path.resolve(__dirname, 'src/app/main.js'),
}), coreConfig]);

// add special rule options to core configuration
const coreUrlImageLoader = mergedCoreConfig.module.rules.find(r => {
    return r.loader === 'url-loader' && r.test.test('.png');
});
coreUrlImageLoader.exclude.push(path.join(__dirname, 'src/app/assets/icons/svg'));

const coreSvgInlineLoader = mergedCoreConfig.module.rules.find(r => r.loader === 'svg-inline-loader');
coreSvgInlineLoader.include.push(path.join(__dirname, 'src/app/assets/icons/svg'));

/**
 * Export all single configs in a array. Webpack uses then the webpack-multi-compiler for isolated
 * builds for each configuration (core + plugins).
 */
module.exports = [mergedCoreConfig, ...configsForPlugins];
