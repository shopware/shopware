/**
 * @sw-package framework
 */
const chalk = require('chalk');

const { merge } = require('webpack-merge');
const path = require('path');
const webpack = require('webpack');
const fs = require('fs');
const TerserPlugin = require('terser-webpack-plugin');
const WebpackBar = require('webpackbar');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const FilenameToChunkNamePlugin = require('./build/webpack/FilenameToChunkNamePlugin');

if (process.env.IPV4FIRST) {
    require('dns').setDefaultResultOrder('ipv4first');
}

const isProdMode = process.env.NODE_ENV === 'production';
const isHotMode = process.env.MODE === 'hot';
const isDevMode = process.env.NODE_ENV !== 'production' && process.env.MODE !== 'hot';
const isCoverage = !!process.env.PWC_COVERAGE;

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
const proxyPort = parseInt(process.env.STOREFRONT_PROXY_PORT || 9998, 10);

try {
    const { protocol, hostname } = new URL(process.env.PROXY_URL || process.env.APP_URL);
    hostName = `${protocol}//${hostname}`;
    if (proxyPort !== 80 && proxyPort !== 443) {
        hostName += `:${proxyPort}`;
    }
} catch (e) {
    hostName = undefined;
}

const useExtensionTwigWatch = process.env.SHOPWARE_STOREFRONT_SKIP_EXTENSION_TWIG_WATCH !== '1';
let watchFilePaths = isHotMode ? [`${themeFiles.basePath}/**/*.twig`] : [];

