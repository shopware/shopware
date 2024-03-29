import deepmerge from 'deepmerge';
import PluginRegistry from 'src/plugin-system/plugin.registry';
import PluginBaseClass from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import 'src/plugin-system/plugin.config.manager';
import Iterator from 'src/helper/iterator.helper';

/**
 * this file handles the plugin functionality of shopware
 *
 * to use the PluginManager:
 * ```
  *    window.PluginManager.register(.....);
 *
 *     window.PluginManager.initializePlugins(.....);
 * ```
 *
 * to extend from the base plugin import:
 * ```
 *     import Plugin from 'src/helper/plugin/plugin.class';
 *
 *     export default MyFancyPlugin extends Plugin {}
 * ```
 *
 * methods:
 *
 * // Registers a plugin to the plugin manager.
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
 * // Initializes all plugins which are currently registered.
 * PluginManager.initializePlugins(): *;
 *
 * // Initializes a single plugin.
 * PluginManager.initializePlugin(pluginName: String|boolean, selector: String | NodeList | HTMLElement, options?: Object): *;
 *
 * @package storefront
 */
class PluginManagerSingleton {

    constructor() {
        this._registry = new PluginRegistry();
    }

    /**
     * Registers a plugin to the plugin manager.
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

        // If we cannot find the prototype of the class, we assume it will be loaded async
        if (!Object.getOwnPropertyDescriptor(pluginClass, 'prototype')) {
            return this._registry.set(pluginName, pluginClass, selector, options, true);
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
     * @param {boolean} strict
     *
     * @returns {Map|null}
     */
    getPlugin(pluginName, strict = true) {
        if (!pluginName) {
            throw new Error('A plugin name must be passed!');
        }

        if (!this._registry.has(pluginName)) {
            if (strict) {
                throw new Error(`The plugin "${pluginName}" is not registered. You might need to register it first.`);
            } else {
                this._registry.set(pluginName);
            }
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
            throw new Error('Passed element is not an Html element!');
        }

        el.__plugins = el.__plugins || new Map();

        return el.__plugins;
    }

    /**
     * Initializes all plugins which are currently registered.
     *
     * @return {Promise<void>}
     */
    async initializePlugins() {
        const initializationFailures = [];

        await this._fetchAsyncPlugins();

        for (const [pluginName] of Object.entries(this.getPluginList())) {
            if (pluginName) {
                if (!this._registry.has(pluginName)) {
                    throw new Error(`The plugin "${pluginName}" is not registered.`);
                }

                const plugin = this._registry.get(pluginName);

                if (plugin.has('registrations')) {
                    for (const [, entry] of plugin.get('registrations')) {
                        try {
                            this._initializePlugin(plugin.get('class'), entry.selector, entry.options, plugin.get('name'));
                        } catch (failure) {
                            initializationFailures.push(failure);
                        }
                    }
                }
            }
        }

        initializationFailures.forEach((failure) => {
            console.error(failure);
        });

        return Promise.resolve();
    }

    /**
     * Fetches all async plugins and sets their class to the PluginRegistry.
     *
     * @return {Promise<void>}
     * @private
     */
    async _fetchAsyncPlugins() {
        // Collect all async plugins that need to be fetched via async import and Promise.all()
        // [
        //   { pluginName: 'Example', pluginClassPromise: () => import('./example.plugin') },
        //   { pluginName: 'FooBar', pluginClassPromise: () => import('./foo-bar.plugin') },
        // ]
        const queue = [];

        // Will contain the fetched plugin classes
        // [
        //   Module: { __esModule: true, default: class Example ... },
        //   Module: { __esModule: true, default: class FooBar ... },
        // ]
        let fetchedPluginClasses = [];

        for (const [pluginName] of Object.entries(this.getPluginList())) {
            if (!pluginName) {
                continue;
            }

            if (!this._registry.has(pluginName)) {
                throw new Error(`The plugin "${pluginName}" is not registered.`);
            }

            const plugin = this._registry.get(pluginName);

            if (!plugin.has('registrations')) {
                continue;
            }

            for (const [, entry] of plugin.get('registrations')) {
                if (!plugin.get('async')) {
                    continue;
                }

                let selector = entry.selector;

                if (DomAccess.isNode(selector)) {
                    queue.push({ pluginName: pluginName, pluginClassPromise: plugin.get('class') });
                    continue;
                }

                if (typeof selector === 'string') {
                    selector = PluginManagerSingleton._queryElements(selector);
                }

                if (selector.length > 0) {
                    queue.push({ pluginName: pluginName, pluginClassPromise: plugin.get('class') });
                }
            }
        }

        if (!queue.length) {
            return;
        }

        // Fetch all needed plugins
        try {
            fetchedPluginClasses = await Promise.all(queue.map((queueItem) => {
                return queueItem.pluginClassPromise();
            }));
        } catch (error) {
            console.error('An error occurred while fetching async JS-plugins', error);
        }

        // Set the fetched plugin classes to the registry, so they can be initialized later.
        queue.forEach((plugin, index) => {
            const pluginClass = fetchedPluginClasses[index].default;
            const pluginName = plugin.pluginName;
            const pluginFromRegistry = this._registry.get(pluginName);

            pluginFromRegistry.set('async', false);
            pluginFromRegistry.set('class', pluginClass);
        });
    }

