import deepmerge from 'deepmerge';
import PluginRegistry from 'src/script/helper/plugin/plugin.registry';
import DomAccess from 'src/script/helper/dom-access.helper';

/**
 * this file handles the plugin functionality of shopware
 *
 * to use the PluginManager import:
 * ```
 *     import PluginManager from 'src/script/helper/plugin/plugin.manager.js';
 *
 *     PluginManager.register(.....);
 *
 *     PluginManager.executePlugins(.....);
 * ```
 *
 * to extend from the base plugin import:
 * ```
 *     import Plugin from 'src/script/helper/plugin/plugin.class.js';
 *
 *     export default MyFancyPlugin extends Plugin {}
 * ```
 *
 * methods:
 *
 * // Registers a plugin to the plugin mananger.
 * PluginManager.register(pluginName: String, pluginClass: Plugin, selector: String | NodeList | HTMLElement, options?: Object): *;
 *
 * // Removes a plugin from the plugin manager.
 * PluginManager.deregister(pluginName: String): *;
 *
 * // Extends an already existing plugin with a new class or function.
 * // If both names are equal, the plugin will be overridden.
 * PluginManager.extend(fromName: String, newName: String, pluginClass: Plugin, selector: String | NodeList | HTMLElement, options?: Object): boolean;
 *
 * // Returns a list of all registered plugins.
 * PluginManager.getPluginList(): *;
 *
 * // Returns the definition of a plugin.
 * PluginManager.getPlugin(pluginName: String): Map : null;
 *
 * // Returns all registered plugin instances for the passed plugin name.
 * PluginManager.getPluginInstances(pluginName: String): Map : null;
 *
 * // Returns the plugin instance from the passed element selected by plugin mame.
 * PluginManager.getPluginInstanceFromElement(el: HTMLElement, pluginName: String): Object | null;
 *
 * // Returns all plugin instances from the passed element.
 * PluginManager.getPluginInstancesFromElement(el: HTMLElement): Map : null;
 *
 * // Starts all plugins which are currently registered.
 * PluginManager.executePlugins(): *;
 *
 * // Starts a single plugin.
 * PluginManager.executePlugin(pluginName: String|boolean, selector: String | NodeList | HTMLElement, options?: Object): *;
 *
 */
class PluginManagerSingleton {

    constructor() {
        this._registry = new PluginRegistry();
    }

    /**
     * Registers a plugin to the plugin mananger.
     *
     * @param {string} pluginName
     * @param {Plugin} pluginClass
     * @param {string|NodeList|HTMLElement} selector
     * @param {Object} options
     *
     * @returns {*}
     */
    register(pluginName, pluginClass, selector = document, options = {}) {
        if (this._registry.has(pluginName, selector)) {
            throw new Error(`Plugin "${pluginName}" is already registered.`);
        }

        return this._registry.set(pluginName, pluginClass, selector, options);
    }

    /**
     * Removes a plugin from the plugin manager.
     *
     * @param {string} pluginName
     * @param {string} selector
     *
     * @returns {*}
     */
    deregister(pluginName, selector = document) {
        if (!this._registry.has(pluginName, selector)) {
            throw new Error(`The plugin "${pluginName}" is not registered.`);
        }

        return this._registry.delete(pluginName, selector);
    }

    /**
     * Extends an already existing plugin with a new class or function.
     * If both names are equal, the plugin will be overridden.
     *
     * @param {string} fromName
     * @param {string} newName
     * @param {Plugin} pluginClass
     * @param {string|NodeList|HTMLElement} selector
     * @param {Object} options
     *
     * @returns {boolean}
     */
    extend(fromName, newName, pluginClass, selector = document, options = {}) {
        // Register the plugin under a new name
        // If the name is the same, replace it
        if (fromName === newName) {
            this.deregister(fromName, selector);
            return this.register(newName, pluginClass, selector, options);
        }

        return this._extendPlugin(fromName, newName, pluginClass, selector, options);
    }

    /**
     * Returns a list of all registered plugins.
     *
     * @returns {*}
     */
    getPluginList() {
        return this._registry.keys();
    }

