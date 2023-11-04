/**
 * @package admin
 */

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
const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin;
const WebpackDynamicPublicPathPlugin = require('webpack-dynamic-public-path');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const path = require('path');
const fs = require('fs');
const chalk = require('chalk');
const crypto = require('crypto');

if (process.env.IPV4FIRST) {
    require('dns').setDefaultResultOrder('ipv4first');
}

/** HACK: OpenSSL 3 does not support md4 anymore,
* but webpack hardcodes it all over the place: https://github.com/webpack/webpack/issues/13572
*/
const cryptoOrigCreateHash = crypto.createHash;
crypto.createHash = algorithm => cryptoOrigCreateHash(algorithm === 'md4' ? 'sha256' : algorithm);

/* eslint-disable */

const buildOnlyExtensions = process.env.SHOPWARE_ADMIN_BUILD_ONLY_EXTENSIONS === '1';
const openBrowserForWatch = process.env.DISABLE_DEVSERVER_OPEN  !== '1';

const flagsPath = path.join(process.env.PROJECT_ROOT, 'var', 'config_js_features.json');
let featureFlags = {};
if (fs.existsSync(flagsPath)) {
    featureFlags = JSON.parse(fs.readFileSync(flagsPath, 'utf-8'));
    // Make featureFlags available globally
    global.featureFlags = featureFlags;
}

// https://regex101.com/r/OGpZFt/1
const versionRegex = /18\.\d{1,2}\.\d{1,2}/;
if (!versionRegex.test(process.versions.node)) {
    console.log();
    console.log(chalk.red('@Deprecated: You are using an incompatible Node.js version. Supported version range: ^18.0.0'));
    console.log();
}

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

