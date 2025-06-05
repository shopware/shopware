import deepmerge from 'deepmerge';
import StringHelper from 'src/helper/string.helper';
import NativeEventEmitter from 'src/helper/emitter.helper';

/**
 * Plugin Base class
 * @sw-package framework
 */
export default class Plugin {
    /**
     * plugin constructor
     *
     * @param {HTMLElement} el
     * @param {Object} options
     * @param {string} pluginName
     */
    constructor(el, options = {}, pluginName = false) {
        if (!(el instanceof Node)) {
            console.warn(`There is no valid element given while trying to create a plugin instance for "${pluginName}".`);
            return;
        }

        this.el = el;
        this.$emitter = new NativeEventEmitter(this.el);
        this._pluginName = this._getPluginName(pluginName);
        this.options = this._mergeOptions(options);
        this._initialized = false;

        this._registerInstance();
        this._init();
    }

    /**
     * this function gets executed when the plugin is initialized
     */
    init() {
        console.warn(`The "init" method for the plugin "${this._pluginName}" is not defined. The plugin will not be initialized.`);
    }

    /**
     * this function gets executed when the plugin is being updated
     */
    update() {

    }

    /**
     * internal init method which checks
     * if the plugin is already initialized
     * before executing the public init
     *
     * @private
     */
    _init() {
        if (this._initialized) return;

        this.init();
        this._initialized = true;
    }

    /**
     * internal update method which checks
     * if the plugin is already initialized
     * before executing the public update
     *
     * @private
     */
    _update() {
        if (!this._initialized) return;

        this.update();
    }

    /**
     * Deep merge the passed options and the static defaults.
     *
     * @param {Object} options
     *
     * @private
     */
    _mergeOptions(options) {
        // static plugin options
        // previously merged options
        // explicit options when creating a plugin instance with 'new'
        const merge = [
            this.constructor.options,
            this.options,
            options,
        ];

        merge.push(this._getConfigFromDataAttribute());
        merge.push(this._getOptionsFromDataAttribute());

        return deepmerge.all(
            merge.filter(config => {
                return config instanceof Object && !(config instanceof Array);
            }).map(config => config || {})
        );
    }

    /**
     * Returns the config from the data attribute.
     *
     * @returns {Object}
     * @private
     */
    _getConfigFromDataAttribute() {
        const attributeConfig = {};

        if (typeof this.el.getAttribute !== 'function') {
            return attributeConfig;
        }

        const dashedPluginName = StringHelper.toDashCase(this._pluginName);
        const dataAttributeConfig = this.el.getAttribute(`data-${dashedPluginName}-config`);

        if (dataAttributeConfig) {
            return window.PluginConfigManager.get(this._pluginName, dataAttributeConfig);
        }

        return attributeConfig;
    }

    /**
     * Returns the options from the data attribute.
     *
     * @returns {Object}
     * @private
     */
    _getOptionsFromDataAttribute() {
        const attributeOptions = {};

        if (typeof this.el.getAttribute !== 'function') {
            return attributeOptions;
        }

        const dashedPluginName = StringHelper.toDashCase(this._pluginName);
        const dataAttributeOptions = this.el.getAttribute(`data-${dashedPluginName}-options`);

        if (dataAttributeOptions) {
            try {
                return JSON.parse(dataAttributeOptions);
            } catch (e) {
                console.error(`The data attribute "data-${dashedPluginName}-options" could not be parsed to json: ${e.message}`);
            }
        }

        return attributeOptions;
    }

    /**
     * registers the plugin Instance to the element
     *
     * @private
     */
    _registerInstance() {
        const elementPluginInstances = window.PluginManager.getPluginInstancesFromElement(this.el);
        elementPluginInstances.set(this._pluginName, this);

        const plugin = window.PluginManager.getPlugin(this._pluginName, false);
        plugin.get('instances').push(this);
    }

    /**
     * returns the plugin name
     *
     * @param {string} pluginName
     *
     * @returns {string}
     * @private
     */
    _getPluginName(pluginName) {
        if (!pluginName) pluginName = this.constructor.name;

        return pluginName;
    }

}
