const path = require('path');
const appModulePath = require('app-module-path');
const HtmlWebpackPlugin = require('html-webpack-plugin');
const merge = require('webpack-merge');
const dotenv = require('dotenv');
const fs = require('fs');
const process = require('process');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const config = require('../config');

const projectRoot = process.env.PROJECT_ROOT || '';

/**
 * Resolves a given directory from the root path of the project
 *
 * @param {String} directory
 * @returns {String}
 */
exports.resolveFromRootPath = function (directory) {
    return path.join(projectRoot, directory);
};

/**
 * Resolves a given directory
 *
 * @param {String} directory
 * @returns {String}
 */
exports.resolve = function (directory) {
    return path.join(__dirname, '..', directory);
};

/**
 * Defines the paths which will be included for path mapping.
 *
 * @type {Array<String>}
 */
const includePaths = [
    exports.resolve('src'),
    exports.resolve('test')
];

/**
 * Tries to load the plugin definitions from the dumped configuration file.
 *
 * @param {String} definitionFilePath
 * @param {Boolean} silent
 * @returns {Array}
 */
exports.getPluginDefinitions = function (definitionFilePath, silent = false) {
    const plugins = [];
    const path = exports.resolveFromRootPath(definitionFilePath);

    if (!fs.existsSync(path)) {
        return [];
    }

    if (!silent) {
        console.log('# Loading Shopware administration plugin definitions');
    }

    try {
        if (!silent) {
            console.log(`Trying to load plugin definitions from "${path}"`);
        }
        const pluginsDefinition = require(path);

        Object.keys(pluginsDefinition).forEach((pluginName) => {
            const pluginDefinition = pluginsDefinition[pluginName];
            plugins.push({
                name: pluginName,
                basePath: pluginDefinition.basePath,
                viewPath: pluginDefinition.viewPath,
                entryFile: exports.resolveFromRootPath(pluginDefinition.entry),
                webpackConfig: (pluginDefinition.webpackConfig === false ?
                    pluginDefinition.webpackConfig :
                    exports.resolveFromRootPath(pluginDefinition.webpackConfig))
            });
        });

        if (!silent) {
            console.log(`Found ${plugins.length} plugin definition(s): ${plugins.map(plugin => plugin.name).join(', ')}`);
        }
    } catch (err) {
        if (!silent) {
            console.log(`Could not load Shopware administration plugin definitions from "${path}"`);
        }
    }

    // Generate an empty line
    if (!silent) {
        console.log();
    }
    return plugins;
};

/**
 * Iterates over the plugin definitions and registers a new entry point into the webpack configuration. If the plugin
 * comes with a custom webpack configuration, it will be merged into our configuration, which allows the plugin to add
 * new loaders for TypeScript or SASS compilation.
 *
 * @param {Object} baseWebPackConfig
 * @param {Array} pluginList
 * @param {Boolean} insertDevClient
 * @returns {Object}
 */
exports.iteratePluginDefinitions = function (baseWebPackConfig, pluginList, insertDevClient = true) {
    console.log('# Adding Shopware administration plugins to Webpack');

    baseWebPackConfig = exports.pluginDefinitionWalker(baseWebPackConfig, pluginList);

    // Apply the dev client to the entry definition
    Object.keys(baseWebPackConfig.entry).forEach((name) => {
        if (insertDevClient) {
            baseWebPackConfig.entry[name] = ['./build/dev-client'].concat(baseWebPackConfig.entry[name]);
        } else {
            baseWebPackConfig.entry[name];
        }
    });

    // Generate empty line
    console.log('');
    return baseWebPackConfig;
};

/**
 * Iterates the plugin list and alters the provided configuration.
 *
 * @param {Object} baseWebPackConfig
 * @param {Array} pluginList
 * @returns {Object}
 */
exports.pluginDefinitionWalker = function (baseWebPackConfig, pluginList) {
    pluginList.forEach((plugin) => {
        const name = plugin.name;

        // Will be provided to the webpack config
        const customWebpackConfigParams = {
            env: process.env.NODE_ENV,
            config: baseWebPackConfig,
            name
        };

        baseWebPackConfig.entry[name] = plugin.entryFile;
        includePaths.push(`${plugin.viewPath}src`);

        if (plugin.webpackConfig) {
            console.log(`Plugin "${name}" using an extended Webpack config`);
            // Enable loading node script for the custom webpack.config.js, webpack uses their own resolving solution
            appModulePath.addPath(plugin.basePath);

            // Get the custom configuration.
            const customConfig = require(plugin.webpackConfig)(Object.assign({}, {
                basePath: plugin.basePath
            }, customWebpackConfigParams));
            baseWebPackConfig = merge(baseWebPackConfig, customConfig);
        } else {
            console.log(`Plugin "${name}" was injected into the Webpack config`);
        }
    });

    return baseWebPackConfig;
};

/**
 * Filters main assets from output
 *
 * @param {JSON} output
 * @returns {String}
 */
exports.filterAssetsOutput = function (output) {
    const filteredOutput = { ...output };

    ['', 'app', 'commons', 'runtime', 'vendors-node'].forEach((output) => {
        delete filteredOutput[output];
    });

    return JSON.stringify(filteredOutput);
};

/**
 * Injects the sw-devmode-loader into the given webpack configuration. It enables the feature "open in editor" for the
 * Vue.js DevTools when PhpStorm is installed on the developers system.
 *
 * @param {Object} webpackConfig
 * @returns {Object}
 */
