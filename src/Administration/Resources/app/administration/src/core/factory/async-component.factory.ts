/**
 * @package admin
 */

import { warn } from 'src/core/service/utils/debug.utils';
import { cloneDeep } from 'src/core/service/utils/object.utils';
import TemplateFactory from 'src/core/factory/template.factory';
// eslint-disable-next-line import/no-named-default
import type { default as Vue, ComponentOptions } from 'vue';
import type {
    ThisTypedComponentOptionsWithRecordProps,
    ThisTypedComponentOptionsWithArrayProps,
// eslint-disable-next-line import/no-unresolved
} from 'vue/types/options';
import { defineComponent } from 'vue';
import type { ComponentOptionsMixin } from 'vue/types/v3-component-options';

/**
 * This method is just for adding TypeScript support to component configuration and provides a this context.
 *
 * Function overload to support all vue component object variations.
 */

const wrapComponentConfig = defineComponent;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    register,
    extend,
    override,
    build,
    wrapComponentConfig,
    getComponentTemplate,
    getComponentRegistry,
    getOverrideRegistry,
    getComponentHelper,
    _clearComponentHelper,
    registerComponentHelper,
    resolveComponentTemplates,
    markComponentTemplatesAsNotResolved,
    isSyncComponent,
    markComponentAsSync,
};

// @ts-expect-error
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export interface ComponentConfig<V extends Vue = Vue> extends ComponentOptions<V> {
    functional?: boolean,
    extends?: ComponentConfig<V> | string,
    _isOverride?: boolean,
    component?: Promise<ComponentConfig|boolean>,
    loading?: ComponentConfig,
    delay?: number,
}

/**
 * Registry which holds all components
 * @private
 */
type AwaitedComponentConfig = () => Promise<ComponentConfig | boolean>;
const componentRegistry = new Map<string, AwaitedComponentConfig>();

/**
 * Registry which holds all component overrides
 * @private
 */
const overrideRegistry = new Map<string, AwaitedComponentConfig[]>();

/**
 * Registry for globally registered helper functions like src/app/service/map-error.service.js
 * @private
 */
const componentHelper: { [helperName: string]: unknown } = {};

/**
 * Contains all components which should be created as a async component
 * @private
 */
const syncComponents = new Set<string>();

/**
 * Check if the component should be a synchronous component
 * @private
 */
function isSyncComponent(componentName: string): boolean {
    return syncComponents.has(componentName);
}

/**
 * Add a component to the synchronous component list. This
 * component will be compiled directly on boot.
 * @public
 */
function markComponentAsSync(componentName: string): void {
    syncComponents.add(componentName);
}

/**
 * Returns the map with all registered components.
 * @private
 */
function getComponentRegistry(): Map<string, AwaitedComponentConfig> {
    return componentRegistry;
}

/**
 * Returns the map with all registered component overrides.
 * @private
 */
function getOverrideRegistry(): Map<string, AwaitedComponentConfig[]> {
    return overrideRegistry;
}

/**
 * Returns the map of component helper functions
 * @public
 */
function getComponentHelper(): { [helperName: string]: unknown } {
    return componentHelper;
}

/**
 * @private
 */
function _clearComponentHelper(): void {
    Object.keys(componentHelper).forEach((key) => {
        delete componentHelper[key];
    });
}

/**
 * Register a new component helper function
 * @public
 */
