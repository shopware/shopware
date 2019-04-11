import deepmerge from 'deepmerge';
import DomAccess from 'asset/script/helper/dom-access.helper';

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
        this.options = this._mergeOptions(options);

        this._pluginName = pluginName;

        this._registerInstance();
        this.init();
    }

    /**
     * this function gets executed when the plugin is initialized
     *
     * @private
     */
    init() {
        throw new Error(`The "init" method for the plugin "${this.constructor.name}" is not defined.`);
    }

    /**
     * deep merge the passed options and the static defaults
     *
     * @param {Object} options
     *
     * @private
     */
    _mergeOptions(options) {
        let defaultOptions = this.options || {};

        if (this.constructor.options) {
            defaultOptions = this.constructor.options;
        }

        return deepmerge(defaultOptions, options || {});
    }

    /**
     * registers the plugin Instance to the element
     *
     * @private
     */
    _registerInstance() {
        const pluginName = this._getPluginName();

        const elementPluginInstances = window.PluginManager.getPluginInstancesFromElement(this.el);
        elementPluginInstances.set(pluginName, this);

        const plugin = window.PluginManager.getPlugin(pluginName);
        plugin.get('instances').push(this);
    }

    /**
     * returns the plugin name
     *
     * @returns {string}
     * @private
     */
    _getPluginName() {
        let pluginName = this._pluginName;
        if (!pluginName) pluginName = this.constructor.name;

        return pluginName;
    }

}
