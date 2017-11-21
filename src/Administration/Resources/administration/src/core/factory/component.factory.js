import utils from 'src/core/service/util.service';
import TemplateFactory from 'src/core/factory/template.factory';

export {
    register,
    extend,
    override,
    build,
    getComponentTemplate,
    getComponentRegistry,
    getOverrideRegistry
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
 * Returns the map with all registered component overrides.
 *
 * @returns {Map}
 */
function getOverrideRegistry() {
    return overrideRegistry;
}

/**
 * Register a new component.
 *
 * @param {String} componentName
 * @param {Object} [componentConfiguration={}]
 * @returns {Boolean|Object}
 */
function register(componentName, componentConfiguration = {}) {
    const config = componentConfiguration;

    if (!componentName || !componentName.length) {
        utils.warn(
            'ComponentFactory',
            'A component always needs a name.',
            componentConfiguration
        );
        return false;
    }

    if (componentRegistry.has(componentName)) {
        utils.warn(
            'ComponentFactory',
            `The component "${componentName}" is already registered. Please select a unique name for your component.`,
            config
        );
        return false;
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
        return false;
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
        TemplateFactory.extendComponentTemplate(componentName, extendComponentName, config.template);

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
 * @param componentName
 * @param skipTemplate
 * @returns {*}
 */
function build(componentName, skipTemplate = false) {
    if (!componentRegistry.has(componentName)) {
        return false;
    }

    let config = Object.create(componentRegistry.get(componentName));

    if (config.extends) {
        const extendComp = build(config.extends, true);

        if (extendComp) {
            config.extends = extendComp;
        } else {
            delete config.extends;
        }
    }

    if (overrideRegistry.has(componentName)) {
        const overrides = overrideRegistry.get(componentName);

        overrides.forEach((overrideComp) => {
            const comp = Object.create(overrideComp);

            comp.extends = Object.create(config);
            config = comp;
        });
    }

    /**
     * Get the final template result including all overrides or extensions.
     */
    if (skipTemplate !== true) {
        config.template = getComponentTemplate(componentName);
    } else {
        delete config.template;
    }

    return config;
}