    /**
     * Returns the definition of a plugin.
     *
     * @param {string} pluginName
     *
     * @returns {Map|null}
     */
    getPlugin(pluginName) {
        if (!pluginName) {
            throw new Error('A plugin name must be passed!');
        }

        if (!this._registry.has(pluginName)) {
            throw new Error(`The plugin "${pluginName}" is not registered. You might need to register it first`);
        }

        return this._registry.get(pluginName);
    }

    /**
     * Returns all registered plugin instances for the passed plugin name.
     *
     * @param {string} pluginName
     * @returns {Map|null}
     */
    getPluginInstances(pluginName) {
        const plugin = this.getPlugin(pluginName);

        return plugin.get('instances');
    }

    /**
     * Returns the plugin instance from the passed element selected by plugin Name.
     *
     * @param {HTMLElement} el
     * @param {String} pluginName
     *
     * @returns {Object|null}
     */
    static getPluginInstanceFromElement(el, pluginName) {
        const instances = PluginManagerSingleton.getPluginInstancesFromElement(el);

        return instances.get(pluginName);
    }

    /**
     * Returns all plugin instances from the passed element.
     *
     * @param {HTMLElement} el
     *
     * @returns {Map|null}
     */
    static getPluginInstancesFromElement(el) {
        if (!DomAccess.isNode(el)) {
            throw new Error('Passed element is not an Html element!')
        }

        el.__plugins = el.__plugins || new Map();

        return el.__plugins;
    }

    /**
     * Starts all plugins which are currently registered.
     */
    executePlugins() {
        const plugins = Object.keys(this.getPluginList());
        plugins.forEach((pluginName) => {
            if (pluginName) {
                if (!this._registry.has(pluginName)) {
                    throw new Error(`The plugin "${pluginName}" is not registered.`);
                }

                const plugin = this._registry.get(pluginName);
                if (plugin.has('registrations')) {
                    plugin.get('registrations').forEach(entry => {
                        this._executePlugin(plugin.get('class'), entry.selector, entry.options, plugin.get('name'));
                    });
                }
            }
        });
    }

    /**
     * Starts a single plugin.
     *
     * @param {Object} pluginName
     * @param {String|NodeList|HTMLElement} selector
     * @param {Object} options
     */
    executePlugin(pluginName, selector, options) {
        let plugin;
        let pluginClass;
        let mergedOptions;

        if (this._registry.has(pluginName, selector)) {
            plugin = this._registry.get(pluginName, selector);
            const registrationOptions = plugin.get('registrations').get(selector);
            pluginClass = plugin.get('class');
            mergedOptions = deepmerge(pluginClass.options || {}, deepmerge(registrationOptions.options || {}, options || {}));
        } else {
            plugin = this._registry.get(pluginName);
            pluginClass = plugin.get('class');
            mergedOptions = deepmerge(pluginClass.options || {}, options || {});
        }

        this._executePlugin(pluginClass, selector, mergedOptions, plugin.get('name'));
    }

    /**
     * Executes a vanilla plugin class.
     *
     * @param {Plugin} pluginClass
     * @param {String|NodeList|HTMLElement} selector
     * @param {Object} options
     * @param {string} pluginName
     */
    _executePlugin(pluginClass, selector, options, pluginName = false) {
        if (DomAccess.isNode(selector)) {
            return PluginManagerSingleton._executePluginOnElement(selector, pluginClass, options, pluginName);
        }

        if (typeof selector === 'string') {
            selector = document.querySelectorAll(selector);
        }

        return selector.forEach((el) => {
            PluginManagerSingleton._executePluginOnElement(el, pluginClass, options, pluginName);
        });
    }

    /**
     * Executes a vanilla plugin class on the passed element.
     *
     * @param {String|NodeList|HTMLElement} el
     * @param {Plugin} pluginClass
     * @param {Object} options
     * @param {string} pluginName
     * @private
     */
    static _executePluginOnElement(el, pluginClass, options, pluginName) {
        if (typeof pluginClass !== 'function') {
            throw new Error('The passed plugin is not a function or a class.');
        }

        new pluginClass(el, options, pluginName);
    }

