const { join, isAbsolute } = require('path');
const fs = require('fs');
const { addPath } = require('app-module-path');
const merge = require('webpack-merge');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const WebpackCopyAfterBuildPlugin = require('@shopware/webpack-copy-after-build');

const projectRoot = process.env.PROJECT_ROOT || '';

/**
 * Resolves a given directory from the root path of the project
 *
 * @param {String} directory
 * @returns {String}
 */
function resolveFromRootPath(directory) {
    if (isAbsolute(directory)) {
        return directory;
    }

    return join(projectRoot, directory);
}

function toKebabCase(val) {
    return val.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();
}

/**
 * Valid sections
 *
 * @type {string[]}
 */
const sections = [
    'storefront',
    'administration'
];

/**
 * Resolves a given directory
 *
 * @param {String} directory
 * @returns {String}
 */
function resolve(directory) {
    return join(__dirname, '..', directory);
}

/**
 * Contains a collection of functions which sanitizes the plugin list for the storefront and administration
 */
class WebpackPluginInjector {
    /**
     * Class constructor which sets up the injector
     *
     * @constructor
     * @param {String} filePath Path to definition file
     * @param {Object} webpackConfig
     * @param {String} section Either 'storefront' or 'administration'
     * @param {boolean} [silent=false]
     * @returns {void}
     */
    constructor(filePath, webpackConfig, section, silent = false) {
        this._section = section;
        this._silent = silent;
        this._filePath = filePath;
        this._webpackConfig = webpackConfig;
        this._env = process.env.NODE_ENV;

        if (!sections.includes(section)) {
            throw new Error(`Section "${section}" is not a valid section. Available sections: ${sections.join(', ')}`);
        }

        // Include paths for the webpack loaders
        this._includePaths = [
            resolve('../administration/src'),
            resolve('../administration/test')
        ];

        let content;
        if (typeof this.filePath === 'string') {
            content = WebpackPluginInjector.getPluginDefinitionContent(this.filePath);
        } else {
            content = this.filePath;
        }
        this._plugins = this.getPluginsBySection(content);

        if (!this._plugins.length) {
            return;
        }

        if (this.env) {
            this.registerPluginsToWebpackConfig(this._plugins);
        }

        if (this.env === 'production' || this.env === 'development') {
            this.injectCopyPluginConfig(this._plugins);
        }
    }

    /**
     * General logging function which provides a unified style of log messages for developers.
     *
     * @param {String} [name='Webpack Plugin Injector']
     * @param {...String|Array|Date|Number|Object} message
     * @returns {boolean}
     */
    warn(name = 'Webpack Plugin Injector', ...message) {
        if (this.silent) {
            return false;
        }

        message.unshift(`# ${name}:`);
        console.warn.apply(this, message);

        return true;
    }

    /**
     * Checks if the definition exists, loads it and parses it as JSON.
     *
     * @static
     * @param {String} definitionFile
     * @return {Object}
     */
    static getPluginDefinitionContent(definitionFile) {
        const fullPathDefinitionFile = resolveFromRootPath(definitionFile);

        if (!fs.existsSync(fullPathDefinitionFile)) {
            throw new Error('Definition file does not exists');
        }

        let definition;
        const content = fs.readFileSync(fullPathDefinitionFile, 'utf8');
        try {
            definition = JSON.parse(content);
        } catch (err) {
            throw new Error('The definition file is not a valid JSON file');
        }

        return definition;
    }

    /**
     * Parses the plugin definition file and split them into the two sections storefront and administration.
     *
     * @param {Object} pluginDefinitions
     * @return {Object<Array>}
     */
    parsePluginDefinitions(pluginDefinitions) {
        const plugins = {
            administration: [],
            storefront: []
        };

        Object.keys(pluginDefinitions).forEach((pluginName) => {
            const pluginDefinition = pluginDefinitions[pluginName];

            sections.forEach((section) => {
                if (pluginDefinition[section] && pluginDefinition[section].entryFilePath) {
                    const plugin = WebpackPluginInjector.getPluginConfig(pluginName, pluginDefinition, section);
                    plugins[section].push(plugin);
                }
            });
        });

        return plugins;
    }

    /**
     * Returns the plugin configuration with sanitized paths. The method also terminates if the plugin contains a custom
     * webpack configuration which we have to merge.
     *
     * @static
     * @param {String} pluginName
     * @param {Object} pluginDefinition
     * @param {String} section
     * @return {Object}
     */
    static getPluginConfig(pluginName, pluginDefinition, section) {
        const basePath = resolveFromRootPath(pluginDefinition.basePath);
        const hasCustomWebpackConfig = (pluginDefinition[section].webpack !== null);
        const hasCustomStyleFiles = Object.prototype.hasOwnProperty.call(pluginDefinition[section], 'styleFiles')
            ? pluginDefinition[section].styleFiles.length > 0
            : false;
        const webpackConfigPath = !hasCustomWebpackConfig ? null
            : join(basePath, pluginDefinition[section].webpack);

        const assetPaths = [];
        if (pluginDefinition.administration) {
            assetPaths.push(join(basePath, pluginDefinition.administration.path, '../static'));
        }
        if (pluginDefinition.storefront) {
            assetPaths.push(join(basePath, pluginDefinition.storefront.path, '../static'));
        }

        return {
            basePath,
            hasCustomWebpackConfig,
            hasCustomStyleFiles,
            webpackConfigPath,
            pluginName,
            assetPaths,
            styleFiles: pluginDefinition[section].styleFiles,
            technicalName: pluginDefinition.technicalName || toKebabCase(pluginName),
            viewPath: pluginDefinition.views.map((path) => join(basePath, path)),
            entryFile: join(basePath, pluginDefinition[section].entryFilePath)
        };
    }