function registerComponentHelper(name: string, helperFunction: unknown): boolean {
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
 * @public
 */
/* eslint-disable max-len */
// function overload to support all vue component object variations
// @ts-expect-error
function register<V extends Vue, Data, Methods, Computed, PropNames extends string, Setup, Mixin, Extends extends ComponentOptionsMixin>(componentName: string, componentConfiguration: ThisTypedComponentOptionsWithArrayProps<V, Data, Methods, Computed, PropNames, Setup, Mixin, Extends>): boolean | ComponentConfig;
function register<V extends Vue, Data, Methods, Computed, PropNames extends string, Setup, Mixin extends ComponentOptionsMixin, Extends extends ComponentOptionsMixin>(componentName: string, componentConfiguration: () => Promise<ThisTypedComponentOptionsWithArrayProps<V, Data, Methods, Computed, PropNames, Setup, Mixin, Extends>>): boolean | ComponentConfig;
function register<V extends Vue, Data, Methods, Computed, Props, Setup, Mixin extends ComponentOptionsMixin, Extends extends ComponentOptionsMixin>(componentName: string, componentConfiguration: ThisTypedComponentOptionsWithRecordProps<V, Data, Methods, Computed, Props, Setup, Mixin, Extends>): boolean | ComponentConfig;
function register<V extends Vue, Data, Methods, Computed, Props, Setup, Mixin extends ComponentOptionsMixin, Extends extends ComponentOptionsMixin>(componentName: string, componentConfiguration: () => Promise<ThisTypedComponentOptionsWithRecordProps<V, Data, Methods, Computed, Props, Setup, Mixin, Extends>>): boolean | ComponentConfig;
function register(componentName: string, componentConfiguration: ComponentConfig<Vue> | (() => Promise<ComponentConfig<Vue>>)): boolean | (() => Promise<ComponentConfig|boolean>) {
/* eslint-enable max-len */
    if (!componentName || !componentName.length) {
        warn(
            'ComponentFactory',
            'A component always needs a name.',
            componentConfiguration,
        );
        return false;
    }

    if (componentRegistry.has(componentName)) {
        warn(
            'ComponentFactory',
            `The component "${componentName}" is already registered. Please select a unique name for your component.`,
            componentConfiguration,
        );
        return false;
    }

    const configurationResolveMethod = async (): Promise<false | ComponentConfig<Vue>> => {
        const awaitedConfig = typeof componentConfiguration === 'function'
            ? componentConfiguration
            : (): Promise<ComponentConfig> => Promise.resolve(componentConfiguration);

        let awaitedConfigResult = await awaitedConfig();

        /**
         * Check if the resulted config is a ES module. Then we need to use the default
         * value of it.
         */
        if (awaitedConfigResult.hasOwnProperty('default')) {
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            awaitedConfigResult = awaitedConfigResult.default;
        }
        const config = { ...awaitedConfigResult };

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
                config,
            );
            return false;
        }

        return config;
    };

    componentRegistry.set(componentName, configurationResolveMethod);

    return configurationResolveMethod;
}

/**
 * Create a new component extending from another existing component.
 * @public
 */
function extend(
    componentName: string,
    extendComponentName: string,
    componentConfiguration: ComponentConfig | (() => Promise<ComponentConfig>) = { name: '' },
): () => Promise<ComponentConfig> {
    let config: ComponentConfig;

    const configurationResolveMethod = async (): Promise<ComponentConfig> => {
        if (config) {
            return config;
        }

        const awaitedConfig = typeof componentConfiguration === 'function'
            ? componentConfiguration
            : (): Promise<ComponentConfig> => Promise.resolve(componentConfiguration);

        let awaitedConfigResult = await awaitedConfig();

        /**
         * Check if the resulted config is a ES module. Then we need to use the default
         * value of it.
         */
        if (awaitedConfigResult.hasOwnProperty('default')) {
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            awaitedConfigResult = awaitedConfigResult.default;
        }

        config = { ...awaitedConfigResult };

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

        return config;
    };

    componentRegistry.set(componentName, configurationResolveMethod);

    return configurationResolveMethod;
}

/**
 * Override an existing component including its config and template.
 * @public
 */
function override(
    componentName: string,
    componentConfiguration: ComponentConfig|(() => Promise<ComponentConfig>),
    overrideIndex = null,
): () => Promise<ComponentConfig> {
    let config: ComponentConfig;
    const configResolveMethod = async (): Promise<ComponentConfig> => {
        if (config) {
            return config;
        }

        const awaitedConfig = typeof componentConfiguration === 'function'
            ? componentConfiguration
            : (): Promise<ComponentConfig> => Promise.resolve(componentConfiguration);

        config = await awaitedConfig();

        /**
         * Check if the resulted config is a ES module. Then we need to use the default
         * value of it.
         */
        if (config.hasOwnProperty('default')) {
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            config = config.default;
        }

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

        return config;
    };

    const overrides = overrideRegistry.get(componentName) || [];

    if (overrideIndex !== null && overrideIndex >= 0 && overrides.length > 0) {
        overrides.splice(overrideIndex, 0, configResolveMethod);
    } else {
        overrides.push(configResolveMethod);
    }

    overrideRegistry.set(componentName, overrides);

    return configResolveMethod;
}

