import deepmerge from 'deepmerge';
import DomAccess from 'src/script/helper/dom-access.helper';
import StringHelper from 'src/script/helper/string.helper';

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
        this._pluginName = this._getPluginName(pluginName);
        this.options = this._mergeOptions(options);

        this._registerInstance();
        this.init();
    }

    /**
     * this function gets executed when the plugin is initialized
     *
     * @private
     */
    init() {
        throw new Error(`The "init" method for the plugin "${this._pluginName}" is not defined.`);
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
        const dataAttributeOptions = DomAccess.getDataAttribute(this.el, `data-${dashedPluginName}-options`, false);

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
        if (dataAttributeOptions) merge.push(dataAttributeOptions);

        return deepmerge.all(merge.map(config => config || {}));
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