const cssUrlMatcher = (url) => {
    // Only handle font urls
    if (url.match(/\.(woff2?|eot|ttf|otf)(\?.*)?$/)) {
        return true;
    }

    return false;
};

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
        .filter(([name, definition]) => !!definition.administration && !!definition.administration.entryFilePath && !process.env.hasOwnProperty('SKIP_' + definition.technicalName.toUpperCase().replace(/-/g, '_')))
        .map(([name, definition]) => {
            console.log(chalk.green(`# Plugin "${name}": Injected successfully`));

            const technicalName = definition.technicalName || name.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();
            const htmlFilePath = path.resolve(process.env.PROJECT_ROOT, definition.basePath, definition.administration.path, '..', 'index.html');
            const hasHtmlFile = fs.existsSync(htmlFilePath);

            return {
                name,
                technicalName: technicalName,
                technicalFolderName: technicalName.replace(/(-)/g, '').toLowerCase(),
                basePath: path.resolve(process.env.PROJECT_ROOT, definition.basePath),
                path: path.resolve(process.env.PROJECT_ROOT, definition.basePath, definition.administration.path),
                filePath: path.resolve(process.env.PROJECT_ROOT, definition.basePath, definition.administration.entryFilePath),
                hasHtmlFile,
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

        ['', 'app', 'runtime'].forEach((key) => {
            delete filteredOutput[key];
        });

        Object.entries(filteredOutput).forEach(([outputTechnicalName, bundles]) => {
            const matchingPluginEntry = pluginEntries.find(e => e.technicalName === outputTechnicalName);

            if (matchingPluginEntry && matchingPluginEntry.hasHtmlFile) {
                bundles.html = `/bundles/${matchingPluginEntry.technicalFolderName}/administration/index.html`;
            }
        })

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

    ...(() => {
        return {
            resolve: {
                extensions: ['.js', '.ts', '.vue', '.json', '.less', '.twig'],
                alias: {
                    scss: path.join(__dirname, 'src/app/assets/scss'),
                },
            },
        };
    })(),

    module: {
        rules: [
            {
                test: /\.(html|twig)$/,
                use: [
                    {
                      loader: 'string-replace-loader',
                      options: {
                          multiple: [
                              {
                                  search: /<!--[\s\S]*?-->/gm,
                                  replace: '',
                              },
                              {
                                  search: /^(?!\{#-)\{#[\s\S]*?#\}/gm,
                                  replace: '',
                              }
                          ],
                      }
                    },
                    'raw-loader',
                ],
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
                    presets: [
                        [
                            '@babel/preset-env', {
                                modules: false,
                                targets: {
                                    browsers: ['last 2 versions', 'edge >= 17'],
                                },
                            },
                        ],
                        '@babel/preset-typescript'
                    ],
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
                loader: 'file-loader',
                options: {
                    name: 'static/fonts/[name].[hash:7].[ext]'
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
                            url: cssUrlMatcher
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
                            url: cssUrlMatcher,
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
                            url: cssUrlMatcher,
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
                            url: cssUrlMatcher,
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
                    {
                        loader: MiniCssExtractPlugin.loader,
                        options: {
                            publicPath: isDev ? '/' : `../../`,
                        }
                    },
                    {
                        loader: 'css-loader',
                        options: {
                            sourceMap: true,
                            url: cssUrlMatcher,
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
                            url: cssUrlMatcher,
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
                            url: cssUrlMatcher,
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

        /**
         * All files inside webpack's output.path directory will be removed once, but the
         * directory itself will not be. If using webpack 4+'s default configuration,
         * everything under <PROJECT_DIR>/dist/ will be removed.
         * Use cleanOnceBeforeBuildPatterns to override this behavior.
         *
         * During rebuilds, all webpack assets that are not used anymore
         * will be removed automatically.
         *
         * See `Options and Defaults` for information
         */
        new CleanWebpackPlugin({
            cleanOnceBeforeBuildPatterns: [
                '!**/*',
                'administration',
            ]
        }),
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
                    open: openBrowserForWatch,
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
                        ...pluginEntries.map((plugin) => `/bundles/${plugin.technicalFolderName.replace(/-/g, '')}/static`),
                    ],
                },
                node: {
                    __filename: true,
                },
            };
        }
    })(),

    entry: {
        app: `${path.resolve('src')}/index.ts`,
    },

    ...(() => {
        return {
            resolve: {
                alias: {
                    vue$: 'vue/dist/vue.esm.js',
                    src: path.join(__dirname, 'src'),
                    assets: path.join(__dirname, 'static'),
                },
            },
        };
    })(),

    output: {
        path: isDev
            // put all files in virtual dist folder when using watcher
            // to be able to access all files in multi-compiler-mode
            ? path.resolve(__dirname, 'v_dist/')
            : path.resolve(__dirname, '../../public/'),
        filename: isDev ? 'bundles/administration/static/js/[name].js' : 'static/js/[name].js',
        chunkFilename: isDev ? 'bundles/administration/static/js/[chunkhash].js' : 'static/js/[chunkhash].js',
        publicPath: isDev ? '/' : `bundles/administration/`,
        globalObject: 'this',
        jsonpFunction: `webpackJsonpAdministration`
    },

    optimization: {
        splitChunks: {
            chunks: 'async',
            minSize: 30000,
        },
    },

    plugins: [
        ...(() => {
            if (process.env.ENABLE_ANALYZE) {
                return [
                    new BundleAnalyzerPlugin(),
                ]
            }

            return [];
        })(),

        new MiniCssExtractPlugin({
            filename: isDev ? 'bundles/administration/static/css/[name].css' : 'static/css/[name].css',
            chunkFilename: isDev ? 'bundles/administration/static/css/[chunkhash].css' : 'static/css/[chunkhash].css',
        }),

        ...(() => {
            if (isProd || process.env.DISABLE_ADMIN_COMPILATION_TYPECHECK) {
                return [];
            }

            return [
                new ForkTsCheckerWebpackPlugin({
                    typescript: {
                        mode: 'write-references',
                    },
                    logger: {
                        infrastructure: 'console',
                        issues: 'console',
                        devServer: false,
                    }
                }),
            ];
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
                                    ignore: ['.*'],
                                },
                            },
                        ],
                    }),
                    // needed to set paths for chunks dynamically (e.g. needed for S3 asset bucket)
                    new WebpackDynamicPublicPathPlugin({
                        externalPublicPath: `(window.__sw__.assetPath + '/bundles/administration/')`,
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
                            featureFlags: JSON.stringify(featureFlags),
                        },
                        inject: false,
                    }),
                    new FriendlyErrorsPlugin(),
                ];
            }
        })()
    ],
};

