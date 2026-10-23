/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-empty-object-type, @typescript-eslint/no-explicit-any */
import { warn } from 'src/core/service/utils/debug.utils';
import types from 'src/core/service/utils/types.utils';
import TemplateFactory from 'src/core/factory/template.factory';
import { indexTwigBlocksFromTemplate } from 'src/core/factory/twig-block-index';
import type {
    AllowedComponentProps,
    ComponentCustomProps,
    ComponentInjectOptions,
    ComponentOptions,
    ComponentOptionsMixin,
    ComponentOptionsWithArrayProps,
    ComponentOptionsWithObjectProps,
    ComponentOptionsWithoutProps,
    ComponentPropsOptions,
    ComputedOptions,
    DefineComponent,
    EmitsOptions,
    ExtractDefaultPropTypes,
    ExtractPropTypes,
    MethodOptions,
    ObjectEmitsOptions,
    SlotsType,
    VNodeProps,
} from 'vue';
import { defineComponent } from 'vue';

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

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export interface ComponentConfig extends ComponentOptions {
    functional?: boolean;
    extends?: ComponentConfig | string;
    _renderedBySfcTemplate?: boolean;
    component?: Promise<ComponentConfig | boolean>;
    loading?: ComponentConfig;
    delay?: number;
    name?: string;
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
type IndexedAwaitedComponentConfig = {
    index: number;
    config: AwaitedComponentConfig;
};
const overrideRegistry = new Map<string, IndexedAwaitedComponentConfig[]>();

/**
 * Registry for globally registered helper functions like src/app/service/map-error.service.ts
 * @private
 */
const componentHelper: ComponentHelper = {} as ComponentHelper;

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
function getOverrideRegistry(): Map<string, IndexedAwaitedComponentConfig[]> {
    return overrideRegistry;
}

/**
 * Returns the map of component helper functions
 * @public
 */
function getComponentHelper(): ComponentHelper {
    return componentHelper;
}

/**
 * @private
 */
function _clearComponentHelper(): void {
    Object.keys(componentHelper).forEach((key) => {
        delete componentHelper[key as keyof ComponentHelper];
    });
}

/**
 * Register a new component helper function
 * @public
 */
function registerComponentHelper<T extends keyof ComponentHelper>(name: T, helperFunction: ComponentHelper[T]): boolean {
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

type PublicProps = VNodeProps & AllowedComponentProps & ComponentCustomProps;

type EmitsToProps<T extends EmitsOptions> = T extends string[]
    ? {
          // eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents,
          [K in string & `on${Capitalize<T[number]>}`]?: (...args: any[]) => any;
      }
    : T extends ObjectEmitsOptions
      ? {
            // eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents
            [K in string & `on${Capitalize<string & keyof T>}`]?: K extends `on${infer C}`
                ? T[Uncapitalize<C>] extends null
                    ? (...args: any[]) => any
                    : (...args: T[Uncapitalize<C>] extends (...args: infer P) => any ? P : never) => any
                : never;
        }
      : {};

type ResolveProps<PropsOrPropOptions, E extends EmitsOptions> = Readonly<
    PropsOrPropOptions extends ComponentPropsOptions ? ExtractPropTypes<PropsOrPropOptions> : PropsOrPropOptions
> &
    ({} extends E ? {} : EmitsToProps<E>);

/**
 * Register a new component.
 * @public
 */

// Overload reference: https://github.com/vuejs/core/blob/1c525f75a3d17a6356d5f66765623c0ae7c0ebcc/packages/runtime-core/src/apiDefineComponent.ts#L92

// overload 1: direct setup function
// (uses user defined props interface)
// ####################
// ##### WARNING: #####
// ####################
// This first overload allows the usage
// of the setup function which is
// not supported by the Showpare extension
// system yet.
// ####################
// function register<
//     Props extends Record<string, any>,
//     E extends EmitsOptions = {},
//     EE extends string = string,
//     S extends SlotsType = {}
// >(
//     componentName: string,
//     setup: (
//         props: Props,
//         ctx: SetupContext<E, S>
//     ) => RenderFunction | Promise<RenderFunction>,
//     options?: Pick<ComponentOptions, "name" | "inheritAttrs"> & {
//         props?: (keyof Props)[];
//         emits?: E | EE[];
//         slots?: S;
//     }
// ): (props: Props & EmitsToProps<E>) => any;
// function register<
//     Props extends Record<string, any>,
//     E extends EmitsOptions = {},
//     EE extends string = string,
//     S extends SlotsType = {}
// >(
//     componentName: string,
//     setup: (
//         props: Props,
//         ctx: SetupContext<E, S>
//     ) => RenderFunction | Promise<RenderFunction>,
//     options?: Pick<ComponentOptions, "name" | "inheritAttrs"> & {
//         props?: ComponentObjectPropsOptions<Props>;
//         emits?: E | EE[];
//         slots?: S;
//     }
// ): (props: Props & EmitsToProps<E>) => any;

// overload 2: object format with no props
// (uses user defined props interface)
// return type is for Vetur and TSX support
function register<
    Props = {},
    RawBindings = {},
    D = {},
    C extends ComputedOptions = {},
    M extends MethodOptions = {},
    Mixin extends ComponentOptionsMixin = ComponentOptionsMixin,
    Extends extends ComponentOptionsMixin = ComponentOptionsMixin,
    E extends EmitsOptions = {},
    EE extends string = string,
    S extends SlotsType = {},
    I extends ComponentInjectOptions = {},
    II extends string = string,
>(
    componentName: string,
    componentConfiguration: ComponentOptionsWithoutProps<Props, RawBindings, D, C, M, Mixin, Extends, E, EE, I, II, S>,
): DefineComponent<
    Props,
    RawBindings,
    D,
    C,
    M,
    Mixin,
    Extends,
    E,
    EE,
    PublicProps,
    ResolveProps<Props, E>,
    ExtractDefaultPropTypes<Props>,
    S
>;

// overload 2 (ASYNC): object format with no props
// (uses user defined props interface)
// return type is for Vetur and TSX support
function register<
    Props = {},
    RawBindings = {},
    D = {},
    C extends ComputedOptions = {},
    M extends MethodOptions = {},
    Mixin extends ComponentOptionsMixin = ComponentOptionsMixin,
    Extends extends ComponentOptionsMixin = ComponentOptionsMixin,
    E extends EmitsOptions = {},
    EE extends string = string,
    S extends SlotsType = {},
    I extends ComponentInjectOptions = {},
    II extends string = string,
>(
    componentName: string,
    componentConfiguration: () => Promise<
        ComponentOptionsWithoutProps<Props, RawBindings, D, C, M, Mixin, Extends, E, EE, I, II, S>
    >,
): DefineComponent<
    Props,
    RawBindings,
    D,
    C,
    M,
    Mixin,
    Extends,
    E,
    EE,
    PublicProps,
    ResolveProps<Props, E>,
    ExtractDefaultPropTypes<Props>,
    S
>;

// overload 3: object format with array props declaration
// props inferred as { [key in PropNames]?: any }
// return type is for Vetur and TSX support
function register<
    PropNames extends string,
    RawBindings,
    D,
    C extends ComputedOptions = {},
    M extends MethodOptions = {},
    Mixin extends ComponentOptionsMixin = ComponentOptionsMixin,
    Extends extends ComponentOptionsMixin = ComponentOptionsMixin,
    E extends EmitsOptions = {},
    EE extends string = string,
    S extends SlotsType = {},
    I extends ComponentInjectOptions = {},
    II extends string = string,
    Props = Readonly<{ [key in PropNames]?: any }>,
>(
    componentName: string,
    componentConfiguration: ComponentOptionsWithArrayProps<PropNames, RawBindings, D, C, M, Mixin, Extends, E, EE, I, II, S>,
): DefineComponent<
    Props,
    RawBindings,
    D,
    C,
    M,
    Mixin,
    Extends,
    E,
    EE,
    PublicProps,
    ResolveProps<Props, E>,
    ExtractDefaultPropTypes<Props>,
    S
>;

// overload 3 (ASYNC): object format with array props declaration
// props inferred as { [key in PropNames]?: any }
// return type is for Vetur and TSX support
function register<
    PropNames extends string,
    RawBindings,
    D,
    C extends ComputedOptions = {},
    M extends MethodOptions = {},
    Mixin extends ComponentOptionsMixin = ComponentOptionsMixin,
    Extends extends ComponentOptionsMixin = ComponentOptionsMixin,
    E extends EmitsOptions = {},
    EE extends string = string,
    S extends SlotsType = {},
    I extends ComponentInjectOptions = {},
    II extends string = string,
    Props = Readonly<{ [key in PropNames]?: any }>,
>(
    componentName: string,
    componentConfiguration: () => Promise<
        ComponentOptionsWithArrayProps<PropNames, RawBindings, D, C, M, Mixin, Extends, E, EE, I, II, S>
    >,
): DefineComponent<
    Props,
    RawBindings,
    D,
    C,
    M,
    Mixin,
    Extends,
    E,
    EE,
    PublicProps,
    ResolveProps<Props, E>,
    ExtractDefaultPropTypes<Props>,
    S
>;

// overload 4: object format with object props declaration
// see `ExtractPropTypes` in ./componentProps.ts
function register<
    // the Readonly constraint allows TS to treat the type of { required: true }
    // as constant instead of boolean.
    PropsOptions extends Readonly<ComponentPropsOptions>,
    RawBindings,
    D,
    C extends ComputedOptions = {},
    M extends MethodOptions = {},
    Mixin extends ComponentOptionsMixin = ComponentOptionsMixin,
    Extends extends ComponentOptionsMixin = ComponentOptionsMixin,
    E extends EmitsOptions = {},
    EE extends string = string,
    S extends SlotsType = {},
    I extends ComponentInjectOptions = {},
    II extends string = string,
>(
    componentName: string,
    componentConfiguration: ComponentOptionsWithObjectProps<
        PropsOptions,
        RawBindings,
        D,
        C,
        M,
        Mixin,
        Extends,
        E,
        EE,
        I,
        II,
        S
    >,
): DefineComponent<
    PropsOptions,
    RawBindings,
    D,
    C,
    M,
    Mixin,
    Extends,
    E,
    EE,
    PublicProps,
    ResolveProps<PropsOptions, E>,
    ExtractDefaultPropTypes<PropsOptions>,
    S
>;

// overload 4 (ASYNC): object format with object props declaration
// see `ExtractPropTypes` in ./componentProps.ts
function register<
    // the Readonly constraint allows TS to treat the type of { required: true }
    // as constant instead of boolean.
    PropsOptions extends Readonly<ComponentPropsOptions>,
    RawBindings,
    D,
    C extends ComputedOptions = {},
    M extends MethodOptions = {},
    Mixin extends ComponentOptionsMixin = ComponentOptionsMixin,
    Extends extends ComponentOptionsMixin = ComponentOptionsMixin,
    E extends EmitsOptions = {},
    EE extends string = string,
    S extends SlotsType = {},
    I extends ComponentInjectOptions = {},
    II extends string = string,
>(
    componentName: string,
    componentConfiguration: Promise<
        ComponentOptionsWithObjectProps<PropsOptions, RawBindings, D, C, M, Mixin, Extends, E, EE, I, II, S>
    >,
): DefineComponent<
    PropsOptions,
    RawBindings,
    D,
    C,
    M,
    Mixin,
    Extends,
    E,
    EE,
    PublicProps,
    ResolveProps<PropsOptions, E>,
    ExtractDefaultPropTypes<PropsOptions>,
    S
>;

function register(componentName: string, componentConfiguration: unknown): unknown {
    if (!componentName || !componentName.length) {
        warn('ComponentFactory', 'A component always needs a name.', componentConfiguration);
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

    const configurationResolveMethod = async (): Promise<false | ComponentConfig> => {
        const awaitedConfig =
            typeof componentConfiguration === 'function'
                ? componentConfiguration
                : // @ts-expect-error - type is defined in overload
                  (): Promise<ComponentConfig> => Promise.resolve(componentConfiguration);

        // eslint-disable-next-line @typescript-eslint/no-unsafe-call
        let awaitedConfigResult = (await awaitedConfig()) as ComponentConfig;

        /**
         * Check if the resulted config is a ES module. Then we need to use the default
         * value of it.
         */
        if (awaitedConfigResult?.default) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            awaitedConfigResult = awaitedConfigResult.default;
        }

        const config = { ...awaitedConfigResult };

        config.name = componentName;

        if (config.template) {
            /**
             * Register the main template of the component.
             */
            TemplateFactory.registerComponentTemplate(componentName, config.template as string);

            /**
             * Delete the template string from the component config.
             * The complete rendered template including all overrides will be added later.
             */
            delete config.template;
        } else if (!config.functional && typeof config.render !== 'function' && !config._renderedBySfcTemplate) {
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

        const awaitedConfig =
            typeof componentConfiguration === 'function'
                ? componentConfiguration
                : (): Promise<ComponentConfig> => Promise.resolve(componentConfiguration);

        let awaitedConfigResult = await awaitedConfig();

        /**
         * Check if the resulted config is a ES module. Then we need to use the default
         * value of it.
         */
        if (awaitedConfigResult?.default) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            awaitedConfigResult = awaitedConfigResult.default;
        }

        config = { ...awaitedConfigResult };

        if (config.template) {
            /**
             * Register the main template of the component based on the extended component.
             */
            TemplateFactory.extendComponentTemplate(componentName, extendComponentName, config.template as string);

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
    componentConfiguration: ComponentConfig | (() => Promise<ComponentConfig>),
    overrideIndex: number | null = null,
): () => Promise<ComponentConfig> {
    let config: ComponentConfig;

    /**
     * For sync object configs the block index is populated here, before any
     * `<sw-block>` mounts. For async function configs it is populated inside
     * `configResolveMethod` when awaited — this relies on `initComponent()` being
     * called for all components before Vue mounts anything. If that boot order
     * changes, async Twig overrides will silently produce no output.
     */
    const isSyncWithTemplate =
        componentConfiguration !== null &&
        typeof componentConfiguration !== 'function' &&
        typeof componentConfiguration.template === 'string';

    if (isSyncWithTemplate) {
        indexTwigBlocksFromTemplate(componentName, componentConfiguration.template as string);
    }

    const configResolveMethod = async (): Promise<ComponentConfig> => {
        if (config) {
            return config;
        }

        const awaitedConfig =
            typeof componentConfiguration === 'function'
                ? componentConfiguration
                : (): Promise<ComponentConfig> => Promise.resolve(componentConfiguration);

        config = await awaitedConfig();

        /**
         * Check if the resulted config is a ES module. Then we need to use the default
         * value of it.
         */
        if (config?.default) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            config = config.default;
        }

        config.name = componentName;

        if (config.template) {
            // Async-only path: direct-object configs were already indexed synchronously
            // above so the block index is ready before any <sw-block> setup() runs.
            if (!isSyncWithTemplate) {
                indexTwigBlocksFromTemplate(componentName, config.template as string);
            }

            TemplateFactory.registerTemplateOverride(componentName, config.template as string, overrideIndex);

            // The merged template (default + all overrides) is compiled later by
            // TemplateFactory, so the raw string on the config object is no longer needed.
            delete config.template;
        }

        return config;
    };

    const overrides = overrideRegistry.get(componentName) || [];
    overrides.push({
        index: overrideIndex !== null ? overrideIndex : 0,
        config: configResolveMethod,
    });
    overrides.sort((a, b) => a.index - b.index);
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
        await Promise.all(asyncOverrideComponent.map((c) => c.config()));
    }
}

/**
 * Returns the complete component including extension and overrides.
 * @private
 */
async function build(componentName: string, skipTemplate = false): Promise<ComponentConfig | boolean> {
    const awaitedConfig = componentRegistry.get(componentName);

    if (!awaitedConfig) {
        throw new Error(`The component registry has not found a component with the name "${componentName}".`);
    }

    const resultConfig: ComponentConfig | boolean = await awaitedConfig();
    if (typeof resultConfig === 'boolean') {
        throw new Error(`The component registry could not build the component with the name "${componentName}".`);
    }

    // let config: ComponentConfig = Object.create(resultConfig) as ComponentConfig;
    let config: ComponentConfig = { ...resultConfig } as ComponentConfig;

    if (!config) {
        throw new Error(`The config of the component "${componentName}" is invalid.`);
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
        // Each override extends the config built so far, so Vue resolves every option topmost first.
        const resolvedEntries = await Promise.all(
            overrideRegistry.get(componentName)!.map((overrideEntry) => overrideEntry.config()),
        );

        resolvedEntries.forEach((overrideConfig) => {
            if (typeof overrideConfig === 'boolean') {
                return;
            }

            config = { ...overrideConfig, extends: config };
        });
    }

    config = withSuperChain(config);

    /**
     * if config has a render function it will ignore template
     */
    if (typeof config?.render === 'function' || config?._renderedBySfcTemplate) {
        delete config.template;
        delete config._renderedBySfcTemplate;
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
 * Original function behind each `$super`-aware wrapper, so a later build can wrap the original again.
 */
const originalOf = new WeakMap<SuperFunction, SuperFunction>();

/**
 * The `$super` method that `withSuperChain` adds to the top layer. Inner builds drop it when they are stacked again.
 */
const topLevelSupers = new WeakSet<SuperFunction>();

type SuperFunction = (this: object, ...args: any[]) => unknown;

type SuperOptions = Record<string, unknown>;

/**
 * A function found for a `$super` call and the index of the layer that defines it, 0 being the topmost layer.
 */
type SuperImplementation = { fn: SuperFunction; layer: number };

/**
 * Makes `this.$super(name, ...args)` call the next implementation of `name` below the calling layer of the
 * `extends` chain. Use it on the final config of `build()`. Functions that call `$super` run with a proxy of the
 * instance as `this` (`this.$.proxy` is the instance), which keeps their position in the chain per call, across `await`.
 *
 * @example
 * // base: label() returns 'base'; override: label() { return `override > ${this.$super('label')}`; }
 * withSuperChain(builtConfig); // vm.label() === 'override > base'
 */
function withSuperChain(top: ComponentConfig): ComponentConfig {
    const layers = extendsChainOf(top);
    let callsSuperAnywhere = false;

    // The layers never change after the build, so each lookup is resolved once.
    const implementations = new Map<string, SuperImplementation | null>();
    const findImplementation = (name: string, fromLayer: number): SuperImplementation | null => {
        const cacheKey = `${fromLayer}:${name}`;
        if (!implementations.has(cacheKey)) {
            implementations.set(cacheKey, searchImplementation(name, fromLayer));
        }

        return implementations.get(cacheKey)!;
    };

    function searchImplementation(name: string, fromLayer: number): SuperImplementation | null {
        const [key, accessor] = name.split('.');
        for (let layer = fromLayer; layer < layers.length; layer += 1) {
            const fn = ownAndMixinOptionsOf(layers[layer])
                .map(({ methods, computed }) =>
                    accessor ? accessorOf(computed, key, accessor) : (methods?.[key] ?? computed?.[key]),
                )
                .map(originalFunctionOf)
                .find(
                    (candidate): candidate is SuperFunction =>
                        typeof candidate === 'function' && !topLevelSupers.has(candidate as SuperFunction),
                );
            if (fn) {
                return { fn, layer };
            }
        }

        return null;
    }

    function callSuper(vm: object, callingLayer: number, name: string, args: unknown[]): unknown {
        if (name === '$super') {
            throw new Error('Don\'t call "$super" manually! This is not supported!');
        }

        const implementation = findImplementation(name, callingLayer + 1);
        if (!implementation) {
            throw new Error(`There was an error resolving the "$super" chain for method "${name}".`);
        }

        return implementation.fn.apply(thisForLayer(vm, implementation.layer), args);
    }

    function thisForLayer(vm: object, layer: number): object {
        return new Proxy(vm, {
            get(target, key) {
                if (key === '$super') {
                    return (name: string, ...args: unknown[]) => callSuper(target, layer, name, args);
                }

                return Reflect.get(target, key) as unknown;
            },
        });
    }

    const wrapForLayer = (value: unknown, layer: number): unknown => {
        const original = originalFunctionOf(value);
        if (typeof original !== 'function' || !/\.\$super/.test(original.toString())) {
            return original;
        }
        const superAware = original as SuperFunction;

        callsSuperAnywhere = true;
        const wrapped = function (this: object, ...args: unknown[]): unknown {
            return superAware.apply(thisForLayer(this, layer), args);
        };
        originalOf.set(wrapped, superAware);

        return wrapped;
    };

    const wrapOptionsForLayer = (options: SuperOptions, layer: number): SuperOptions => {
        const wrapped: SuperOptions = {};
        Object.entries(options).forEach(([key, value]) => {
            if (typeof value === 'function' && topLevelSupers.has(value as SuperFunction)) {
                return;
            }

            if (types.isPlainObject(value)) {
                const accessors = { ...(value as SuperOptions) };
                ['get', 'set'].forEach((accessor) => {
                    accessors[accessor] = wrapForLayer(accessors[accessor], layer);
                });
                wrapped[key] = accessors;
                return;
            }

            wrapped[key] = wrapForLayer(value, layer);
        });

        return wrapped;
    };

    const wrapLayer = (config: ComponentConfig, layer: number): ComponentConfig => {
        const wrapped: ComponentConfig = { ...config };
        if (config.methods) {
            wrapped.methods = wrapOptionsForLayer(config.methods as SuperOptions, layer);
        }
        if (config.computed) {
            wrapped.computed = wrapOptionsForLayer(config.computed as SuperOptions, layer);
        }
        if (Array.isArray(config.mixins)) {
            wrapped.mixins = config.mixins.map((mixin: unknown) =>
                types.isPlainObject(mixin) ? wrapLayer(mixin as ComponentConfig, layer) : mixin,
            );
        }

        return wrapped;
    };

    let rebuilt: ComponentConfig | undefined;
    for (let layer = layers.length - 1; layer >= 0; layer -= 1) {
        const wrapped = wrapLayer(layers[layer], layer);
        if (rebuilt) {
            wrapped.extends = rebuilt;
        }
        rebuilt = wrapped;
    }

    const result = rebuilt ?? top;
    if (callsSuperAnywhere) {
        // Called on the instance from outside a method, `$super(name)` skips the topmost implementation of `name`.
        const topLevelSuper = function (this: object, name: string, ...args: unknown[]): unknown {
            const topmost = findImplementation(name, 0);

            return callSuper(this, topmost ? topmost.layer : -1, name, args);
        };
        topLevelSupers.add(topLevelSuper);
        result.methods = { ...(result.methods as SuperOptions), $super: topLevelSuper };
    }

    return result;
}

/**
 * Returns the configs of the `extends` chain, topmost first.
 */
function extendsChainOf(top: ComponentConfig): ComponentConfig[] {
    const layers: ComponentConfig[] = [];
    for (
        let config: ComponentConfig | string | undefined = top;
        config && typeof config === 'object';
        config = config.extends
    ) {
        layers.push(config);
    }

    return layers;
}

/**
 * Returns the own options of a layer followed by its mixins, in the order Vue gives them precedence.
 */
function ownAndMixinOptionsOf(config: ComponentConfig): { methods?: SuperOptions; computed?: SuperOptions }[] {
    const mixins = Array.isArray(config.mixins) ? (config.mixins as ComponentConfig[]).slice().reverse() : [];

    return [config, ...mixins].map((options) => ({
        methods: options.methods as SuperOptions | undefined,
        computed: options.computed as SuperOptions | undefined,
    }));
}

/**
 * Returns the `get` or `set` function of a computed property definition, if it has one.
 */
function accessorOf(computed: SuperOptions | undefined, key: string, accessor: string): unknown {
    const definition = computed?.[key];

    return types.isPlainObject(definition) ? (definition as SuperOptions)[accessor] : undefined;
}

/**
 * Returns the function a `$super`-aware wrapper stands for, or the value itself.
 */
function originalFunctionOf(value: unknown): unknown {
    return typeof value === 'function' ? (originalOf.get(value as SuperFunction) ?? value) : value;
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
