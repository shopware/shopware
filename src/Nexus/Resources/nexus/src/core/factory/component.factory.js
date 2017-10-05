import utils from 'src/core/service/util.service';
import TemplateFactory from 'src/core/factory/template.factory';

export {
    register,
    extend,
    override,
    getComponentTemplate,
    getComponentRegistry
};

/** @type Map componentRegistry - Registry which holds all component registry */
const componentRegistry = new Map();

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
    let config = componentConfiguration;

    config = utils.merge(config, {
        name: componentName
    });

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
    let config = componentConfiguration;

    if (!componentRegistry.has(extendComponentName)) {
        utils.warn(
            'ComponentFactory',
            `The component ${extendComponentName} doesn't exists,`,
            `we're registering a new component named ${componentName} instead.`,
            componentConfiguration
        );

        return register(componentName, config);
    }

    const name = componentName;
    const extendedComponent = componentRegistry.get(extendComponentName);

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

    config = Object.assign(config, {
        name: componentName
    });

    config = mergeConfig({}, extendedComponent, config);

    componentRegistry.set(componentName, config);

    return config;
}

/**
 * Override an existing component including its config and template.
 *
 * ToDo@All: Keep reference to original config object.
 *
 * @param componentName
 * @param componentConfiguration
 * @param overrideIndex
 * @returns {*}
 */
function override(componentName, componentConfiguration, overrideIndex = null) {
    let config = componentConfiguration;

    if (!componentRegistry.has(componentName)) {
        return register(componentName, config);
    }

    config = Object.assign(config, {
        name: componentName
    });

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

    config = mergeConfig(componentRegistry.get(componentName), config);
    componentRegistry.set(componentName, config);

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

function mergeConfig(target, source, ...additionalSources) {
    if (!utils.isObject(target) || !utils.isObject(source)) {
        return source;
    }

    const parent = Object.assign({}, target);
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
        } else if (key !== 'parent') {
            config[key] = source[key];
        }
    });

    // Keep reference to original config
    if (!utils.isEmpty(parent)) {
        config.parent = parent;
    }

    if (additionalSources.length > 0) {
        return mergeConfig(config, ...additionalSources);
    }

    return config;
}
