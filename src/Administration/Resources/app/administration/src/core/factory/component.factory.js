/**
 * @module core/factory/component
 */
import { warn } from 'src/core/service/utils/debug.utils';
import { cloneDeep } from 'src/core/service/utils/object.utils';
import TemplateFactory from 'src/core/factory/template.factory';

export default {
    register,
    extend,
    override,
    build,
    getComponentTemplate,
    getComponentRegistry,
    getOverrideRegistry,
    getComponentHelper,
    registerComponentHelper,
    resolveComponentTemplates,
    markComponentTemplatesAsNotResolved
};

/**
 * Indicates if the templates of the components are resolved.
 * @type {boolean}
 */
let templatesResolved = false;

/**
 * Registry which holds all components
 *
 * @type {Map<any, any>}
 */
const componentRegistry = new Map();

/**
 * Registry which holds all component overrides
 *
 * @type {Map<any, any>}
 */
const overrideRegistry = new Map();

/**
 * Registry for globally registered helper functions like src/app/service/map-error.service.js
 * @type {Map<any, any>}
 */
const componentHelper = {};

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
 * Returns the map of component helper functions
 *
 * @returns {Map<any, any>}
 */
function getComponentHelper() {
    return componentHelper;
}

/**
 * Register a new component helper function
 *
 * @returns {Boolean}
 */
