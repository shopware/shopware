export default {
    register,
    override,
    get,
    has,
    destroy,
    run,
    getRegistry,
    extend
};

/** @type Map Registry for plugins */
const registry = new Map();

/**
 * Returns an object with all registered plugins.
 *
 * @returns {Object}
 */
function getRegistry() {
    return Array.from(registry).reduce((accumulator, values) => {
        const [key, value] = values;
        accumulator[key] = value;
        return accumulator;
    }, {});
}

/**
 * Destroys the registry and resets it completely.
 *
 * @returns {boolean}
 */
function destroy() {
    registry.clear();
    return true;
}

/**
 * Registers a plugin to the registry
 *
 * @throws {Error} Throws an error when a plugin with the same name was registered already.
 * @param {String} name
 * @param {Object} definition
 * @returns {Object}
 */
function register(name, definition) {
    if (has(name)) {
        throw new Error(`Plugin "${name}" is already registered.`);
    }

    if (!validOptions(definition)) {
        throw new Error('The plugin definition is not valid.');
    }

    registry.set(name, definition);

    return definition;
}

/**
 * Overrides a plugin in the registry.
 *
 * @throws {Error} Throws an error when the plugin wasn't registered.
 * @param {String} name
 * @param {Object} definition
 * @returns {Object}
 */
function override(name, definition) {
    if (!has(name)) {
        throw new Error(`No plugin "${name}" is not registered.`);
    }

    if (!validOptions(definition)) {
        throw new Error('The plugin definition is not valid.');
    }

    registry.set(name, definition);

    return definition;
}

/**
 * Returns a plugin from the registry.
 *
 * @param {String} name
 * @returns {Object|null}
 */
function get(name) {
    return registry.get(name);
}

/**
 * Checks if a plugin is registered in the registry.
 *
 * @param {String} name
 * @returns {Boolean}
 */
function has(name) {
    return registry.has(name);
}

/**
 * Transforms the registered vanilla plugins as jQuery plugins.
 *
 * @param {jQuery} jQueryInstance
 * @returns {Array[]}
 */
function run(jQueryInstance) {
    const plugins = getRegistry();
    Object.keys(plugins).forEach((name) => {
        const definition = plugins[name];

        if (!jQueryInstance) {
            document.querySelectorAll(definition.selector).forEach((el) => {
                const Plugin = definition.plugin;
                new Plugin(el); // eslint-disable-line no-new
            });

            return;
        }
        transformVanillaPluginToJQueryPlugin(jQueryInstance, name, definition);
    });

    return plugins;
}

/**
 * Transforms vanilla plugins to jQuery plugins. The method generates a jQuery interface for each plugin.
 *
 * @param {jQuery} jQueryInstance
 * @param {String} name
 * @param {Object} definition
 * @returns {Function}
 */
function transformVanillaPluginToJQueryPlugin(jQueryInstance, name, definition) {
    /**
     * Private method which provides the jQuery interface for vanilla plugins.
     *
     * @param {Object} config
     * @returns {jQuery}
     */
    function jQueryInterface(config) {
        return this.each(() => {
            const dataKey = `plugin_${name}`;
            const data = jQueryInstance.data(this, dataKey);

            // Prevent multiple instantiation
            if (data) {
                return;
            }
            const plugin = new Plugin(this.get(0), config); // eslint-disable-line no-use-before-define
            jQueryInstance.data(this, dataKey, plugin);
        });
    }

    const JQUERY_NO_CONFLICT = jQueryInstance.fn[name];
    const Plugin = definition.plugin;
    jQueryInstance.fn[name] = jQueryInterface;

    jQueryInstance.fn[name].Constructor = Plugin;
    jQueryInstance.fn[name].noConflict = () => {
        jQueryInstance.fn[name] = JQUERY_NO_CONFLICT;
        return jQueryInterface;
    };

    return jQueryInstance.fn[name];
}

/**
 * Checks if a plugin definition is valid.
 *
 * @param {Object} definition
 * @returns {Boolean}
 */
function validOptions(definition) {
    return Object.prototype.hasOwnProperty.call(definition, 'selector')
        && Object.prototype.hasOwnProperty.call(definition, 'plugin');
}

/**
 * Provides an easy-to-use way to extend existing plugins for users which aren't using the webpack stack.
 *
 * @param {String} newName
 * @param {String} extendFromName
 * @param {Object} definition
 * @returns {Boolean}
 */
function extend(newName, extendFromName, definition) {
    if (!has(extendFromName)) {
        throw new Error(`No plugin "${extendFromName}" found in registry.`);
    }

    // Create plugin
    const extendFromPlugin = registry.get(extendFromName).plugin;
    class Plugin extends extendFromPlugin {
        constructor(el, config) {
            super(el, config, newName);
        }
    }

    // Extend the plugin with the new definitions
    Plugin.prototype = Object.assign(Plugin.prototype, definition.plugin);
    Plugin.prototype.constructor = Plugin;

    // Register the plugin under a new name
    register(newName, {
        selector: definition.selector,
        plugin: Plugin
    });

    return true;
}