/**
 * We iterate through all activated plugins and create a separate webpack configuration for each plugin. We use the
 * base configuration for this. Additionally, we allow plugin developers to extend or modify their webpack configuration
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
            technicalFolderName: plugin.technicalFolderName,
            plugin,
        });
    }

    const htmlFilePath = path.resolve(plugin.path, '../index.html');
    const hasHtmlFile = fs.existsSync(htmlFilePath);

    return webpackMerge([
        createdBaseConfig,
        {
            entry: {
                [plugin.technicalName]: plugin.filePath,
            },

            ...(() => {
                return {
                    resolve: {
                        alias: {
                            '@administration': path.join(__dirname, 'src'),
                        },
                    },
                };
            })(),

            output: {
                path: isDev
                    // put all files in virtual dist folder when using watcher
                    // to be able to access all files in multi-compiler-mode
                    ? path.resolve(__dirname, `v_dist/bundles/${plugin.technicalFolderName}/administration/`)
                    : path.resolve(plugin.path, '../../../public/'),
                publicPath: isDev ? `/bundles/${plugin.technicalFolderName}/administration/` : `/bundles/${plugin.technicalFolderName}/`,
                // filenames aren´t in static folder when using watcher to match the build environment
                filename: isDev ? 'js/[name].js' : 'static/js/[name].js',
                chunkFilename: isDev ? 'js/[name].js' : 'static/js/[name].js',
                globalObject: 'this',
                jsonpFunction: `webpackJsonpPlugin${plugin.technicalName}`
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
                    if (isProd && !hasHtmlFile) {
                        return [
                            // needed to set paths for chunks dynamically (e.g. needed for S3 asset bucket)
                            new WebpackDynamicPublicPathPlugin({
                                externalPublicPath: `(window.__sw__.assetPath + '/bundles/${plugin.technicalFolderName}/')`,
                            }),

                            new ESLintPlugin({
                                context: path.resolve(plugin.path),
                                useEslintrc: false,
                                baseConfig: {
                                    parser: '@babel/eslint-parser',
                                    parserOptions: {
                                        sourceType: 'module',
                                        requireConfigFile: false,
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

                ...(() => {
                    if (hasHtmlFile) {
                        // generate HTML file for plugin
                        return [
                            // remove static from path
                            new (class HtmlWebpackRenamePathPlugin {
                                apply(compiler) {
                                    compiler.hooks.compilation.tap('HtmlWebpackRenamePathPlugin', (compilation) => {
                                        HtmlWebpackPlugin.getHooks(compilation).beforeEmit.tapAsync(
                                            'HtmlWebpackRenamePathPlugin', // name for stacktrace
                                            (data, cb) => {
                                                // replace "/administration/static/" with "/administration/"
                                                data.html = data.html.replace(
                                                    /\/administration\/static\//,
                                                    '/administration/',
                                                )

                                                // Tell webpack to move on
                                                cb(null, data)
                                            }
                                        )
                                    })
                                }
                            })(),
                            new HtmlWebpackPlugin({
                                filename: isDev ? '../administration/index.html' : 'administration/index.html',
                                template: htmlFilePath,
                                publicPath: isDev ? `/bundles/${plugin.technicalFolderName}/administration/` : `/bundles/${plugin.technicalFolderName}/administration/`,
                            })
                        ];
                    }

                    return [];
                })()
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
coreUrlImageLoader.exclude.push(/@shopware-ag\/meteor-icon-kit\/icons/);

const coreSvgInlineLoader = mergedCoreConfig.module.rules.find(r => r.loader === 'svg-inline-loader');
coreSvgInlineLoader.include.push(path.join(__dirname, 'src/app/assets/icons/svg'));
coreSvgInlineLoader.include.push(/@shopware-ag\/meteor-icon-kit\/icons/);

/**
 * Export all single configs in a array. Webpack uses then the webpack-multi-compiler for isolated
 * builds for each configuration (core + plugins).
 */
module.exports = buildOnlyExtensions
    ? [...configsForPlugins]
    : [mergedCoreConfig, ...configsForPlugins];