const pluginEntries = (() => {
    const pluginFile = path.resolve(process.env.PROJECT_ROOT, 'var/plugins.json');

    if (!fs.existsSync(pluginFile)) {
        throw new Error(`The file ${pluginFile} could not be found. Try bin/console bundle:dump to create this file.`);
    }

    const pluginDefinition = JSON.parse(fs.readFileSync(pluginFile, 'utf8'));

    return Object.entries(pluginDefinition)
        .filter(([, definition]) => definition.technicalName !== 'storefront' && !!definition.storefront && !!definition.storefront.entryFilePath && !process.env.hasOwnProperty('SKIP_' + definition.technicalName.toUpperCase().replace(/-/g, '_')))
        .map(([name, definition]) => {
            console.log(chalk.bgGreenBright.black(`# Plugin "${name}": Injected successfully`));

            const technicalName = definition.technicalName || name.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();
            const htmlFilePath = path.resolve(process.env.PROJECT_ROOT, definition.basePath, definition.storefront.path, '..', 'index.html');
            const hasHtmlFile = fs.existsSync(htmlFilePath);

            if (isHotMode && useExtensionTwigWatch && definition.views?.length > 0) {
                watchFilePaths = watchFilePaths.concat(definition.views.map((view) => {
                    return `${path.resolve(projectRootPath, definition.basePath, view)}/**/*.twig`;
                }));
            }

            return {
                name,
                technicalName: technicalName,
                technicalFolderName: technicalName.replace(/(-)/g, '').toLowerCase(),
                basePath: path.resolve(process.env.PROJECT_ROOT, definition.basePath),
                path: path.resolve(process.env.PROJECT_ROOT, definition.basePath, definition.storefront.path),
                filePath: path.resolve(process.env.PROJECT_ROOT, definition.basePath, definition.storefront.entryFilePath),
                isTheme: definition.isTheme,
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
    entry: {},
    module: {
        rules: [
            // Primary transpilation with SWC
            {
                test: /\.m?(t|j)s$/,
                exclude: /(node_modules|bower_components|vendors)\/(?!(are-you-es5|fs-extra|query-string|split-on-first)\/).*/,
                use: [
                    {
                        loader: require.resolve('swc-loader'),
                        options: {
                            env: {
                                mode: 'entry',
                                coreJs: '3.34.0',
                                // ensure swc respects your browserslist
                                targets: require('browserslist').loadConfig({ path: './' }),
                            },
                            jsc: {
                                parser: {
                                    syntax: 'typescript',
                                    tsx: false,
                                },
                                transform: {
                                    useDefineForClassFields: false,
                                },
                            },
                            // NOTE: disable SWC sourceMaps when coverage is active to avoid instrumenter .inputSourceMap issues
                            sourceMaps: isCoverage ? false : true,
                        },
                    },
                ],
            },

            // fonts
            {
                test: /\.(woff(2)?|ttf|eot|svg)(\?v=\d+\.\d+\.\d+)?$/,
                use: [
                    {
                        loader: require.resolve('file-loader'),
                        options: {
                            name: '[name].[ext]',
                            outputPath: 'assets/font',
                            publicPath: '../assets/font',
                        },
                    },
                ],
            },

            // images
            {
                test: /\.(jp(e)g|png|gif|svg)(\?v=\d+\.\d+\.\d+)?$/,
                use: [
                    {
                        loader: require.resolve('file-loader'),
                        options: {
                            name: '[name].[ext]',
                            outputPath: 'assets/img',
                            publicPath: '../assets/img',
                        },
                    },
                ],
            },

            // SCSS in hot mode
            ...(() => {
                if (isHotMode) {
                    return [
                        {
                            test: /\.scss$/,
                            use: [
                                { loader: require.resolve('style-loader') },
                                {
                                    loader: require.resolve('css-loader'),
                                    options: { sourceMap: true, url: false },
                                },
                                {
                                    loader: require.resolve('postcss-loader'),
                                    options: { sourceMap: true, postcssOptions: { config: false } },
                                },
                                {
                                    loader: require.resolve('sass-loader'),
                                    options: { sourceMap: true },
                                },
                            ],
                        },
                        {
                            test: /\.(woff(2)?|ttf|eot|svg|otf)$/,
                            use: [
                                {
                                    loader: require.resolve('file-loader'),
                                    options: { name: '[name].[ext]', outputPath: 'fonts/' },
                                },
                            ],
                        },
                    ];
                }
                return [];
            })(),

            // Coverage instrumentation (post) only when PWC_COVERAGE=1
            ...(() => {
                if (!isCoverage) return [];

                return [
                    {
                        test: /\.m?(t|j)s$/,
                        exclude: /(node_modules|\.spec\.ts$)/,
                        enforce: 'post', // <-- valid rule-level 'enforce'
                        use: [
                            {
                                loader: require.resolve('babel-loader'),
                                options: {
                                    babelrc: false,
                                    configFile: false,
                                    sourceMaps: true,
                                    presets: [
                                        [
                                            require.resolve('@babel/preset-env'),
                                            {
                                                targets: require('browserslist').loadConfig({ path: './' }),
                                                modules: false,
                                            },
                                        ],
                                    ],
                                    plugins: [
                                        [ require.resolve('babel-plugin-istanbul'), { exclude: ['**/*.spec.*', '**/*.test.*'] } ],
                                    ],
                                },
                            },
                        ],
                    },
                ];
            })(),
        ],
    },
    name: 'shopware-6-storefront',
    optimization: {
        moduleIds: 'deterministic',
        chunkIds: false,
        ...(() => {
            if (isProdMode) {
                return {
                    minimizer: [
                        new TerserPlugin({
                            minify: TerserPlugin.swcMinify,
                            terserOptions: { compress: true },
                            parallel: true,
                        }),
                    ],
                };
            }
            return {};
        })(),
    },
    output: {
        path: path.resolve(__dirname, 'dist'),
        filename: './storefront/[name].js',
        chunkFilename: isHotMode ? './storefront/[name].js' : './storefront/[name].[chunkhash:6].js',
        clean: (isHotMode ? false : { keep: /assets\// }),
    },
    performance: { hints: false },
    plugins: [
        new webpack.NoEmitOnErrorsPlugin(),
        new webpack.ProvidePlugin({ Popper: ['popper.js', 'default'] }),
        new MiniCssExtractPlugin({ filename: './css/[name].css', chunkFilename: './css/[name].css' }),
        new webpack.ids.DeterministicChunkIdsPlugin({ maxLength: 5 }),
        new FilenameToChunkNamePlugin(),
        ...(() => (isHotMode ? [ new webpack.HotModuleReplacementPlugin() ] : []))(),
        ...(() => {
            if (fs.existsSync(path.resolve(__dirname, 'static'))) {
                return [
                    new CopyWebpackPlugin({
                        patterns: [
                            {
                                from: path.resolve(__dirname, 'static'),
                                to: path.resolve(__dirname, '../../../Resources/public/assets'),
                                globOptions: { ignore: ['.*'] },
                            },
                        ],
                    }),
                ];
            }
            return [];
        })(),
    ],
    resolve: {
        extensions: ['.ts', '.tsx', '.js', '.jsx', '.json', '.less', '.sass', '.scss', '.twig'],
        modules: [
            path.resolve(__dirname, 'node_modules'),
            path.resolve(__dirname, 'node_modules/@shopware-ag/dive/node_modules'),
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

// Create plugin-specific configs
const pluginConfigs = pluginEntries.map((plugin) => {
    let customPluginConfig = {};

    if (plugin.webpackConfig) {
        // eslint-disable-next-line no-console
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

    if (isHotMode) {
        const scriptAssetNames = themeFiles.script.map(script => script.assetName);
        const pluginNameDashes = plugin.name
            .replace(/[A-Z]/g, m => '-' + m.toLowerCase())
            .replace(/^-/, '');

        if (plugin.isTheme && scriptAssetNames.includes(pluginNameDashes)) {
            console.log(chalk.bgYellowBright.black(`# Compiling Theme "${plugin.name}" in HotMode`));
        }
        if (plugin.isTheme && !scriptAssetNames.includes(pluginNameDashes)) {
            console.log(chalk.bgHex('#fbbc39').black(`# Skipping "${plugin.name}" Theme in HotMode`));
            return merge([ coreConfig, {}, customPluginConfig ]);
        }
    }

    return merge([
        coreConfig,
        {
            name: plugin.technicalName,
            entry: { [plugin.technicalName]: plugin.filePath },
            output: {
                path: isHotMode ? path.resolve(__dirname, 'dist') : path.resolve(plugin.path, '../dist/storefront'),
                filename: isHotMode ? `./${plugin.technicalName}/[name].js` : `./js/${plugin.technicalName}/[name].js`,
                chunkFilename: isHotMode ? `./${plugin.technicalName}/[name].js` :
                    isDevMode ? `./js/${plugin.technicalName}/[name].js` : `./js/${plugin.technicalName}/[name].[chunkhash:6].js`,
                clean: !isHotMode,
            },
            resolve: { modules: ['node_modules'] },
            plugins: [ new WebpackBar({ name: plugin.name, color: 'green' }) ],
            optimization: { splitChunks: false, runtimeChunk: false },
        },
        customPluginConfig,
    ]);
});

if (isHotMode) {
    const scssFeatureConfig = (() => {
        if (!features) return '$sw-features: ();';
        const featuresScss = Object.entries(features).map(([key, val]) => `'${key}': ${val}`).join(',');
        return `$sw-features: (${featuresScss});`;
    })();

    const scssEntryFilePath = path.resolve(projectRootPath, 'var/theme-entry.scss');
    const themeConfig = JSON.parse(fs.readFileSync(path.resolve(projectRootPath, 'files/theme-config/index.json'), { encoding: 'utf8' }));
    const themeId = themeFiles.themeId ?? Object.values(themeConfig)[0];

    const scssDumpedFallbackVariables = path.resolve(projectRootPath, 'var/theme-variables.scss');
    const scssDumpedThemeVariables = path.resolve(projectRootPath, `var/theme-variables/${themeId}.scss`);
    const scssDumpedVariables = (fs.existsSync(scssDumpedThemeVariables)) ? scssDumpedThemeVariables : scssDumpedFallbackVariables;

    if (fs.existsSync(scssDumpedThemeVariables)) {
        console.log(chalk.bgCyanBright.black(`# Theme variable file: ${scssDumpedVariables}`));
    }
    if (!fs.existsSync(scssDumpedThemeVariables)) {
        console.log(chalk.bgHex('#b30000').white(
            '\n# No custom theme-variables found. Execute ' + chalk.bold('bin/console theme:compile') + ' to create these files'
        ));
        console.log(chalk.bgHex('#b30000').white('# Styling can be wrong in HotMode. Falling back to default var/theme-variables.scss\n'));
    }

    const scssEntryFileContent = (() => {
        const fileComment = '// ATTENTION! This file is auto generated by webpack.hot.config.js and should not be edited.\n\n';
        const dumpedVariablesImport = `@import "${scssDumpedVariables}";\n`;
        const assetOverrides = `
            $app-css-relative-asset-path: '/theme/${themeId}/assets';
            $sw-asset-public-url: '';
            $sw-asset-theme-url: '';
            $sw-asset-asset-url: '';
            $sw-asset-sitemap-url: '';
        `;
        const collectedImports = [dumpedVariablesImport, assetOverrides, ...themeFiles.style.map((value) => `@import "${value.filepath}";\n`)];
        return fileComment + scssFeatureConfig + collectedImports.join('');
    })();

    try {
        fs.writeFileSync(scssEntryFilePath, scssEntryFileContent, 'utf8');
    } catch (error) {
        throw new Error(`Unable to write file "${scssEntryFilePath}". ${error.message}`);
    }

    coreConfig.entry['hot-reloading'] = [scssEntryFilePath];
}

const mergedCoreConfig = merge([
    coreConfig,
    {
        devServer: (() => {
            if (isHotMode) {
                return {
                    static: { directory: path.resolve(__dirname, 'dist') },
                    open: false,
                    devMiddleware: { publicPath: `${hostName}/`, stats: { colors: true } },
                    hot: false,
                    compress: false,
                    allowedHosts: 'all',
                    port: parseInt(process.env.STOREFRONT_ASSETS_PORT || 9999, 10),
                    host: '127.0.0.1',
                    client: {
                        webSocketURL: { hostname: '0.0.0.0', protocol: 'ws', port: parseInt(process.env.STOREFRONT_ASSETS_PORT || 9999, 10) },
                        logging: 'warn',
                        overlay: { warnings: false, errors: true },
                    },
                    headers: { 'Access-Control-Allow-Origin': '*' },
                    watchFiles: { paths: watchFilePaths, options: { persistent: true, cwd: projectRootPath, ignorePermissionErrors: true } },
                };
            }
            return {};
        })(),
        entry: {
            storefront: `${path.resolve('src')}/main.js`,
        },
        plugins: [
            new WebpackBar({
                name: 'Shopware 6 Storefront',
                color: '#118cff',
            }),
        ],
    },
]);

// Use multi-compiler
module.exports = [mergedCoreConfig, ...pluginConfigs];
// Default is infinity
module.exports.parallelism = process.env.SHOPWARE_BUILD_PARALLELISM ? parseInt(process.env.SHOPWARE_BUILD_PARALLELISM, 10) : Infinity;
