/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-empty-object-type, @typescript-eslint/no-explicit-any */
import { error, warn } from 'src/core/service/utils/debug.utils';
import types from 'src/core/service/utils/types.utils';
import TemplateFactory from 'src/core/factory/template.factory';
import { indexTwigBlocksFromTemplate } from 'src/core/factory/twig-block-index';
import { silenceKey } from 'src/core/feature';
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

/**
 * @private
 * Added to native setup SFCs by the Vite 8 watcher: `ShopwareSetupHmrMeta` of the shopware-setup Vite plugin,
 * plus the hot data of their module.
 */
export type SetupHmrMetadata = {
    componentName: string;
    mode: 'base' | 'override';
    version: string;
    data: {
        reloadPage?: boolean;
        instances?: Set<unknown>;
    };
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export interface ComponentConfig extends ComponentOptions {
    functional?: boolean;
    extends?: ComponentConfig | string;
    _isOverride?: boolean;
    _renderedBySfcTemplate?: boolean;
    component?: Promise<ComponentConfig | boolean>;
    loading?: ComponentConfig;
    delay?: number;
    name?: string;
    /** @private */
    __shopwareSetupHmr?: SetupHmrMetadata;
}

/**
 * Registry which holds all components
 * @private
 */
type AwaitedComponentConfig = () => Promise<ComponentConfig | boolean>;
const componentRegistry = new Map<string, AwaitedComponentConfig>();

/**
 * @private
 */
export type IndexedAwaitedComponentConfig = {
    index: number;
    config: AwaitedComponentConfig;
    /** The majors `Feature.silence()` silenced while the override was registered, empty in production. */
    silencedMajors?: string[];
};
/**
 * Registry which holds all component overrides
 * @private
 */
const overrideRegistry = new Map<string, IndexedAwaitedComponentConfig[]>();

/**
 * What `extend()` registered for a component, read by `build()` to report the deprecated members of the parent that
 * the component redefines.
 */
type ExtensionRegistration = {
    /** The majors `Feature.silence()` silenced while the component was registered, empty in production. */
    silencedMajors: string[];
    /** The own `computed` and methods of the component, copied before `build()` adds the inherited ones to them. */
    ownMembers?: Pick<ComponentConfig, 'computed' | 'methods'>;
};
const extensionRegistry = new Map<string, ExtensionRegistration>();

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
          // eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents
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
    const registration: ExtensionRegistration = {
        silencedMajors: process.env.NODE_ENV === 'production' ? [] : Shopware.Feature.getSilencedMajors(),
    };

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
        if (process.env.NODE_ENV !== 'production') {
            registration.ownMembers = {
                computed: { ...(config.computed as ComputedOptions) },
                methods: { ...(config.methods as MethodOptions) },
            };
        }

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
    extensionRegistry.set(componentName, registration);

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
        silencedMajors: process.env.NODE_ENV === 'production' ? [] : Shopware.Feature.getSilencedMajors(),
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
 * The Vue HMR runtime can only swap a native setup SFC as it is.
 * Once the factory composed it with other components, a script edit needs a page reload to compose it again.
 */
function requirePageReloadOnHotUpdate(config: ComponentConfig): void {
    if (process.env.NODE_ENV === 'production') {
        return;
    }

    if (config.__shopwareSetupHmr) {
        config.__shopwareSetupHmr.data.reloadPage = true;
    }
}

function requirePageReloadIfComposed(componentName: string, config: ComponentConfig): void {
    if (process.env.NODE_ENV === 'production') {
        return;
    }

    if (overrideRegistry.has(componentName) || config.extends || config.mixins?.length) {
        requirePageReloadOnHotUpdate(config);
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

    requirePageReloadIfComposed(componentName, resultConfig);

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
            reportRedefinedDeprecatedMembers(componentName, extendComp, extensionRegistry.get(componentName));
            requirePageReloadOnHotUpdate(extendComp);
            enrichSuperChain(extendComp, config);

            config.extends = extendComp;
        } else {
            delete config.extends;
        }
    }

    if (overrideRegistry.has(componentName)) {
        // Resolve all override configs in parallel, then separate by type
        const overrideEntries = overrideRegistry.get(componentName)!;
        const resolvedEntries = await Promise.all(overrideEntries.map((overrideEntry) => overrideEntry.config()));

        reportOverriddenDeprecatedMembers(componentName, config, overrideEntries, resolvedEntries);

        const standardOverrideConfigs: AwaitedComponentConfig[] = resolvedEntries.map(
            (resolvedConfig) => () =>
                Promise.resolve(typeof resolvedConfig === 'boolean' ? resolvedConfig : copyOverrideConfig(resolvedConfig)),
        );

        // Continue with standard Options API overrides
        if (standardOverrideConfigs.length > 0) {
            const convertedOverrides = await convertOverrides(standardOverrideConfigs, config);

            convertedOverrides.forEach((overrideComp) => {
                overrideComp.extends = config;
                overrideComp._isOverride = true;
                config = { ...overrideComp };
            });
        }
    }

    const superRegistry = buildSuperRegistry(config);
    if (isNotEmptyObject(superRegistry) && config) {
        const inheritedFrom = isAnOverride(config)
            ? `#${componentName}`
            : typeof config.extends !== 'string' && config?.extends?.name;

        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        config.methods = {
            ...config.methods,
            // @ts-expect-error
            ...addSuperBehaviour(inheritedFrom, superRegistry),
        };
    }

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
 * The resolved config of an override is cached and shared by every build of its component, but `convertOverrides` and
 * `enrichSuperChain` move and add properties and methods. Each build therefore works on its own copy, so that a later
 * build cannot take a method or prop away from a component that an earlier build already returned.
 */
function copyOverrideConfig(overrideConfig: ComponentConfig): ComponentConfig {
    const copy = copyOwnProperties(overrideConfig);
    Reflect.ownKeys(copy).forEach((key) => {
        const descriptor = Object.getOwnPropertyDescriptor(copy, key);
        if (descriptor && 'value' in descriptor && types.isPlainObject(descriptor.value)) {
            Object.defineProperty(copy, key, {
                ...descriptor,
                value: copyOwnProperties(descriptor.value as object),
            });
        }
    });

    return copy;
}

/**
 * Unlike a spread, this keeps getters unevaluated and keeps non-enumerable properties.
 */
function copyOwnProperties<T extends object>(object: T): T {
    return Object.create(Object.getPrototypeOf(object) as object | null, Object.getOwnPropertyDescriptors(object)) as T;
}

/**
 * Reorganizes the structure of the given overrides.
 */
async function convertOverrides(
    awaitedOverrides: AwaitedComponentConfig[] | undefined,
    config: ComponentConfig,
): Promise<ComponentConfig[]> {
    if (!awaitedOverrides) {
        return [];
    }

    // Await all override configs into one array
    const overrides = await Promise.all(
        awaitedOverrides.map((awaitedOverride) => {
            return awaitedOverride();
        }),
    );

    /**
     * Merge and sort the overrides from latest to first.
     * Copy over previous override properties if they don't exist.
     */
    /* eslint-disable @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-assignment */
    // @ts-expect-error
    const sortedOverrides: ComponentConfig[] = overrides.reduceRight((acc, overrideComp) => {
        // @ts-expect-error
        if (acc.length === 0) {
            return [overrideComp];
        }

        // @ts-expect-error
        const previous = acc.shift();

        Object.entries(overrideComp).forEach(([prop, values]) => {
            // check if current property exists in previous override
            if (previous && previous.hasOwnProperty(prop)) {
                // if it exists iterate over the methods
                // and hoist them if they don't exists in previous override

                // only hoist between plain objects, Vue merges arrays, functions and null through the extends chain.
                // Hoisting object keys into an array (e.g. inject, props) would drop them.
                if (!types.isPlainObject(values) || !types.isPlainObject(previous[prop])) {
                    return;
                }

                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                Object.entries(values).forEach(([methodName, methodFunction]) => {
                    if (!previous[prop].hasOwnProperty(methodName)) {
                        // move the function over
                        previous[prop][methodName] = methodFunction;
                        // @ts-expect-error
                        delete overrideComp[prop][methodName];
                    }
                });
            } else {
                // move the property over
                previous[prop] = values;
                // @ts-expect-error
                delete overrideComp[prop];
            }
        });

        // eslint-disable-next-line @typescript-eslint/no-unsafe-return
        return [
            ...[overrideComp],
            previous,
            // @ts-expect-error
            ...acc,
        ];
    }, []);

    /**
     * For every override check if it contains every base computed or method.
     * If a computed or method is missing, a placeholder function will be injected which simply calls $super.
     * This assures a consecutive $super chain later in the process.
     */
    sortedOverrides.forEach((sortedOverride) => {
        enrichSuperChain(config, sortedOverride);
    });

    return sortedOverrides;
    /* eslint-enable @typescript-eslint/no-unsafe-member-access */
}

interface SuperRegistry {
    [name: string]: {
        [sName: string]: {
            parent: string;
            func: ((args: $TSFixMe[]) => $TSFixMe) | null;
        };
    };
}

interface SuperBehavior {
    $super(name: string, ...args: $TSFixMe[]): $TSFixMe;
    _initVirtualCallStack(name: string): void;
    _findInSuperRegister(name: string): SuperRegistry;
    _superRegistry(): SuperRegistry;
    _inheritedFrom(): string;
    _virtualCallStack: { [name: string]: string | undefined };
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
                // regular computed or function
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
    methodOrComputed: 'methods' | 'computed',
    config: ComponentConfig,
): SuperRegistry {
    const superCallPattern = /\.\$super/g;
    const methodString = typeof method === 'function' && method.toString();
    const hasSuperCall = methodString && superCallPattern.test(methodString);

    // requested method has no super call return
    if (!hasSuperCall) {
        return superRegistry;
    }

    // method is not in super registry create empty object
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
    return {
        $super(this: SuperBehavior, name, ...args) {
            // this is to prevent this.$super('$super') call's
            if (name === '$super') {
                throw new Error('Don\'t call "$super" manually! This is not supported!');
            }

            // prepare the call stack for the current function name
            this._initVirtualCallStack(name);

            const superStack = this._findInSuperRegister(name);
            const superFuncObject = superStack[this._virtualCallStack[name]!];
            if (!superFuncObject) {
                // This should no longer happen but a precise error is better than a call stack exception
                throw new Error(`There was an error resolving the "$super" chain for method "${name}".`);
            }

            // eslint-disable-next-line @typescript-eslint/no-base-to-string
            if (!superFuncObject?.func?.toString()?.includes('$super')) {
                this._virtualCallStack[name] = undefined;

                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-return
                return superFuncObject.func.bind(this)(...args);
            }

            // @ts-expect-error
            this._virtualCallStack[name] = superFuncObject.parent;

            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
            return superFuncObject.func.bind(this)(...args);
        },
        _initVirtualCallStack(name) {
            // _virtualCallStack is added to the instance by virtual-call-stack.plugin.ts

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
}

/**
 * Resolves the super call chain for a given method (or computed property).
 */
function resolveSuperCallChain(
    config: ComponentConfig,
    methodName: string,
    methodsOrComputed: 'methods' | 'computed' = 'methods',
    overridePrefix = '',
): $TSFixMe {
    const extension = config.extends;

    if (!extension || typeof extension === 'string') {
        return {};
    }

    const parentName = `${overridePrefix}${extension.name ?? ''}`;
    let parentsParentName =
        typeof extension.extends === 'object' && extension.extends
            ? `${overridePrefix}${extension.extends.name ?? ''}`
            : null;

    if (parentName === parentsParentName) {
        // If we are dealing with an override move up one level by prepending another #
        if (overridePrefix.length > 0 || extension._isOverride) {
            overridePrefix = `#${overridePrefix}`;
        }

        const extendsName =
            extension && extension.extends && typeof extension.extends !== 'string' && extension.extends.name;
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

    // Early return if the current method does no longer contain a $super call
    const superCallPattern = /\.\$super/g;
    if (typeof methodFunction === 'function' && !superCallPattern.test((methodFunction as () => any).toString())) {
        // @ts-expect-error  - We know this exists
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        parentBlock[parentName].parent = null;

        return parentBlock;
    }

    const resolvedParent = resolveSuperCallChain(extension, methodName, methodsOrComputed, overridePrefix);
    return {
        ...resolvedParent,
        ...parentBlock,
    };
}

/**
 * This function adds $super calls for every missing computed or method from baseConfig to targetConfig.
 * Iterating all computed and methods from baseConfig. Adding a dummy function calling $super is inserted
 * if targetConfig doesn't specifically override the method or computed already.
 */
function enrichSuperChain(baseConfig: ComponentConfig, targetConfig: ComponentConfig) {
    ['computed', 'methods'].forEach((methodOrComputed) => {
        // base component has no computed or methods
        if (!baseConfig.hasOwnProperty(methodOrComputed)) {
            return;
        }

        const methodsOrComputed = baseConfig[methodOrComputed];
        // base component computed or methods are empty
        if (!methodsOrComputed) {
            return;
        }

        // computed or methods does not exist on override create empty object
        if (!targetConfig.hasOwnProperty(methodOrComputed)) {
            targetConfig[methodOrComputed] = {};
        }

        // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
        Object.entries(methodsOrComputed).forEach(([key, method]) => {
            // override specifically overrides the current method or computed? Abort!
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            if (targetConfig[methodOrComputed].hasOwnProperty(key)) {
                return;
            }

            // is computed getter/setter definition
            if (methodOrComputed === 'computed' && typeof method === 'object') {
                // Create computed as empty object
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                targetConfig[methodOrComputed][key] = {};

                // Fill object with super calls
                Object.entries(method as object).forEach(([cmd]) => {
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                    targetConfig[methodOrComputed][key][cmd] = function (...args: $TSFixMe) {
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-return
                        return this.$super(`${key}.${cmd}`, ...args);
                    };
                });
            } else {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                targetConfig[methodOrComputed][key] = function (...args: $TSFixMe) {
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-return,@typescript-eslint/no-unsafe-member-access
                    return this.$super(key, ...args);
                };
            }
        });
    });
}

/**
 * The leading `triggerDeprecationOrThrow` call of a deprecated member, which `require-deprecation-guard` enforces.
 * `message` is `null` when the guard does not pass it as a plain string literal.
 */
type DeprecationGuard = {
    flag: string;
    message: string | null;
};

/**
 * Which accessor of a member a function implements: `null` for a method, `get` or `set` for a computed.
 */
type MemberAccessor = 'get' | 'set' | null;

/**
 * A method, a computed or one of its accessors, of which only the source is read.
 */
type MemberFunction = (...args: never[]) => unknown;

/**
 * Matches the leading guard at the start of a function body, with its flag and its message when that is a string literal.
 */
const LEADING_GUARD_PATTERN =
    /^\s*[\w$.]+\.triggerDeprecationOrThrow\(\s*(['"`])(\w+)\1\s*(?:,\s*(['"`])((?:\\.|(?!\3)[^\\])*)\3\s*[,)])?/;

/**
 * Matches the counters Istanbul puts at the start of every function when Jest collects coverage, like
 * `cov_1a().f[17]++; cov_1a().s[38]++;` with their `istanbul ignore next` comment, so the guard after them is still
 * found in CI.
 */
const LEADING_COVERAGE_COUNTERS =
    /^(?:\s*\/\*\s*istanbul ignore next\s*\*\/|\s*cov_\w+\(\)\.[bfs]\[\d+\](?:\[\d+\])?\+\+;)*/;

/**
 * A deprecation reported for a member that an override or an extending component redefines.
 */
type MemberDeprecation = {
    flag: string;
    message: string;
};

/**
 * A deprecated `computed` or method of a base config that another config redefines.
 */
type RedefinedMember = {
    kind: 'computed' | 'method';
    name: string;
    guard: DeprecationGuard;
};

/**
 * Reports every override of `componentName` that overrides a deprecated `computed` or method of `baseConfig`, with
 * or without `this.$super()`. The member's own guard only runs when the member is called, which can be behind a
 * click, while the override stops working once the member is removed either way. An override registered inside
 * `Feature.silence()` of the member's major is not reported, so an extension can keep a backward compatible override
 * for older versions.
 * Development only: production builds drop it.
 * `overrideConfigs` are the resolved configs of `overrideEntries`, in the same order.
 */
function reportOverriddenDeprecatedMembers(
    componentName: string,
    baseConfig: ComponentConfig,
    overrideEntries: IndexedAwaitedComponentConfig[],
    overrideConfigs: (ComponentConfig | boolean)[],
): void {
    if (process.env.NODE_ENV === 'production') {
        return;
    }

    const deprecations = overrideConfigs.flatMap((overrideConfig, index) => {
        if (typeof overrideConfig === 'boolean') {
            return [];
        }

        return redefinedDeprecatedMembers(baseConfig, overrideConfig, overrideEntries[index].silencedMajors ?? []).map(
            ({ kind, name, guard }) => ({
                flag: guard.flag,
                message: `Component.override('${componentName}') overrides the deprecated ${kind} "${name}", so the override stops working once "${name}" is removed with ${guard.flag}.${guardMessage(kind, guard)}`,
            }),
        );
    });

    reportMemberDeprecations(deprecations);
}

/**
 * Reports every deprecated `computed` or method of `parentConfig` that the component `componentName`, registered with
 * `Component.extend()`, redefines. The redefinition keeps the name of a member the parent removes, and overrides of
 * the component rely on it, so it needs a new name or a deprecation of its own. A redefinition with its own guard is
 * not reported, and neither is one registered inside `Feature.silence()` of the member's major.
 * Development only: production builds drop it.
 */
function reportRedefinedDeprecatedMembers(
    componentName: string,
    parentConfig: ComponentConfig,
    registration: ExtensionRegistration | undefined,
): void {
    if (process.env.NODE_ENV === 'production' || !registration?.ownMembers) {
        return;
    }

    const ownMembers = registration.ownMembers;
    const deprecations = redefinedDeprecatedMembers(parentConfig, ownMembers, registration.silencedMajors)
        .filter(({ kind, name }) => !isDeprecatedItself(kind, ownMembers, name))
        .map(({ kind, name, guard }) => ({
            flag: guard.flag,
            message: `Component.extend('${componentName}', '${parentConfig.name ?? ''}') redefines the deprecated ${kind} "${name}", which is removed with ${guard.flag}. Rename it or deprecate it as well.${guardMessage(kind, guard)}`,
        }));

    reportMemberDeprecations(deprecations);
}

/**
 * Triggers each deprecation. Once its major is active, it logs an error instead of throwing, as a throw would keep
 * the whole component from rendering, while the redefinition only stops working with the major.
 */
function reportMemberDeprecations(deprecations: MemberDeprecation[]): void {
    deprecations.forEach(({ flag, message }) => {
        try {
            Shopware.Feature.triggerDeprecationOrThrow(flag, message);
        } catch (deprecation) {
            // eslint-disable-next-line sw-deprecation-rules/no-manual-deprecation-notices -- reports the thrown guard
            error('Deprecation', (deprecation as Error).message);
        }
    });
}

/**
 * The deprecated `computed` and methods of `baseConfig` that `config` redefines, except the ones of a major in
 * `silencedMajors`.
 */
function redefinedDeprecatedMembers(
    baseConfig: ComponentConfig,
    config: Pick<ComponentConfig, 'computed' | 'methods'>,
    silencedMajors: string[],
): RedefinedMember[] {
    return (['computed', 'methods'] as const).flatMap((section) => {
        const members = (config[section] ?? {}) as Record<string, unknown>;
        const kind = section === 'methods' ? 'method' : 'computed';

        return Object.entries(members).flatMap(([name, member]) => {
            const guard = overriddenDeprecationGuard(baseConfig, section, name, member);

            if (!guard || silencedMajors.includes(silenceKey(guard.flag))) {
                return [];
            }

            return [{ kind, name, guard }];
        });
    });
}

/**
 * Whether the member `name` of `config` calls its own deprecation guard.
 */
function isDeprecatedItself(
    kind: RedefinedMember['kind'],
    config: Pick<ComponentConfig, 'computed' | 'methods'>,
    name: string,
): boolean {
    const section = kind === 'method' ? 'methods' : 'computed';
    const member = (config[section] as Record<string, unknown> | undefined)?.[name];

    return memberAccessors(section, member).some(([, func]) => isMemberFunction(func) && !!leadingDeprecationGuard(func));
}

/**
 * The message of the member's own guard, appended to the reported deprecation.
 */
function guardMessage(kind: RedefinedMember['kind'], guard: DeprecationGuard): string {
    return guard.message ? ` The ${kind} is deprecated with: ${guard.message}` : '';
}

/**
 * The guard of the deprecated base implementation that `member` overrides, or `null`.
 */
function overriddenDeprecationGuard(
    baseConfig: ComponentConfig,
    section: 'computed' | 'methods',
    name: string,
    member: unknown,
): DeprecationGuard | null {
    for (const [accessor, func] of memberAccessors(section, member)) {
        if (isMemberFunction(func)) {
            const guard = inheritedDeprecationGuard(baseConfig, section, name, accessor);

            if (guard) {
                return guard;
            }
        }
    }

    return null;
}

/**
 * The functions a member consists of, each with the accessor it implements.
 *
 * @example
 * memberAccessors('computed', { get() {}, set(value) {} }); // [['get', get], ['set', set]]
 */
function memberAccessors(section: 'computed' | 'methods', member: unknown): [MemberAccessor, unknown][] {
    if (section === 'methods') {
        return [[null, member]];
    }

    if (typeof member === 'function') {
        return [['get', member]];
    }

    if (!types.isPlainObject(member)) {
        return [];
    }

    const accessors = member as { get?: unknown; set?: unknown };

    return [
        ['get', accessors.get],
        ['set', accessors.set],
    ];
}

/**
 * Follows the `$super` chain of a member from `config` up to the first implementation that does not call `$super`,
 * and returns the guard of the first guarded implementation on the way.
 */
function inheritedDeprecationGuard(
    config: ComponentConfig,
    section: 'computed' | 'methods',
    name: string,
    accessor: MemberAccessor,
): DeprecationGuard | null {
    let level: ComponentConfig | undefined = config;

    while (level) {
        const func = accessorOf(level, section, name, accessor);

        if (isMemberFunction(func)) {
            const guard = leadingDeprecationGuard(func);

            if (guard) {
                return guard;
            }

            if (!callsSuper(func)) {
                return null;
            }
        }

        level = typeof level.extends === 'object' ? level.extends : undefined;
    }

    return null;
}

/**
 * The function that implements `accessor` of the member `name` in `config` itself, without its `extends`.
 */
function accessorOf(
    config: ComponentConfig,
    section: 'computed' | 'methods',
    name: string,
    accessor: MemberAccessor,
): unknown {
    const member = (config[section] as Record<string, unknown> | undefined)?.[name];

    return memberAccessors(section, member).find(([memberAccessor]) => memberAccessor === accessor)?.[1];
}

/**
 * Narrows a member value to a function, so its source can be read.
 */
function isMemberFunction(value: unknown): value is MemberFunction {
    return typeof value === 'function';
}

/**
 * Whether a member function calls `this.$super()`, the same check the `$super` chain uses.
 */
function callsSuper(func: MemberFunction): boolean {
    return /\.\$super/.test(func.toString());
}

/**
 * The guard a deprecated member calls as its first statement, read from its source, or `null` when it has none.
 *
 * @example
 * leadingDeprecationGuard(function () { Shopware.Feature.triggerDeprecationOrThrow('V6_8_0_0', 'x is deprecated.'); });
 * // { flag: 'V6_8_0_0', message: 'x is deprecated.' }
 */
function leadingDeprecationGuard(func: MemberFunction): DeprecationGuard | null {
    const source = func.toString();
    const bodyStart = functionBodyStart(source);
    const body = bodyStart === -1 ? null : source.slice(bodyStart);
    const match = body === null ? null : LEADING_GUARD_PATTERN.exec(body.replace(LEADING_COVERAGE_COUNTERS, ''));

    if (!match) {
        return null;
    }

    const [
        ,
        ,
        flag,
        messageQuote,
        rawMessage,
    ] = match;
    const isPlainLiteral = rawMessage !== undefined && !(messageQuote === '`' && rawMessage.includes('${'));

    return {
        flag,
        message: isPlainLiteral ? rawMessage.replace(/\\(.)/g, '$1') : null,
    };
}

/**
 * The index right after the `{` that opens the body of a function source, or `-1` for an arrow function without a
 * block body. It skips the parameter list by its parentheses, as a destructured parameter contains braces too.
 *
 * @example
 * functionBodyStart('d(r,{foo:e}={}){return e}'); // 16
 */
function functionBodyStart(source: string): number {
    let depth = 0;

    for (let index = source.indexOf('('); index !== -1 && index < source.length; index += 1) {
        if (source[index] === '(') {
            depth += 1;
        } else if (source[index] === ')') {
            depth -= 1;
        }

        if (depth === 0) {
            const opening = /^\s*(?:=>\s*)?\{/.exec(source.slice(index + 1));

            return opening ? index + 1 + opening[0].length : -1;
        }
    }

    return -1;
}

/**
 * Returns a method in the extension chain object.
 */
function findMethodInChain(
    extension: ComponentConfig,
    methodName: string,
    methodsOrComputed: 'methods' | 'computed',
): $TSFixMe {
    const splitPath = methodName.split('.');

    if (splitPath.length > 1) {
        return resolveGetterSetterChain(extension, splitPath, methodsOrComputed);
    }

    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
    if (extension[methodsOrComputed]?.[methodName]) {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        return extension[methodsOrComputed][methodName];
    }

    return null;
}

/**
 * Returns a method in the extension chain object with a method path like `getterSetterMethod.get`.
 */
function resolveGetterSetterChain(
    extension: ComponentConfig,
    path: string[],
    methodsOrComputed: 'methods' | 'computed',
): $TSFixMe {
    const [methodName, cmd] = path;

    if (!extension[methodsOrComputed]) {
        // @ts-expect-error
        return findMethodInChain(extension.extends, methodName, methodsOrComputed);
    }

    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
    if (!extension[methodsOrComputed][methodName]) {
        // @ts-expect-error
        return findMethodInChain(extension.extends, methodName, methodsOrComputed);
    }

    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
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
    return Object.keys(obj).length !== 0 && obj.constructor === Object;
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
