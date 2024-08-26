import PluginConfigRegistry from 'src/plugin-system/plugin.config.registry';

/**
 * @package storefront
 */
class PluginConfigManagerSingleton {

    constructor() {
        this._registry = new PluginConfigRegistry();
    }

    /**
     * returns the plugin config registry
     * or a direct config if a name is given
     *
     * @param {string} pluginName
     * @param {*|boolean} configName
     *
     * @returns {any}
     */
    get(pluginName, configName = false) {
        return this._registry.get(pluginName, configName);
    }

    /**
     * returns the plugin config registry
     * or a direct config if a name is given
     *
     * @param {string} pluginName
     * @param {*|boolean} configName
     * @param {*} config
     *
     * @returns {any}
     */
    add(pluginName, configName, config) {
        return this._registry.set(pluginName, configName, config);
    }

    /**
     * removes a config from the registry
     *
     * @param {string} pluginName
     * @param {*|boolean} configName
     *
     * @returns {any}
     */
    remove(pluginName, configName) {
        return this._registry.delete(pluginName, configName);
    }

    /**
     * returns the plugin registry
     *
     * @returns {Map<any, any>}
     */
    getRegistry() {
        return this._registry;
    }

}

/**
 * Create the PluginConfigManager instance.
 * @type {Readonly<PluginConfigManagerSingleton>}
 */
export const PluginConfigManagerInstance = Object.freeze(new PluginConfigManagerSingleton());

class PluginConfigManager {

    /**
     * returns the plugin config registry
     * or a direct config if a name is given
     *
     * @param {string} pluginName
     * @param {*|boolean} configName
     *
     * @returns {any}
     */
    static get(pluginName, configName = false) {
        return PluginConfigManagerInstance.get(pluginName, configName);
    }

    /**
     * returns the plugin config registry
     * or a direct config if a name is given
     *
     * @param {string} pluginName
     * @param {*|boolean} configName
     * @param {*} config
     *
     * @returns {any}
     */
    static add(pluginName, configName, config) {
        return PluginConfigManagerInstance.add(pluginName, configName, config);
    }

    /**
     * removes a config from the registry
     *
     * @param {string} pluginName
     * @param {*|boolean} configName
     *
     * @returns {any}
     */
    static remove(pluginName, configName) {
        return PluginConfigManagerInstance.remove(pluginName, configName);
    }

    /**
     * returns the plugin registry
     *
     * @returns {Map<any, any>}
     */
    static getRegistry() {
        return PluginConfigManagerInstance.getRegistry();
    }

}

window.PluginConfigManager = PluginConfigManager;