    /**
     * extends a plugin class with another class or function.
     *
     * @param {string} fromName
     * @param {string} newName
     * @param {Plugin} pluginClass
     * @param {string|NodeList|HTMLElement} selector
     * @param {Object} options
     *
     * @returns {*}
     * @private
     */
    _extendPlugin(fromName, newName, pluginClass, selector, options = {}) {
        if (!this._registry.has(fromName, selector)) {
            throw new Error(`The plugin "${fromName}" is not registered.`);
        }

        // get current plugin
        const extendFrom = this._registry.get(fromName);
        const parentPlugin = extendFrom.get('class');
        const mergedOptions = deepmerge(parentPlugin.options || {}, options || {});

        // Create plugin
        class InternallyExtendedPlugin extends parentPlugin {
        }

        // Extend the plugin with the new definitions
        InternallyExtendedPlugin.prototype = Object.assign(InternallyExtendedPlugin.prototype, pluginClass);
        InternallyExtendedPlugin.prototype.constructor = InternallyExtendedPlugin;

        return this.register(newName, InternallyExtendedPlugin, selector, mergedOptions);
    }

}

/**
 * Make the PluginManager being a Singleton
 * @type {PluginManagerSingleton}
 */
const PluginManagerInstance = new PluginManagerSingleton();
Object.freeze(PluginManagerInstance);

export default class PluginManager {

    constructor() {
        window.PluginManager = this;
    }

    /**
     * Registers a plugin to the plugin mananger.
     *
     * @param {string} pluginName
     * @param {Plugin} pluginClass
     * @param {string|NodeList|HTMLElement} selector
     * @param {Object} options
     *
     * @returns {*}
     */
    static register(pluginName, pluginClass, selector = document, options = {}) {
        return PluginManagerInstance.register(pluginName, pluginClass, selector, options);
    }

    /**
     * Removes a plugin from the plugin manager.
     *
     * @param {string} pluginName
     * @param {string} selector
     *
     * @returns {*}
     */
    static deregister(pluginName, selector) {
        return PluginManagerInstance.deregister(pluginName, selector);
    }

    /**
     * Extends an already existing plugin with a new class or function.
     * If both names are equal, the plugin will be overridden.
     *
     * @param {string} fromName
     * @param {string} newName
     * @param {Plugin} pluginClass
     * @param {string|NodeList|HTMLElement} selector
     * @param {Object} options
     *
     * @returns {boolean}
     */
    static extend(fromName, newName, pluginClass, selector, options = {}) {
        return PluginManagerInstance.extend(fromName, newName, pluginClass, selector, options);
    }

    /**
     * Returns a list of all registered plugins.
     *
     * @returns {*}
     */
    static getPluginList() {
        return PluginManagerInstance.getPluginList();
    }

    /**
     * Returns the definition of a plugin.
     *
     * @returns {*}
     */
    static getPlugin(pluginName) {
        return PluginManagerInstance.getPlugin(pluginName);
    }

    /**
     * Returns all registered plugin instances for the passed plugin name..
     *
     * @param {string} pluginName
     *
     * @returns {Map|null}
     */
    static getPluginInstances(pluginName) {
        return PluginManagerInstance.getPluginInstances(pluginName);
    }

    /**
     * Returns the plugin instance from the passed element selected by plugin Name.
     *
     * @param {HTMLElement} el
     * @param {String} pluginName
     *
     * @returns {Object|null}
     */
    static getPluginInstanceFromElement(el, pluginName) {
        return PluginManagerSingleton.getPluginInstanceFromElement(el, pluginName);
    }

    /**
     * Returns all plugin instances from the passed element.
     *
     * @param {HTMLElement} el
     *
     * @returns {Map|null}
     */
    static getPluginInstancesFromElement(el) {
        return PluginManagerSingleton.getPluginInstancesFromElement(el);
    }

    /**
     * Starts all plugins which are currently registered.
     */
    static executePlugins() {
        PluginManagerInstance.executePlugins();
    }

    /**
     * Starts a single plugin.
     *
     * @param {Object} pluginName
     * @param {String|NodeList|HTMLElement} selector
     * @param {Object} options
     */
    static executePlugin(pluginName, selector, options) {
        PluginManagerInstance.executePlugin(pluginName, selector, options);
    }
}

window.PluginManager = PluginManager;