function registerComponentHelper(name, helperFunction) {
    if (!name || !name.length) {
        warn('ComponentFactory/ComponentHelper', 'A ComponentHelper always needs a name.', helperFunction);
        return false;
    }

    if (componentHelper.hasOwnProperty(name)) {
        warn('ComponentFactory/ComponentHelper', `A ComponentHelper with the name ${name} already exists.`, helperFunction);
        return false;
    }

    componentHelper[name] = helperFunction;

    return true;
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
        warn(
            'ComponentFactory',
            'A component always needs a name.',
            componentConfiguration
        );
        return false;
    }

    if (componentRegistry.has(componentName)) {
        warn(
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
    } else if (!config.functional && typeof config.render !== 'function') {
        warn(
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
function extend(componentName, extendComponentName, componentConfiguration = {}) {
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
    if (!templatesResolved) {
        resolveComponentTemplates();
    }
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
    if (!templatesResolved) {
        resolveComponentTemplates();
    }

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
        // clone the override configuration to prevent side-effects to the config
        const overrides = cloneDeep(overrideRegistry.get(componentName));

        convertOverrides(overrides).forEach((overrideComp) => {
            const comp = Object.create(overrideComp);

            comp.extends = Object.create(config);
            comp._isOverride = true;
            config = comp;
        });
    }

    const superRegistry = buildSuperRegistry(config);

    if (isNotEmptyObject(superRegistry)) {
        const inheritedFrom = isAnOverride(config)
            ? `#${componentName}`
            : config.extends.name;

        config.methods = { ...config.methods, ...addSuperBehaviour(inheritedFrom, superRegistry) };
    }

    /**
     * if config has a render function it will ignore template
     */
    if (typeof config.render === 'function') {
        delete config.template;
        return config;
    }

    if (skipTemplate) {
        delete config.template;
        return config;
    }

    /**
     * Get the final template result including all overrides or extensions.
     */
    config.template = getComponentTemplate(componentName);

    if (typeof config.template !== 'string') {
        return false;
    }

    return config;
}

/**
 * Reorganizes the structure of the given overrides.
 * @param {Object} overrides
 * @returns {Object}
 */
function convertOverrides(overrides) {
    return overrides
        .reduceRight((acc, overrideComp) => {
            if (acc.length === 0) {
                return [overrideComp];
            }

            const previous = acc.shift();

            Object.entries(overrideComp).forEach(([prop, values]) => {
                // check if current property exists in previous override
                if (previous.hasOwnProperty(prop)) {
                    // if it exists iterate over the methods
                    // and hoist them if the don't exists in previous override

                    // check for methods in current property-object
                    if (typeof values === 'object') {
                        Object.entries(values).forEach(([methodName, methodFunction]) => {
                            if (!previous[prop].hasOwnProperty(methodName)) {
                                // move the function over
                                previous[prop][methodName] = methodFunction;
                                delete overrideComp[prop][methodName];
                            }
                        });
                    }
                } else {
                    // move the property over
                    previous[prop] = values;
                    delete overrideComp[prop];
                }
            });

            return [...[overrideComp], previous, ...acc];
        }, []);
}

/**
 * Build the superRegistry for computed properties and methods.
 * @param {Object} config
 * @returns {Object}
 */
function buildSuperRegistry(config) {
    let superRegistry = {};

    // if it is an override build the super registry recursively
    if (config._isOverride) {
        superRegistry = buildSuperRegistry(config.extends);
    }

    /**
     * Search for `this.$super()` call in every `computed` property and `method``
     * and resolve the call chain.
     */
    ['computed', 'methods'].forEach((methodOrComputed) => {
        if (!config[methodOrComputed]) {
            return;
        }

        const methods = Object.entries(config[methodOrComputed]);

        methods.forEach(([name, method]) => {
            // is computed getter/setter definition
            if (methodOrComputed === 'computed' && typeof method === 'object') {
                Object.entries(method).forEach(([cmd, func]) => {
                    const path = `${name}.${cmd}`;

                    superRegistry = updateSuperRegistry(superRegistry, path, func, methodOrComputed, config);
                });
            } else {
                superRegistry = updateSuperRegistry(superRegistry, name, method, methodOrComputed, config);
            }
        });
    });

    return superRegistry;
}

function updateSuperRegistry(superRegistry, methodName, method, methodOrComputed, config) {
    const superCallPattern = /\.\$super/g;
    const methodString = method.toString();
    const hasSuperCall = superCallPattern.test(methodString);

    if (!hasSuperCall) {
        return superRegistry;
    }

    if (!superRegistry.hasOwnProperty(methodName)) {
        superRegistry[methodName] = {};
    }

    const overridePrefix = isAnOverride(config) ? '#' : '';

    superRegistry[methodName] = resolveSuperCallChain(config, methodName, methodOrComputed, overridePrefix);

    return superRegistry;
}

/**
 * Returns a superBehaviour object, which contains a super-like callstack.
 * @param {String} inheritedFrom
 * @param {Object} superRegistry
 * @returns {Object}
 */
function addSuperBehaviour(inheritedFrom, superRegistry) {
    return {
        $super(name, ...args) {
            this._initVirtualCallStack(name);

            const superStack = this._findInSuperRegister(name);

            const superFuncObject = superStack[this._virtualCallStack[name]];

            this._virtualCallStack[name] = superFuncObject.parent;

            const result = superFuncObject.func.bind(this)(...args);

            // reset the virtual call-stack
            if (superFuncObject.parent) {
                this._virtualCallStack[name] = this._inheritedFrom();
            }

            return result;
        },
        _initVirtualCallStack(name) {
            // if there is no virtualCallStack
            if (!this._virtualCallStack) {
                this._virtualCallStack = { name };
            }

            if (!this._virtualCallStack[name]) {
                this._virtualCallStack[name] = this._inheritedFrom();
            }
        },
        _findInSuperRegister(name) {
            return this._superRegistry()[name];
        },
        _superRegistry() {
            return superRegistry;
        },
        _inheritedFrom() {
            return inheritedFrom;
        }
    };
}

/**
 * Resolves the super call chain for a given method (or computed property).
 * @param {Object} config
 * @param {String} methodName
 * @param {String} methodsOrComputed
 * @param {String} overridePrefix
 * @returns {Object}
 */
function resolveSuperCallChain(config, methodName, methodsOrComputed = 'methods', overridePrefix = '') {
    const extension = config.extends;

    if (!extension) {
        return {};
    }

    const parentName = `${overridePrefix}${extension.name}`;
    let parentsParentName = extension.extends ? `${overridePrefix}${extension.extends.name}` : null;

    if (parentName === parentsParentName) {
        if (overridePrefix.length > 0) {
            overridePrefix = `#${overridePrefix}`;
        }

        parentsParentName = `${overridePrefix}${extension.extends.name}`;
    }

    const methodFunction = findMethodInChain(extension, methodName, methodsOrComputed);

    const parentBlock = {};
    parentBlock[parentName] = {
        parent: parentsParentName,
        func: methodFunction
    };

    const resolvedParent = resolveSuperCallChain(extension, methodName, methodsOrComputed, overridePrefix);

    const result = {
        ...resolvedParent,
        ...parentBlock
    };

    return result;
}

/**
 * Returns a method in the extension chain object.
 * @param {Object} extension
 * @param {String} methodName
 * @param {String} methodsOrComputed
 * @returns {Object} superCallChain
 */
function findMethodInChain(extension, methodName, methodsOrComputed) {
    const splitPath = methodName.split('.');

    if (splitPath.length > 1) {
        return resolveGetterSetterChain(extension, splitPath, methodsOrComputed);
    }

    if (extension[methodsOrComputed] && extension[methodsOrComputed][methodName]) {
        return extension[methodsOrComputed][methodName];
    }

    if (extension.extends) {
        return findMethodInChain(extension.extends, methodName, methodsOrComputed);
    }

    return null;
}

/**
 * Returns a method in the extension chain object with a method path like `getterSetterMethod.get`.
 * @param {Object} extension
 * @param {string[]} path
 * @param {String} methodsOrComputed
 * @returns {Object} superCallChain
 */
function resolveGetterSetterChain(extension, path, methodsOrComputed) {
    const [methodName, cmd] = path;

    if (!extension[methodsOrComputed]) {
        return findMethodInChain(extension.extends, methodName, methodsOrComputed);
    }

    if (!extension[methodsOrComputed][methodName]) {
        return findMethodInChain(extension.extends, methodName, methodsOrComputed);
    }

    return extension[methodsOrComputed][methodName][cmd];
}

/**
 * Tests a component, whether it is an extension or an override.
 * @param {Object} config
 * @returns {Boolean}
 */
function isAnOverride(config) {
    if (!config.extends) {
        return false;
    }

    return config.extends.name === config.name;
}

/**
 * Tests an object, whether it is empty or not.
 * @param {Object} obj
 * @returns {Boolean}
 */
function isNotEmptyObject(obj) {
    return (Object.keys(obj).length !== 0 && obj.constructor === Object);
}

/**
 * Resolves the component templates using the template factory.
 * @returns {boolean}
 */
function resolveComponentTemplates() {
    TemplateFactory.resolveTemplates();
    templatesResolved = true;
    return true;
}

/**
 * Helper method which clears the normalized templates and marks
 * the indicator as `false`, so another resolve run is possible
 * @returns {boolean}
 */
function markComponentTemplatesAsNotResolved() {
    TemplateFactory.getNormalizedTemplateRegistry().clear();
    templatesResolved = false;
    return true;
}
