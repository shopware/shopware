import utils from 'src/core/service/util.service';
import TemplateFactory from 'src/core/factory/template.factory';

export {
    register,
    extend,
    override,
    build,
    getComponentTemplate,
    getComponentRegistry
};

/** @type Map componentRegistry - Registry which holds all component registry */
const componentRegistry = new Map();

/** @type Map overrideRegistry - Registry which holds all component overrides */
const overrideRegistry = new Map();

/**
 * Returns the map with all registered components.
 *
 * @returns {Map}
 */
function getComponentRegistry() {
    return componentRegistry;
}

/**
 * Register a new component.
 *
 * @param {String} componentName
 * @param {Object} [componentConfiguration={}]
 * @returns {*}
 */
function register(componentName, componentConfiguration = {}) {
    const config = componentConfiguration;

    config.name = componentName;

    if (componentRegistry.has(componentName)) {
        utils.warn(
            'ComponentFactory',
            `The component "${componentName}" is already registered. Please select a unique name for your component.`,
            config
        );
        return config;
    }

    config.name = componentName;

    if (config.template) {
        /**
         * Register the main template of the component.
         */
        TemplateFactory.registerComponentTemplate(componentName, config.template);

        /**
         * Delete the template string from the component config.
         * The complete rendered template including all overrides will be added later.
         */
        delete config.template;
    } else {
        utils.warn(
            'ComponentFactory',
            `The component "${config.name}" needs a template to be functional.`,
            'Please add a "template" property to your component definition',
            config
        );
        return config;
    }

    componentRegistry.set(componentName, config);

    return config;
}

/**
 * Create a new component extending from another existing component.
 *
 * @param {String} componentName
 * @param {String} extendComponentName
 * @param {Object} componentConfiguration
 * @returns {Object} config
 */
function extend(componentName, extendComponentName, componentConfiguration) {
    const config = componentConfiguration;

    if (config.template) {
        /**
         * Register the main template of the component based on the extended component.
         */
        TemplateFactory.extendComponentTemplate(name, extendComponentName, config.template);

        /**
         * Delete the template string from the component config.
         * The complete rendered template including all overrides will be added later.
         */
        delete config.template;
    } else {
        TemplateFactory.extendComponentTemplate(componentName, extendComponentName);
    }

    config.name = componentName;
    config.extends = extendComponentName;

    componentRegistry.set(componentName, config);

    return config;
}

/**
 * Override an existing component including its config and template.
 *
 * ToDo: Keep reference to original config object.
 *
 * @param componentName
 * @param componentConfiguration
 * @param overrideIndex
 * @returns {*}
 */
function override(componentName, componentConfiguration, overrideIndex = null) {
    const config = componentConfiguration;

    config.name = componentName;

    if (config.template) {
        /**
         * Register a template override for the existing component template.
         */
        TemplateFactory.registerTemplateOverride(componentName, config.template, overrideIndex);

        /**
         * Delete the template string from the component config.
         * The complete rendered template including all overrides will be added later.
         */
        delete config.template;
    }

    const overrides = overrideRegistry.get(componentName) || [];

    if (overrideIndex !== null && overrideIndex >= 0 && overrides.length > 0) {
        overrides.splice(overrideIndex, 0, config);
    } else {
        overrides.push(config);
    }

    overrideRegistry.set(componentName, overrides);

    return config;
}

/**
 * Returns the complete rendered template of the component.
 *
 * @param componentName
 * @returns {string}
 */
function getComponentTemplate(componentName) {
    return TemplateFactory.getRenderedTemplate(componentName);
}

/**
 * Returns the complete component including extension and overrides.
 *
 * ToDo: Implement overrides for recursive extended components including the template.
 *
 * @param componentName
 * @returns {*}
 */
function build(componentName) {
    if (!componentRegistry.has(componentName)) {
        return false;
    }

    let config = componentRegistry.get(componentName);

    if (config.extends) {
        config = getExtendedComponent(componentName);
    }

    if (overrideRegistry.has(componentName)) {
        const overrides = overrideRegistry.get(componentName);

        overrides.forEach((overrideComp) => {
            config = mergeConfig(config, overrideComp);
        });
    }

    /**
     * Get the final template result including all overrides.
     */
    config.template = getComponentTemplate(componentName);

    return config;
}

/**
 * Get the final version of an extended component.
 * Called recursively for multiple extended components.
 *
 * @param componentName
 * @returns {*}
 */
function getExtendedComponent(componentName) {
    if (!componentRegistry.has(componentName)) {
        return {};
    }

    let config = componentRegistry.get(componentName);

    if (!config.extends || !componentRegistry.has(config.extends)) {
        return config;
    }

    const extendComponent = getExtendedComponent(componentRegistry.get(config.extends));

    config = mergeConfig({}, extendComponent, config);

    return config;
}

/**
 * ToDo: Add possibility to access original parent component.
 *
 * @param target
 * @param source
 * @param additionalSources
 * @returns {*}
 */
function mergeConfig(target, source, ...additionalSources) {
    if (!utils.isObject(target) || !utils.isObject(source)) {
        return source;
    }

    const config = Object.assign({}, target);

    Object.keys(source).forEach((key) => {
        if (config.hasOwnProperty(key) && config[key] !== null) {
            // Merge the special data function used for data binding
            if (utils.isFunction(config[key]) && key === 'data') {
                const mergedData = mergeConfig(config[key](), source[key]());

                config[key] = function data() {
                    return mergedData;
                };
            // Merge arrays
            } else if (utils.isArray(config[key])) {
                config[key] = [...config[key], ...source[key]];
            // Deep merge objects
            } else if (utils.isObject(source[key])) {
                config[key] = mergeConfig(config[key], source[key]);
            } else {
                config[key] = source[key];
            }
        } else {
            config[key] = source[key];
        }
    });

    if (additionalSources.length > 0) {
        return mergeConfig(config, ...additionalSources);
    }

    return config;
}