/**
 * Returns the complete rendered template of the component.
 * @private
 */
async function getComponentTemplate(componentName: string): Promise<string | null> {
    await initComponent(componentName);

    return TemplateFactory.getRenderedTemplate(componentName);
}

async function initComponent(componentName: string): Promise<void> {
    const asyncComponent = componentRegistry.get(componentName);
    const asyncOverrideComponent = overrideRegistry.get(componentName);

    if (asyncComponent) {
        await asyncComponent();
    }

    if (asyncOverrideComponent) {
        await Promise.all(asyncOverrideComponent.map(c => c()));
    }
}

/**
 * Returns the complete component including extension and overrides.
 * @private
 */
async function build(componentName: string, skipTemplate = false): Promise<ComponentConfig | boolean> {
    const awaitedConfig = componentRegistry.get(componentName);

    if (!awaitedConfig) {
        throw new Error(
            `The component registry has not found a component with the name "${componentName}".`,
        );
    }

    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
    const resultConfig: ComponentConfig|boolean = await awaitedConfig();
    if (typeof resultConfig === 'boolean') {
        throw new Error(
            `The component registry could not build the component with the name "${componentName}".`,
        );
    }

    // let config: ComponentConfig = Object.create(resultConfig) as ComponentConfig;
    let config: ComponentConfig = { ...resultConfig } as ComponentConfig;

    if (!config) {
        throw new Error(
            `The config of the component "${componentName}" is invalid.`,
        );
    }

    if (config.extends) {
        let extendComp: ComponentConfig | undefined;

        if (typeof config.extends === 'string') {
            const buildedComp = await build(config.extends, true);

            if (typeof buildedComp !== 'boolean') {
                extendComp = buildedComp;
            }
        }

        if (extendComp) {
            config.extends = extendComp;
        } else {
            delete config.extends;
        }
    }

    if (overrideRegistry.has(componentName)) {
        // clone the override configuration to prevent side-effects to the config
        const overrides = cloneDeep(overrideRegistry.get(componentName));

        const convertedOverrides = await convertOverrides(overrides);

        convertedOverrides.forEach((overrideComp) => {
            overrideComp.extends = config;
            overrideComp._isOverride = true;
            config = { ...overrideComp };
        });
    }

    const superRegistry = buildSuperRegistry(config);

    if (isNotEmptyObject(superRegistry) && config) {
        const inheritedFrom = isAnOverride(config)
            ? `#${componentName}`
            : typeof config.extends !== 'string' && config?.extends?.name;

        // @ts-expect-error
        config.methods = { ...config.methods, ...addSuperBehaviour(inheritedFrom, superRegistry) };
    }

    /**
     * if config has a render function it will ignore template
     */
    if (typeof config?.render === 'function') {
        delete config.template;
        return config;
    }

    if (skipTemplate && config) {
        delete config.template;
        return config;
    }

    /**
     * Get the final template result including all overrides or extensions.
     */
    const componentTemplate = await getComponentTemplate(componentName);
    if (config && typeof componentTemplate === 'string') {
        config.template = componentTemplate;
    }

    if (typeof config?.template !== 'string') {
        return false;
    }

    return config;
}

/**
 * Reorganizes the structure of the given overrides.
 */
