/**
 * Plugin Registry
 *
 * contains all definitions for all plugins
 *
 * @package storefront
 */
export default class PluginConfigRegistry {

    constructor() {
        this._registry = new Map();
    }

    /**
     * adds a plugin to the registry
     *
     * @param {string} pluginName
     * @param {string} configName
     * @param {Object} config
     *
     * @returns {Map<any, any>}
     */
    set(pluginName, configName, config) {
        const pluginConfigs = this._createPluginConfigRegistry(pluginName);
        return pluginConfigs.set(configName, config);
    }

    /**
     * returns a config from the registry
     *
     * @param {string} pluginName
     * @param {string} configName
     *
     * @returns {any}
     */
    get(pluginName, configName = false) {
        const pluginConfigs = this._createPluginConfigRegistry(pluginName);
        if (configName && pluginConfigs.has(configName)) {
            return pluginConfigs.get(configName);
        } else if (configName) {
            throw new Error(`The config "${configName}" is not registered for the plugin "${pluginName}"!`);
        }

        return pluginConfigs;
    }

    /**
     * removes a config from the registry
     *
     * @param {string} pluginName
     * @param {string} configName
     *
     * @returns {PluginConfigRegistry}
     */
    delete(pluginName, configName) {
        const pluginConfigs = this._createPluginConfigRegistry(pluginName);
        pluginConfigs.delete(configName);

        return this;
    }

    /**
     * clears the registry
     *
     * @returns {PluginConfigRegistry}
     */
    clear() {
        this._registry.clear();

        return this;
    }

    /**
     * creates the map for a plugin if not already existing
     * and returns it
     *
     * @param {string} pluginName
     *
     * @returns {Map<any, any>}
     * @private
     */
    _createPluginConfigRegistry(pluginName) {
        if (!pluginName) {
            throw new Error('A plugin name must be given!');
        }
        if (!this._registry.has(pluginName)) {
            this._registry.set(pluginName, new Map());
        }

        return this._registry.get(pluginName);
    }

}