exports.injectSwDevModeLoader = function (webpackConfig) {
    console.log('# Injecting SW DevMode Loader');
    console.log(`Additional custom loader directory "${path.resolve(__dirname, 'loaders')}" added.`);
    console.log(`JavaScript files in "${includePaths.join(', ')}" are affected by the loader.`);
    console.log('');

    return merge(webpackConfig, {
        // Insert our custom loader directory
        resolveLoader: {
            modules: [
                'node_modules',
                path.resolve(__dirname, 'loaders')
            ]
        },
        module: {
            // Configure the sw-devmode-loader.
            rules: [{
                test: /\.(js|tsx?|vue)$/,
                loader: 'sw-devmode-loader',
                include: includePaths
            }]
        }
    });
};

/**
 * Gets the chunks from the entry definition, so the HTML plugin knows what files it has to load.
 *
 * @param {Object} config
 * @returns {Array<String>}
 */
exports.getChunks = function (config) {
    console.log('# Collecting chunks. Each chunk will be a separate bundle');

    const chunks = Object.keys(config.entry).map((entry) => {
        return entry;
    });

    console.log(`The following chunks were collected: ${chunks.join(', ')}`);
    console.log();

    return chunks;
};

/**
 * Add the HTML Webpack plugin which injects the registered chunks to the devmode server.
 *
 * @param {Object} config
 * @param {Object} featureFlags
 * @returns {HtmlWebpackPlugin}
 */
exports.injectHtmlPlugin = function (config, featureFlags) {
    return new HtmlWebpackPlugin({
        filename: 'index.html',
        template: 'index.html.tpl',
        templateParameters: {
            featureFlags: JSON.stringify(featureFlags)
        },
        inject: false
    });
};

/**
 * Injects the include paths for the eslint-loader and babel-loader to the webpack config, which enables plugins to
 * have linting and babel support right out of the box.
 *
 * @param {Object} config
 * @param {Array} paths
 * @returns {Object}
 */
exports.injectIncludePathsToLoader = function (config, paths) {
    config.module.rules.forEach((rule, index) => {
        if (rule.loader === 'eslint-loader' || rule.loader === 'babel-loader') {
            config.module.rules[index].include = paths;
        }
    });

    return config;
};

/**
 * Returns the include paths
 *
 * @returns {Array<String>}
 */
exports.getIncludePaths = function () {
    return includePaths;
};

/**
 * Provides the path depending on the environment.
 *
 * @param {String} _path
 * @returns {String}
 */
exports.assetsPath = function (_path) {
    const assetsSubDirectory = process.env.NODE_ENV === 'production'
        ? config.build.assetsSubDirectory
        : config.dev.assetsSubDirectory;

    return path.posix.join(assetsSubDirectory, _path);
};

/**
 * Returns the css loader object for the webpack config
 * @param {Object} options
 * @returns {Object}
 */
exports.cssLoaders = function (options) {
    options = options || {};

    const cssLoader = {
        loader: 'css-loader',
        options: {
            sourceMap: options.sourceMap,
            url: false
        }
    };

    // generate loader string to be used with extract text plugin
    function generateLoaders(loader, loaderOptions) {
        const loaders = [MiniCssExtractPlugin.loader, cssLoader];
        if (loader) {
            loaders.push({
                loader: `${loader}-loader`,
                options: Object.assign({}, loaderOptions, {
                    sourceMap: options.sourceMap
                })
            });
        }

        return ['vue-style-loader'].concat(loaders);
    }

    // http://vuejs.github.io/vue-loader/en/configurations/extract-css.html
    return {
        css: generateLoaders(),
        postcss: generateLoaders(),
        less: generateLoaders('less', { javascriptEnabled: true }),
        sass: generateLoaders('sass', { indentedSyntax: true }),
        scss: generateLoaders('sass'),
        stylus: generateLoaders('stylus'),
        styl: generateLoaders('stylus')
    };
};

// Generate loaders for standalone style files (outside of .vue)
exports.styleLoaders = function (options) {
    const output = [];
    const loaders = exports.cssLoaders(options);
    for (const extension in loaders) {
        const loader = loaders[extension];
        output.push({
            test: new RegExp(`\\.${extension}$`),
            use: loader
        });
    }

    return output;
};

/**
 *
 * Loads the feature flag config from .env file and the filesystem
 *
 * @param {String} envFile
 * @returns {Object}
 */
exports.loadFeatureFlags = function (envFile) {
    const envResult = dotenv.config({ path: envFile });

    if (envResult.hasOwnProperty('error')) {
        console.error('utils-load-feature-flags', 'Unable to load .env file, no features registered.', envResult.error);
        return {};
    }

    const allNames = fs.readdirSync(path.join(__dirname, '../src/flag'))
        .filter((file) => {
            return file.indexOf('feature_') === 0;
        })
        .map(file => {
            return path.basename(
                file.substring(8),
                '.js'
            );
        });

    const allActive = Object.keys(envResult.parsed)
        .filter((key) => {
            return key.indexOf('FEATURE_') === 0;
        }).reduce((obj, key) => {
            const clearedKey = key
                .substring(8)
                .split('_')
                .map((part) => {
                    return part[0].toUpperCase() + part.substr(1).toLowerCase();
                })
                .join('')
                .replace(/^./, (part) => { return part.toLowerCase(); });

            if (envResult.parsed[key] === '1') {
                obj[clearedKey] = true;
            }
            return obj;
        }, {});

    const flagConfig = {};
    allNames.forEach((flagName) => {
        flagConfig[flagName] = allActive.hasOwnProperty(flagName);
    });

    return flagConfig;
};