async function convertOverrides(awaitedOverrides: AwaitedComponentConfig[] | undefined): Promise<ComponentConfig[]> {
    if (!awaitedOverrides) {
        return [];
    }

    const overrides = await Promise.all(awaitedOverrides.map((awaitedOverride) => {
        return awaitedOverride();
    }));

    // eslint-disable-next-line max-len
    /* eslint-disable @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-assignment */
    // @ts-expect-error
    return overrides
        // @ts-expect-error
        .reduceRight((acc, overrideComp) => {
            if (acc.length === 0) {
                return [overrideComp];
            }

            const previous = acc.shift();

            Object.entries(overrideComp).forEach(([prop, values]) => {
                // check if current property exists in previous override
                // @ts-expect-error
                if (previous && previous.hasOwnProperty(prop)) {
                    // if it exists iterate over the methods
                    // and hoist them if they don't exists in previous override


                    // ignore array based properties
                    if (Array.isArray(values)) {
                        return;
                    }

                    // check for methods in current property-object
                    if (typeof values === 'object') {
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                        Object.entries(values).forEach(([methodName, methodFunction]) => {
                            // @ts-expect-error
                            if (!previous[prop].hasOwnProperty(methodName)) {
                                // move the function over
                                // @ts-expect-error
                                previous[prop][methodName] = methodFunction;
                                // @ts-expect-error
                                delete overrideComp[prop][methodName];
                            }
                        });
                    }
                } else {
                    // move the property over
                    // @ts-expect-error
                    previous[prop] = values;
                    // @ts-expect-error
                    delete overrideComp[prop];
                }
            });

            return [...[overrideComp], previous, ...acc];
        }, []);

    /* eslint-enable @typescript-eslint/no-unsafe-member-access */
}

interface SuperRegistry {
    [name: string]: {
        [sName: string]: {
            parent: string,
            func: (args: $TSFixMe[]) => $TSFixMe
        }
    }
}

interface SuperBehavior {
    $super(name: string, ...args: $TSFixMe[]): $TSFixMe,
    _initVirtualCallStack(name: string): void,
    _findInSuperRegister(name: string): SuperRegistry,
    _superRegistry(): SuperRegistry,
    _inheritedFrom(): string,
    _virtualCallStack: { [name: string]: string }
}

/**
 * Build the superRegistry for computed properties and methods.
 */
function buildSuperRegistry(config: ComponentConfig): SuperRegistry {
    let superRegistry: SuperRegistry = {};

    // if it is an override build the super registry recursively
    if (config._isOverride && config.extends && typeof config.extends !== 'string') {
        superRegistry = buildSuperRegistry(config.extends);
    }

    /**
     * Search for `this.$super()` call in every `computed` property and `method``
     * and resolve the call chain.
     */
    ['computed', 'methods'].forEach((methodOrComputed) => {
        // @ts-expect-error
        const ConfigMethodOrComputed = config[methodOrComputed];

        if (!ConfigMethodOrComputed) {
            return;
        }

        // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
        const methods = Object.entries(ConfigMethodOrComputed);

        methods.forEach(([name, method]) => {
            // is computed getter/setter definition
            if (methodOrComputed === 'computed' && typeof method === 'object') {
                Object.entries(method as object).forEach(([cmd, func]) => {
                    const path = `${name}.${cmd}`;

                    superRegistry = updateSuperRegistry(superRegistry, path, func, methodOrComputed, config);
                });
            } else {
                // @ts-expect-error
                superRegistry = updateSuperRegistry(superRegistry, name, method, methodOrComputed, config);
            }
        });
    });

    return superRegistry;
}

