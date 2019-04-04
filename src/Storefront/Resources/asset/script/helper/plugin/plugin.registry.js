/**
 * Plugin Registry
 *
 * contains all definitions for all plugins
 */
export default class PluginRegistry {

    constructor() {
        this._registry = new Map();
    }

    /**
     * returns if the plugin is set to the registry
     *
     * @param {string} name
     * @returns {boolean}
     */
    has(name) {
        return this._registry.has(name);
    }

    /**
     * adds a plugin to the registry
     *
     * @param {string} name
     * @param {Object} plugin
     * @param {string|NodeList|HTMLElement} selector
     * @param {Object} options
     * @returns {Map<any, any>}
     */
    set(name, plugin, selector, options) {
        return this._registry.set(name, { name, plugin, selector, options });
    }

    /**
     * returns a plugin from the registry
     *
     * @param {string} name
     * @returns {any}
     */
    get(name) {
        return this._registry.get(name);
    }

    /**
     * removes a plugin from the registry
     *
     * @param {string} name
     *
     * @returns {boolean}
     */
    delete(name) {
        return this._registry.delete(name);
    }

    /**
     * clears the registry
     *
     * @returns {boolean}
     */
    clear() {
        return this._registry.clear();
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

