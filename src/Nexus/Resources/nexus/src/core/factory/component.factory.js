import utils from 'src/core/service/util.service';
import TemplateFactory from 'src/core/factory/template.factory';

export default {
    register: registerComponent,
    extend: extendComponent,
    override: overrideComponent,
    getComponentTemplate,
    getComponentRegistry
};

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
 * @param componentName
 * @param componentConfiguration
 * @returns {*}
 */
function registerComponent(componentName, componentConfiguration) {
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
    }

    componentRegistry.set(componentName, config);

    return config;
}

/**
 * Create a new component extending from another existing component.
 *
 * @param componentName
 * @param extendComponentName
 * @param componentConfiguration
 * @returns {*}
 */
function extendComponent(componentName, extendComponentName, componentConfiguration) {
    let config = componentConfiguration;

    if (!componentRegistry.has(extendComponentName)) {
        return registerComponent(componentName, config);
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

    config = Object.assign({}, extendedComponent, config);

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
function overrideComponent(componentName, componentConfiguration, overrideIndex = null) {
    let config = componentConfiguration;

    if (!componentRegistry.has(componentName)) {
        return registerComponent(componentName, config);
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

    config = Object.assign({}, componentRegistry.get(componentName), config);

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