    /**
     * Provides all registered plugins for a section.
     *
     * @param {String} content
     * @return {Array|null}
     */
    getPluginsBySection(content) {
        const plugins = this.parsePluginDefinitions(content);
        return plugins[this.section] || null;
    }

    /**
     * Iterates the plugin list, adds custom webpack configs to the provided Webpack config, alters
     * the Node.js' module path resolving to enable plugins with a custom config to load their own Node.js modules.
     *
     * @param {Array} plugins
     * @return {Object} modified webpack config
     */
    registerPluginsToWebpackConfig(plugins) {
        plugins.forEach((plugin) => {
            const name = plugin.pluginName;
            const technicalName = plugin.technicalName;

            // Params for the custom webpack config
            const params = {
                env: process.env.NODE_ENV,
                config: this.webpackConfig,
                name,
                technicalName,
                plugin
            };

            if (!Object.prototype.hasOwnProperty.call(this.webpackConfig, 'entry')) {
                this.webpackConfig.entry = {};
            }

            if (process.env.MODE === 'hot' && this.section === 'storefront') {
                this.webpackConfig.entry.storefront.push(plugin.entryFile);
                this.webpackConfig.entry.storefront.push(...plugin.styleFiles);
            } else {
                // Add plugin as a new entry in the webpack config, respect NODE_ENV and insert the 'dev-client' if necessary
                this.webpackConfig.entry[technicalName] = (this.env === 'development' && this.section === 'administration')
                    ? ['./build/dev-client'].concat(plugin.entryFile)
                    : plugin.entryFile;
            }

            // Add plugin to include paths to support eslint
            this.includePaths.push(plugin.entryFile);

            if (!plugin.hasCustomWebpackConfig) {
                this.warn('Webpack Plugin Injector', `Plugin "${name}" injected as a new entry point`);
                return;
            }

            // Add new path to Node.js' module path resolving
            addPath(plugin.basePath);

            // Check if custom webpack config is a function
            const customConfigFn = require(plugin.webpackConfigPath); /* eslint-disable-line */
            if (typeof customConfigFn !== 'function') {
                throw new Error(`Webpack config for plugin ${name} needs to be a function to extend the provided Webpack.`);
            }

            // Call custom webpack config function and merge the new config
            const modifiedWebpackConfig = customConfigFn(Object.assign({}, {
                basePath: plugin.basePath
            }, params));

            this.webpackConfig = merge(this.webpackConfig, modifiedWebpackConfig);
            this.warn('Webpack Plugin Injector', `Plugin "${name}" injected with custom config`);
        });

        return this.injectIncludePathsToLoaders();
    }

    /**
     * Adds the additional include paths to the necessary loaders.
     *
     * @return {Object} modified webpack configuration
     */
    injectIncludePathsToLoaders() {
        const loaders = ['eslint-loader', 'babel-loader'];
        this.webpackConfig.module.rules.forEach((rule, index) => {
            if (loaders.includes(rule.loader)) {
                this.webpackConfig.module.rules[index].include = this.includePaths;
            }
        });

        return this.webpackConfig;
    }

    /**
     * In the production build we need additional plugins which are able to copy the plugin chunk to the destination
     * (e.g. plugin directory) and adds a copy plugin which copies the assets folder if available.
     *
     * @param {Array} plugins
     * @returns {Object} modified webpack config
     */
    injectCopyPluginConfig(plugins) {
        plugins.forEach((plugin) => {
            const pluginName = plugin.technicalName;
            const basePath = plugin.basePath;
            const assetPaths = plugin.assetPaths;
            let pluginPath = '';
            let publicStaticPath = '';

            if (this.section === 'administration') {
                pluginPath = `${basePath}Resources/public/administration`;
                publicStaticPath = `${basePath}Resources/public/static/`;
            } else {
                pluginPath = `${basePath}Resources/app/storefront/dist/storefront`;
                publicStaticPath = `${basePath}Resources/app/storefront/dist/static/`;
            }

            // Copy plugin chunk after build
            if (!this.webpackConfig.plugins) {
                this.webpackConfig.plugins = [];
            }

            this.webpackConfig.plugins.push(
                new WebpackCopyAfterBuildPlugin({
                    files: [{
                        chunkName: pluginName,
                        to: `${pluginPath}/${pluginName}.js`
                    }],
                    options: {
                        absolutePath: true,
                        sourceMap: true,
                        transformer: (path) => {
                            return path.replace('static/', '');
                        }
                    }
                })
            );

            // If the plugin has additional assets - copy them as well
            assetPaths.forEach((assetPath) => {
                if (!fs.existsSync(assetPath)) {
                    return;
                }

                this.webpackConfig.plugins.push(
                    // copy custom static assets
                    new CopyWebpackPlugin([
                        {
                            from: assetPath,
                            to: publicStaticPath,
                            ignore: ['.*']
                        }
                    ])
                );
            });
        });

        return this.webpackConfig;
    }

    get section() {
        return this._section;
    }

    set section(value) {
        this._section = value;
    }

    get silent() {
        return this._silent;
    }

    set silent(value) {
        this._silent = value;
    }

    get filePath() {
        return this._filePath;
    }

    set filePath(value) {
        this._filePath = value;
    }

    get webpackConfig() {
        return this._webpackConfig;
    }

    set webpackConfig(value) {
        this._webpackConfig = value;
    }

    get env() {
        return this._env;
    }

    set env(value) {
        this._env = value;
    }

    get includePaths() {
        return this._includePaths;
    }

    set includePaths(value) {
        this._includePaths = value;
    }

    get plugins(){
        return this._plugins;
    }
}

module.exports = WebpackPluginInjector;
