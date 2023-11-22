/**
 * @package storefront
 * @deprecated tag:v6.6.0 - This new webpack uses independent build processes for plugins/apps and the core and also considers async imports.
 */
const chalk = require('chalk');

console.log(chalk.yellow('#############################################'));
console.log(chalk.yellow('# v6.6.0 - Storefront async JS is activated #'));
console.log(chalk.yellow('#############################################'));
console.log();

const { merge } = require('webpack-merge');
const babelrc = require('./.babelrc');
const path = require('path');
const webpack = require('webpack');
const fs = require('fs');
const TerserPlugin = require('terser-webpack-plugin');
const WebpackBar = require('webpackbar');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

if (process.env.IPV4FIRST) {
    require('dns').setDefaultResultOrder('ipv4first');
}

const isProdMode = process.env.NODE_ENV === 'production';
const isHotMode = process.env.MODE === 'hot';
const isDevMode = process.env.NODE_ENV !== 'production' && process.env.MODE !== 'hot';

const projectRootPath = process.env.PROJECT_ROOT
    ? path.resolve(process.env.PROJECT_ROOT)
    : path.resolve('../../../../..');

let themeFiles;
let features = {};

if (isHotMode) {
    const themeFilesConfigPath = path.resolve(projectRootPath, 'var/theme-files.json');
    themeFiles = require(themeFilesConfigPath);
}
const featureConfigPath = path.resolve(projectRootPath, 'var/config_js_features.json');

if (fs.existsSync(featureConfigPath)) {
    features = require(featureConfigPath);
} else {
    console.error(chalk.red('\n \u{26A0}️  The feature dump file "config_js_features.json" cannot be found. All features will be deactivated. Please execute bin/console feature:dump.  \u{26A0}️\n'));
}

let hostName;
const proxyPort = parseInt(process.env.STOREFRONT_PROXY_PORT || 9998);

try {
    const { protocol, hostname } = new URL(process.env.PROXY_URL || process.env.APP_URL);
    hostName = `${protocol}//${hostname}`;
    if (proxyPort !== 80 && proxyPort !== 443) {
        hostName += `:${proxyPort}`;
    }
} catch (e) {
    hostName = undefined;
}

const pluginEntries = (() => {
    const pluginFile = path.resolve(process.env.PROJECT_ROOT, 'var/plugins.json');

    if (!fs.existsSync(pluginFile)) {
        throw new Error(`The file ${pluginFile} could not be found. Try bin/console bundle:dump to create this file.`);
    }

    const pluginDefinition = JSON.parse(fs.readFileSync(pluginFile, 'utf8'));

    return Object.entries(pluginDefinition)
        .filter(([, definition]) => definition.technicalName !== 'storefront' && !!definition.storefront && !!definition.storefront.entryFilePath && !process.env.hasOwnProperty('SKIP_' + definition.technicalName.toUpperCase().replace(/-/g, '_')))
        .map(([name, definition]) => {
            console.log(chalk.green(`# Plugin "${name}": Injected successfully`));

            const technicalName = definition.technicalName || name.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();
            const htmlFilePath = path.resolve(process.env.PROJECT_ROOT, definition.basePath, definition.storefront.path, '..', 'index.html');
            const hasHtmlFile = fs.existsSync(htmlFilePath);

            return {
                name,
                technicalName: technicalName,
                technicalFolderName: technicalName.replace(/(-)/g, '').toLowerCase(),
                basePath: path.resolve(process.env.PROJECT_ROOT, definition.basePath),
                path: path.resolve(process.env.PROJECT_ROOT, definition.basePath, definition.storefront.path),
                filePath: path.resolve(process.env.PROJECT_ROOT, definition.basePath, definition.storefront.entryFilePath),
                hasHtmlFile,
                webpackConfig: definition.storefront.webpack ? path.resolve(process.env.PROJECT_ROOT, definition.basePath, definition.storefront.webpack) : null,
            };
        });
})();

