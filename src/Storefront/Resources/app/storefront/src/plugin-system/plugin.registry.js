/**
 * Plugin Registry
 *
 * contains all definitions for all plugins
 * @package storefront
 */
export default class PluginRegistry {

    constructor() {
        this._registry = new Map();
    }

    /**
     * returns if the plugin is set to the registry
     *
     * @param {string} name
     * @param {string} selector
     *
     * @returns {boolean}
     */
    has(name, selector) {
        if (!selector) {
            return this._registry.has(name);
        }

        if (!this._registry.has(name)) {
            this._registry.set(name, new Map());
        }

        const pluginMap = this._registry.get(name);
        if (!pluginMap.has('registrations')) return false;

        return pluginMap.get('registrations').has(selector);
    }

    /**
     * adds a plugin to the registry
     *
     * @param {string} name
     * @param {Object} plugin
     * @param {string|NodeList|HTMLElement} selector
     * @param {Object} options
     *
     * @returns {Map<any, any>}
     */
    set(name, plugin, selector, options) {
        if (!this.has(name)) this._registry.set(name, new Map());
        const pluginMap = this._registry.get(name);
        pluginMap.set('class', plugin);
        pluginMap.set('name', name);

        if (!pluginMap.has('registrations')) pluginMap.set('registrations', new Map());
        if (!pluginMap.has('instances')) pluginMap.set('instances', []);
        const registrationMap = pluginMap.get('registrations');
        if (selector) {
            registrationMap.set(selector, { selector, options });
        }

        return this;
    }

    /**
     * returns a plugin from the registry
     *
     * @param {string} name
     *
     * @returns {any}
     */
    get(name) {
        return this._registry.get(name);
    }

    /**
     * removes a plugin from the registry
     *
     * @param {string} name
     * @param {string} selector
     *
     * @returns {PluginRegistry}
     */
    delete(name, selector) {
        if (!selector) {
            return this._registry.delete(name);
        }

        const pluginMap = this._registry.get(name);
        if (!pluginMap) return true;

        const registrationMap = pluginMap.get('registrations');
        if (!registrationMap) return true;

        registrationMap.delete(selector);

        return this;
    }

    /**
     * clears the registry
     *
     * @returns {PluginRegistry}
     */
    clear() {
        this._registry.clear();

        return this;
    }

    /**
     * returns all defined plugin names from the registry
     *
     * @returns {[any , any]}
     */
    keys() {
        return Array.from(this._registry).reduce((accumulator, values) => {
            const [key, value] = values;
            accumulator[key] = value;
            return accumulator;
        }, {});
    }

}