function updateSuperRegistry(
    superRegistry: SuperRegistry,
    methodName: string,
    method: unknown,
    methodOrComputed: 'methods'|'computed',
    config: ComponentConfig,
): SuperRegistry {
    const superCallPattern = /\.\$super/g;
    const methodString = typeof method === 'function' && method.toString();
    const hasSuperCall = methodString && superCallPattern.test(methodString);

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
 */
function addSuperBehaviour(inheritedFrom: string, superRegistry: SuperRegistry): SuperBehavior {
    const superBehavior: SuperBehavior = {
        $super(this: SuperBehavior, name, ...args) {
            this._initVirtualCallStack(name);

            const superStack = this._findInSuperRegister(name);

            const superFuncObject = superStack[this._virtualCallStack[name]];

            // @ts-expect-error
            this._virtualCallStack[name] = superFuncObject.parent;

            // @ts-expect-error
            const result = superFuncObject.func.bind(this)(...args);

            // reset the virtual call-stack
            if (superFuncObject.parent) {
                this._virtualCallStack[name] = this._inheritedFrom();
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
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
        // @ts-expect-error
        _findInSuperRegister(name) {
            return this._superRegistry()[name];
        },
        _superRegistry() {
            return superRegistry;
        },
        _inheritedFrom() {
            return inheritedFrom;
        },
    };

    return superBehavior;
}

/**
 * Resolves the super call chain for a given method (or computed property).
 */
function resolveSuperCallChain(
    config: ComponentConfig,
    methodName: string,
    methodsOrComputed: 'methods'|'computed' = 'methods',
    overridePrefix = '',
): $TSFixMe {
    const extension = config.extends;

    if (!extension || typeof extension === 'string') {
        return {};
    }

    const parentName = `${overridePrefix}${extension.name ?? ''}`;
    let parentsParentName = typeof extension.extends === 'object' && extension.extends
        ? `${overridePrefix}${extension.extends.name ?? ''}`
        : null;

    if (parentName === parentsParentName) {
        if (overridePrefix.length > 0 || extension._isOverride) {
            overridePrefix = `#${overridePrefix}`;
        }

        const extendsName = (
            extension
            && extension.extends
            && typeof extension.extends !== 'string'
            && extension.extends.name
        );
        const extendsNameString = typeof extendsName === 'string' ? extendsName : '';
        parentsParentName = `${overridePrefix}${extendsNameString}`;
    }

    const methodFunction = findMethodInChain(extension, methodName, methodsOrComputed);

    const parentBlock = {};
    // @ts-expect-error
    parentBlock[parentName] = {
        parent: parentsParentName,
        func: methodFunction,
    };

    const resolvedParent = resolveSuperCallChain(extension, methodName, methodsOrComputed, overridePrefix);

    const result = {
        ...resolvedParent,
        ...parentBlock,
    };

    return result;
}

/**
 * Returns a method in the extension chain object.
 */
function findMethodInChain(
    extension: ComponentConfig,
    methodName: string,
    methodsOrComputed: 'methods'|'computed',
): $TSFixMe {
    const splitPath = methodName.split('.');

    if (splitPath.length > 1) {
        return resolveGetterSetterChain(extension, splitPath, methodsOrComputed);
    }

    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
    if (extension[methodsOrComputed]?.[methodName]) {
        // @ts-expect-error
        return extension[methodsOrComputed][methodName];
    }

    if (extension.extends) {
        // @ts-expect-error
        return findMethodInChain(extension.extends, methodName, methodsOrComputed);
    }

    return null;
}

/**
 * Returns a method in the extension chain object with a method path like `getterSetterMethod.get`.
 */
function resolveGetterSetterChain(
    extension: ComponentConfig,
    path: string[],
    methodsOrComputed: 'methods'|'computed',
):$TSFixMe {
    const [methodName, cmd] = path;

    if (!extension[methodsOrComputed]) {
        // @ts-expect-error
        return findMethodInChain(extension.extends, methodName, methodsOrComputed);
    }

    // @ts-expect-error
    if (!extension[methodsOrComputed][methodName]) {
        // @ts-expect-error
        return findMethodInChain(extension.extends, methodName, methodsOrComputed);
    }

    // @ts-expect-error
    // eslint-disable-next-line @typescript-eslint/no-unsafe-return
    return extension[methodsOrComputed][methodName][cmd];
}

/**
 * Tests a component, whether it is an extension or an override.
 */
function isAnOverride(config: ComponentConfig): boolean {
    if (!config.extends || typeof config.extends === 'string') {
        return false;
    }

    return config.extends.name === config.name;
}

/**
 * Tests an object, whether it is empty or not.
 */
function isNotEmptyObject(obj: $TSFixMe): boolean {
    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument,@typescript-eslint/no-unsafe-member-access
    return (Object.keys(obj).length !== 0 && obj.constructor === Object);
}

/**
 * Resolves the component templates using the template factory.
 * @private
 */
function resolveComponentTemplates(): boolean {
    TemplateFactory.resolveTemplates();
    return true;
}

/**
 * Helper method which clears the normalized templates and marks
 * the indicator as `false`, so another resolve run is possible
 * @private
 */
function markComponentTemplatesAsNotResolved(): boolean {
    TemplateFactory.getNormalizedTemplateRegistry().clear();
    return true;
}