const coreConfig = {
    cache: true,
    experiments: {
        topLevelAwait: true,
    },
    devServer: (() => {
        if (isHotMode) {
            return {
                static: {
                    directory: path.resolve(__dirname, 'dist'),
                },
                open: false,
                devMiddleware: {
                    publicPath: `${hostName}/`,
                    stats: {
                        colors: true,
                    },
                },
                hot: true,
                compress: false,
                allowedHosts: 'all',
                port: parseInt(process.env.STOREFRONT_ASSETS_PORT || 9999, 10),
                host: '127.0.0.1',
                client: {
                    webSocketURL: {
                        hostname: '0.0.0.0',
                        protocol: 'ws',
                        port: parseInt(process.env.STOREFRONT_ASSETS_PORT || 9999, 10),
                    },
                    logging: 'warn',
                    overlay: {
                        warnings: false,
                        errors: true,
                    },
                },
                headers: {
                    'Access-Control-Allow-Origin': '*',
                },
                watchFiles: {
                    paths: [`${themeFiles.basePath}/**/*.twig`],
                    options: {
                        persistent: true,
                        cwd: projectRootPath,
                        ignorePermissionErrors: true,
                    },
                },
            }
        }
        return {};
    })(),
    devtool: (() => {
        if (isDevMode || isHotMode) {
            return 'eval-cheap-module-source-map';
        }

        if (isProdMode) {
            return false;
        }

        return 'inline-cheap-source-map';
    })(),
    context: path.resolve(__dirname, 'src'),
    mode: isProdMode ? 'production' : 'development',
    ...(() => {
        if (isHotMode) {
            return {
                entry: {
                    app: [],
                    storefront: [],
                },
            };
        }

        return {};
    })(),
    module: {
        rules: [
            {
                test: /\.m?(t|j)s$/,
                exclude: /(node_modules|bower_components|vendors)\/(?!(are-you-es5|fs-extra|query-string|split-on-first)\/).*/,
                use: [
                    {
                        loader: 'babel-loader',
                        options: {
                            ...babelrc,
                            cacheDirectory: true,
                        },
                    },
                ],
            },
            {
                test: /\.(woff(2)?|ttf|eot|svg)(\?v=\d+\.\d+\.\d+)?$/,
                include: [
                    path.resolve(__dirname, 'vendor/Inter-3.5/font'),
                ],
                use: [
                    {
                        loader: 'file-loader',
                        options: {
                            name: '[name].[ext]',
                            outputPath: 'assets/font',
                            publicPath: '../assets/font',
                        },
                    },
                ],
            },
            {
                test: /\.(jp(e)g|png|gif|svg)(\?v=\d+\.\d+\.\d+)?$/,
                exclude: [
                    path.resolve(__dirname, 'vendor/Inter-3.5/font'),
                ],
                use: [
                    {
                        loader: 'file-loader',
                        options: {
                            name: '[name].[ext]',
                            outputPath: 'assets/img',
                            publicPath: '../assets/img',
                        },
                    },
                ],
            },
            ...(() => {
                if (isHotMode) {
                    return [
                        {
                            test: /\.scss$/,
                            use: [
                                {
                                    loader: 'style-loader',
                                },
                                {
                                    loader: 'css-loader',
                                    options: {
                                        sourceMap: true,
                                        // Skip auto resolving of url(), see: https://github.com/webpack-contrib/css-loader/blob/master/CHANGELOG.md#400-2020-07-25
                                        url: false,
                                    },
                                },
                                {
                                    loader: 'postcss-loader', // needs to be AFTER css/style-loader and BEFORE sass-loader
                                    options: {
                                        sourceMap: true,
                                        postcssOptions: {
                                            config: false,
                                        },
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
                            test: /\.(woff(2)?|ttf|eot|svg|otf)$/,
                            use: [
                                {
                                    loader: 'file-loader',
                                    options: {
                                        name: '[name].[ext]',
                                        outputPath: 'fonts/',
                                    },
                                },
                            ],
                        },
                    ]
                }

                return [];
            })(),
        ],
    },
    name: 'shopware-6-storefront',
    optimization: {
        moduleIds: 'deterministic',
        chunkIds: 'named',
        ...(() => {
            if (isProdMode) {
                return {
                    minimizer: [
                        new TerserPlugin({
                            terserOptions: {
                                ecma: 5,
                                warnings: false,
                            },
                            parallel: true,
                        }),
                    ],
                }
            }

            return {}
        })(),
    },
    output: {
        path: path.resolve(__dirname, 'dist'),
        filename: './storefront/[name].js',
        // publicPath: 'auto',
        chunkFilename: './storefront/[name].js',
    },
    performance: {
        hints: false,
    },
    plugins: [
        new webpack.NoEmitOnErrorsPlugin(),
        new webpack.ProvidePlugin({
            Popper: ['popper.js', 'default'],
        }),
        new MiniCssExtractPlugin({
            filename: './css/[name].css',
            chunkFilename: './css/[name].css',
        }),
        ...(() => {
            if (isHotMode) {
                return [
                    new webpack.HotModuleReplacementPlugin(),
                ];
            }

            return []
        })(),
        /**
         * We hard-code the relocation of the core storefront.js from the configured webpack output directory
         * to /dist/storefront/js/ to avoid breaking changes in the current theme.json which requires this location.
         */
        new (class SwCopyAfterBuildPlugin {
            apply(compiler) {
                compiler.hooks.done.tap('sw-copy-after-build', () => {
                    const from = path.resolve(__dirname, 'dist/js/storefront.js');
                    const to = path.resolve(__dirname, 'dist/storefront/js/storefront.js');

                    if (!fs.existsSync(from)) {
                        return;
                    }

                    // Create output directory
                    fs.mkdirSync(path.resolve(__dirname, 'dist/storefront/js'), { recursive: true });

                    // Move file to new location which is used by theme.json
                    fs.renameSync(from, to);
                });
            }
        })(),
    ],
    resolve: {
        extensions: [ '.ts', '.tsx', '.js', '.jsx', '.json', '.less', '.sass', '.scss', '.twig' ],
        modules: [
            // statically add the storefront node_modules folder, so sw plugins can resolve it
            path.resolve(__dirname, 'node_modules'),
        ],
        alias: {
            src: path.resolve(__dirname, 'src'),
            assets: path.resolve(__dirname, 'assets'),
            scss: path.resolve(__dirname, 'src/scss'),
            vendor: path.resolve(__dirname, 'vendor'),
        },
    },
    stats: 'minimal',
    target: 'web',
};

if (isHotMode) {
    /**
     * Converts the feature config JSON to a SCSS map syntax.
     * This allows reading of the feature flag config inside SCSS via `map.get` function.
     *
     * Output example:
     * $sw-features: ("FEATURE_NEXT_1234": false, "FEATURE_NEXT_1235": true);
     *
     * @see https://sass-lang.com/documentation/values/maps
     */
    const scssFeatureConfig = (() => {
        // Return an empty SCSS map when feature dump cannot be found. All feature checks will be false.
        if (!features) {
            return '$sw-features: ();'
        }

        const featuresScss = Object.entries(features).map(([key, val]) => {
            return `'${key}': ${val}`;
        }).join(',');

        return `$sw-features: (${featuresScss});`;
    })();

    /**
     * Adds all entry points from the theme-variables.json "style" array as imports to one string.
     */
    const scssEntryFilePath = path.resolve(projectRootPath, 'var/theme-entry.scss');
    const scssDumpedVariables = path.resolve(projectRootPath, 'var/theme-variables.scss');
    const scssEntryFileContent = (() => {
        const themeConfig = JSON.parse(fs.readFileSync(path.resolve(projectRootPath, 'files/theme-config/index.json'), { encoding: 'utf8' }));
        const themeId = Object.values(themeConfig)[0];

        const fileComment = '// ATTENTION! This file is auto generated by webpack.hot.config.js and should not be edited.\n\n';
        const dumpedVariablesImport = `@import "${scssDumpedVariables}";\n`;
        const assetOverrides = `
            $app-css-relative-asset-path: '/theme/${themeId}/assets';
            $sw-asset-public-url: '';
            $sw-asset-theme-url: '';
            $sw-asset-asset-url: '';
            $sw-asset-sitemap-url: '';
        `;

        const collectedImports = [dumpedVariablesImport, assetOverrides, ...themeFiles.style.map((value) => {
            return `@import "${value.filepath}";\n`;
        })];

        return fileComment + scssFeatureConfig + collectedImports.join('');
    })();

    /**
     * Auto generates file under given path.
     * The generated file is used by webpack.hot.config.js as a single entry point.
     */
    try {
        fs.writeFileSync(scssEntryFilePath, scssEntryFileContent, 'utf8');
    } catch (error) {
        throw new Error(`Unable to write file "${scssEntryFilePath}". ${error.message}`);
    }

    coreConfig.entry.app = [scssEntryFilePath];

    coreConfig.entry.storefront = [...themeFiles.script].map((file) => {
        return file.filepath;
    });
}

// Create all plugin configs
const pluginConfigs = pluginEntries.map((plugin) => {

    // add custom config optionally when it exists
    let customPluginConfig = {};

    if (plugin.webpackConfig) {
        console.log(chalk.green(`# Plugin "${plugin.name}": Extends the webpack config successfully`));

        const pluginWebpackConfigFn = require(path.resolve(plugin.webpackConfig));

        customPluginConfig = pluginWebpackConfigFn({
            basePath: plugin.basePath,
            env: process.env.NODE_ENV,
            config: coreConfig,
            name: plugin.name,
            technicalName: plugin.technicalName,
            technicalFolderName: plugin.technicalFolderName,
            plugin,
        });
    }

    return merge([
        coreConfig,
        {
            name: plugin.technicalName,
            entry: {
                [plugin.technicalName]: plugin.filePath,
            },
            output: {
                path: path.resolve(plugin.path, '../dist/storefront'),
                filename: `./js/${plugin.technicalName}/[name].js`,
                chunkFilename: `./js/${plugin.technicalName}/[name].js`,
            },
            plugins: [
                new WebpackBar({
                    name: plugin.name,
                    color: 'green',
                }),
            ],
            optimization: {
                splitChunks: false,
                runtimeChunk: false,
            },
        },
        customPluginConfig,
    ]);
});

const mergedCoreConfig = merge([
    coreConfig,
    {
        entry: {
            storefront: `${path.resolve('src')}/main-async.js`,
        },
        plugins: [
            new WebpackBar({
                name: 'Shopware 6 Storefront',
                color: 'blue',
            }),
        ],
    },
]);

// Use multi-compiler
module.exports = [mergedCoreConfig, ...pluginConfigs];
