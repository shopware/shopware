import deepmerge from 'deepmerge';
import DomAccess from 'src/helper/dom-access.helper';
import StringHelper from 'src/helper/string.helper';
import NativeEventEmitter from 'src/helper/emitter.helper';

/**
 * Plugin Base class
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
        if (!DomAccess.isNode(el)) {
            throw new Error('There is no valid element given.');
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
        throw new Error(`The "init" method for the plugin "${this._pluginName}" is not defined.`);
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
     * deep merge the passed options and the static defaults
     *
     * @param {Object} options
     *
     * @private
     */
    _mergeOptions(options) {
        const dashedPluginName = StringHelper.toDashCase(this._pluginName);
        const dataAttributeConfig = DomAccess.getDataAttribute(this.el, `data-${dashedPluginName}-config`, false);
        const dataAttributeOptions = DomAccess.getAttribute(this.el, `data-${dashedPluginName}-options`, false);


        // static plugin options
        // previously merged options
        // explicit options when creating a plugin instance with 'new'
        const merge = [
            this.constructor.options,
            this.options,
            options,
        ];

        // options which are set via data-plugin-name-config="config name"
        if (dataAttributeConfig) merge.push(window.PluginConfigManager.get(this._pluginName, dataAttributeConfig));
        // options which are set via data-plugin-name-options="{json..}"
        try {
            if (dataAttributeOptions) merge.push(JSON.parse(dataAttributeOptions));
        } catch (e) {
            console.error(this.el);
            throw new Error(
                `The data attribute "data-${dashedPluginName}-options" could not be parsed to json: ${e.message}`
            );
        }

        return deepmerge.all(
            merge.filter(config => {
                return config instanceof Object && !(config instanceof Array);
            })
                .map(config => config || {})
        );
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
