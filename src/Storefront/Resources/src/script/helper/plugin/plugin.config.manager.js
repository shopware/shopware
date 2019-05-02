import PluginConfigRegistry from 'src/script/helper/plugin/plugin.config.registry';


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
        return this._registry
    }

}


const PluginConfigManagerSingletonInstance = new PluginConfigManagerSingleton();
Object.freeze(PluginConfigManagerSingletonInstance);

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
        return PluginConfigManagerSingletonInstance.get(pluginName, configName);
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
        return PluginConfigManagerSingletonInstance.add(pluginName, configName, config);
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
        return PluginConfigManagerSingletonInstance.remove(pluginName, configName);
    }

    /**
     * returns the plugin registry
     *
     * @returns {Map<any, any>}
     */
    static getRegistry() {
        return PluginConfigManagerSingletonInstance.getRegistry();
    }

}

window.PluginConfigManager = PluginConfigManager;