    /**
     * @param {Object} pluginFromRegistry
     * @param {String|NodeList|HTMLElement} selector
     * @return {Promise<void>}
     * @private
     */
    async _fetchAsyncPlugin(pluginFromRegistry, selector) {
        if (!pluginFromRegistry.get('async')) {
            return;
        }

        let needsFetch = false;
        if (DomAccess.isNode(selector)) {
            needsFetch = true;
        }

        if (typeof selector === 'string') {
            selector = PluginManagerSingleton._queryElements(selector);
            needsFetch = !!selector.length;
        }

        if (!needsFetch) {
            return;
        }

        const pluginClassPromise = pluginFromRegistry.get('class')();
        const fetchedPlugin = await pluginClassPromise;
        const pluginClass = fetchedPlugin.default;

        pluginFromRegistry.set('async', false);
        pluginFromRegistry.set('class', pluginClass);
    }

    /**
     * Initializes a single plugin.
     *
     * @param {string} pluginName
     * @param {String|NodeList|HTMLElement} selector
     * @param {Object} options
     */
    async initializePlugin(pluginName, selector, options) {
        let plugin;
        let pluginClass;
        let mergedOptions;

        if (this._registry.has(pluginName, selector)) {
            plugin = this._registry.get(pluginName, selector);
            await this._fetchAsyncPlugin(plugin, selector);
            const registrationOptions = plugin.get('registrations').get(selector);
            pluginClass = plugin.get('class');
            mergedOptions = deepmerge(pluginClass.options || {}, deepmerge(registrationOptions.options || {}, options || {}));
        } else {
            plugin = this._registry.get(pluginName);
            await this._fetchAsyncPlugin(plugin, selector);
            pluginClass = plugin.get('class');
            mergedOptions = deepmerge(pluginClass.options || {}, options || {});
        }

        try {
            this._initializePlugin(pluginClass, selector, mergedOptions, plugin.get('name'));
        } catch (failure) {
            console.error(failure);
        }

        return Promise.resolve();
    }

    /**
     * Executes a vanilla plugin class.
     *
     * @param {Plugin} pluginClass
     * @param {String|NodeList|HTMLElement} selector
     * @param {Object} options
     * @param {string} pluginName
     */
    _initializePlugin(pluginClass, selector, options, pluginName = false) {
        if (DomAccess.isNode(selector)) {
            return PluginManagerSingleton._initializePluginOnElement(selector, pluginClass, options, pluginName);
        }

        if (typeof selector === 'string') {
            selector = PluginManagerSingleton._queryElements(selector);
        }

        return Iterator.iterate(selector, el => {
            PluginManagerSingleton._initializePluginOnElement(el, pluginClass, options, pluginName);
        });
    }

    /**
     * Determines the way to query the elements.
     *
     * [data-*] => querySelectorAll
     * #fooBar => getElementById
     * #foo_bar => getElementById
     * #foo-bar => getElementById
     * #foo .bar => querySelectorAll
     * .fooBar => getElementsByClassName
     * .FOO => getElementsByClassName
     * .foo_bar => getElementsByClassName
     * .foo-bar => getElementsByClassName
     * .foo .bar => querySelectorAll
     *
     * FOO => getElementsByTagName
     * FOO .bar => querySelectorAll
     *
     * For performance reason used regex based on common characters `a-zA-Z1-9_-`
     * instead of the entire compatible characters
     *
     * @param {string} selector
     *
     * @return {NodeList|HTMLCollection|Array}
     */
    static _queryElements(selector) {
        if (selector.startsWith('.')) {
            const regexEl = /^\.([\w-]+)$/.exec(selector);
            if (regexEl) {
                return document.getElementsByClassName(regexEl[1]);
            }
        } else if (selector.startsWith('#')) {
            const regexEl = /^#([\w-]+)$/.exec(selector);
            if (regexEl) {
                const el = document.getElementById(regexEl[1]);

                return (el) ? [el] : [];
            }
        } else if (/^([\w-]+)$/.exec(selector)) {
            return document.getElementsByTagName(selector);
        }

        return document.querySelectorAll(selector);
    }

    /**
     * Executes a vanilla plugin class on the passed element.
     *
     * @param {Node|HTMLElement} el
     * @param {Plugin} pluginClass
     * @param {Object} options
     * @param {string} pluginName
     * @private
     */
    static _initializePluginOnElement(el, pluginClass, options, pluginName) {
        if (typeof pluginClass !== 'function') {
            throw new Error('The passed plugin is not a function or a class.');
        }

        const instance = PluginManager.getPluginInstanceFromElement(el, pluginName);
        if (!instance) {
            return new pluginClass(el, options, pluginName);
        }

        return instance._update();
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
 * Create the PluginManager instance.
 * @type {Readonly<PluginManagerSingleton>}
 */
export const PluginManagerInstance = Object.freeze(new PluginManagerSingleton());

export default class PluginManager {

    constructor() {
        window.PluginManager = this;
    }

    /**
     * Registers a plugin to the plugin manager.
     *
     * @param {string} pluginName
     * @param {function(): Promise<{readonly default?: *}>} pluginClass
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

    static override(overrideName, pluginClass, selector, options = {}) {
        return PluginManagerInstance.extend(overrideName, overrideName, pluginClass, selector, options);
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
     * Initializes all plugins which are currently registered.
     *
     * @return {Promise<void>}
     */
    static initializePlugins() {
        return PluginManagerInstance.initializePlugins();
    }

    /**
     * Initializes a single plugin.
     *
     * @param {string} pluginName
     * @param {String|NodeList|HTMLElement} selector
     * @param {Object} options
     * @returns {Promise<void>}
     */
    static initializePlugin(pluginName, selector, options) {
        return PluginManagerInstance.initializePlugin(pluginName, selector, options);
    }
}

window.PluginManager = PluginManager;
window.PluginBaseClass = PluginBaseClass;
