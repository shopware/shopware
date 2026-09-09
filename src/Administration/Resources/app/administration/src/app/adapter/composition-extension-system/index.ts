import type { ComputedRef, Ref } from 'vue';
import {
    computed,
    getCurrentInstance as vueGetCurrentInstance,
    getCurrentScope,
    isReactive,
    isReadonly,
    isRef,
    reactive,
    watch,
} from 'vue';
import { syncRef } from '@vueuse/core';
import type { ComponentInternalInstance, SetupContext, PublicProps } from '@vue/runtime-core';
import { shouldActivateShim, convertOptionsApiOverrideToCompositionApi } from '../options-composition-shim';
import type { OverrideFn } from '../options-composition-shim';
import {
    createDataScope,
    createOverrideLocalState,
    exposeOverrideLocalState,
    getOverrideLocalState,
    isOverrideLocalStateKey,
    mergeOverrideState,
    setDataScopeForInstance,
} from './data-scope-helper';
import type { ExtendableSetupState, OverrideLocalState } from './data-scope-helper';

/** @private */
export { getScriptSetupDataScope } from './data-scope-helper';

/**
 * @private
 * @sw-package framework
 *
 * Extendable Setup Utility for Vue Components
 *
 * This file provides a utility for extending the setup function of Vue components
 * in a flexible and dynamic way. It allows for runtime modifications to
 * component behavior without directly altering the original component code.
 *
 * Key features:
 * 1. Dynamic Component Extension: Allows adding new functionality or overriding existing
 *    behavior of Vue components at runtime.
 * 2. Non-Invasive Modifications: Original components remain unchanged, with extensions
 *    applied through a wrapping mechanism.
 * 3. Reactive Overrides: Uses Vue's reactivity system to ensure that overrides are
 *    reactive and stay in sync with the component's state.
 * 4. Multiple Override Types: Supports various types of overrides including refs, computed
 *    properties, reactive objects, and functions.
 *
 * Main functions:
 * - extendableSetup: Wraps a component's setup function to make it extendable.
 * - overrideComponentSetup: Adds an override for a specific component.
 */

// Disable ESLint rules for this file due to the use of 'any' types and potentially unsafe operations
/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-assignment */
declare global {
    /**
     * @private
     *
     * Maps an extendable component name to the shape of its public setup state, so the extension
     * system's own signatures can name it.
     *
     * Not an author-facing surface: the index-signature fallback below resolves every real component to
     * `{ [key: string]: any }`, so hand-declaring an entry is the only way to get any checking - and
     * generated code never passes the type argument that would use it. Per-SFC types will be generated
     * from the native setup transform instead, at which point this goes away.
     */
    interface ComponentPublicApiMapping {
        _internal_test_component: {
            baseValue: Ref<number, number>;
            multipliedValue: ComputedRef<number>;
            addedValue: ComputedRef<number>;
            title: Ref<string, string>;
        };
        // Fallback for untyped components

        [componentName: string]: { [key: string]: any };
    }
}

/**
 * Extends Vue's ComponentInternalInstance with the setupContext property,
 * which is available at runtime during the setup function but not exposed in Vue's public types.
 */
type ComponentInstanceWithSetupContext = ComponentInternalInstance & {
    setupContext: SetupContext;
};

/**
 * Typed wrapper around Vue's getCurrentInstance that includes the setupContext property.
 * Use this instead of Vue's getCurrentInstance when you need access to setupContext.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function getCurrentInstance(): ComponentInstanceWithSetupContext | null {
    return vueGetCurrentInstance() as ComponentInstanceWithSetupContext | null;
}

/**
 * @private
 * Create a reactive map to store overrides for each component
 */
export const _overridesMap: {
    [componentName: string]: Array<OverrideFn>;
} = reactive({});

/**
 * @private
 * Function to check if the new structure contains at least all keys of the old structure (nested)
 */
const checkNestedStructure = <
    TOld extends Record<string, unknown>,
    TNew extends Partial<Record<keyof TOld, unknown>> & Record<string, unknown>,
>({
    oldObj,
    newObj,
    path = '',
    componentName,
}: {
    oldObj: TOld;
    newObj: TNew;
    path?: string;
    componentName: string;
}): {
    isValid: boolean;
    error: string | null;
} => {
    let result: {
        isValid: boolean;
        error: string | null;
    } = { isValid: true, error: null };

    for (const key of Object.keys(oldObj)) {
        const currentPath = path ? `${path}.${key}` : key;

        if (!Object.prototype.hasOwnProperty.call(newObj, key)) {
            result = {
                isValid: false,
                error: `[${componentName}] Override value not working. New structure does not contain key: ${currentPath}`,
            };
            break;
        }

        if (
            typeof oldObj[key] === 'object' &&
            oldObj[key] !== null &&
            typeof newObj[key] === 'object' &&
            newObj[key] !== null
        ) {
            // Recursively check nested objects
            const nestedResult = checkNestedStructure({
                oldObj: oldObj[key] as Record<string, unknown>,
                newObj: newObj[key] as Record<string, unknown>,
                path: currentPath,
                componentName,
            });

            if (!nestedResult.isValid) {
                result = nestedResult;
                break;
            }
        }
    }

    return result;
};

const getComponentContext = (): SetupContext => {
    const instance = getCurrentInstance();

    return (
        instance?.setupContext ??
        ({
            attrs: instance?.attrs,
            slots: instance?.slots,
            emit: instance?.emit,
            expose: () => {
                console.error('expose is not available in the current context');
            },
        } as SetupContext)
    );
};

/**
 * This utility type is used to require the the exact shape of a type.
 */
type Exact<T, Shape> = T extends Shape ? (Exclude<keyof T, keyof Shape> extends never ? T : never) : never;

/**
 * Describes the state shape passed to an override callback.
 *
 * Public setup values stay at the top level, while private setup values are grouped under `_private`.
 *
 * @example
 * override(({ headline, _private }) => ({ headline: ref(`${headline.value}!`) }));
 */
type PreviousStateForOverride<TPublicState extends object, TPrivateState extends object> = TPublicState & {
    _private: TPrivateState;
};

/**
 * Lists setup-state keys that should be visible to override callbacks.
 *
 * Use this before building the previous-state snapshot so hidden override-local fields stay internal.
 *
 * @example
 * const keys = getOverrideVisibleStateKeys(setupState);
 */
const getOverrideVisibleStateKeys = (state: object): string[] => {
    return Object.keys(state).filter((key) => !isOverrideLocalStateKey(key));
};

/**
 * Builds the previous-state snapshot passed to one override callback.
 *
 * This filters setup state to only include the public setup result at the top level, adds private setup values under
 * `_private`, and leaves hidden override-local fields out of the callback payload.
 *
 * @example
 * const previousState = createPreviousStateForOverride(setupState, publicSetupState);
 */
const createPreviousStateForOverride = <TPublicState extends object, TPrivateState extends object>(
    setupState: TPublicState & TPrivateState,
    publicState: TPublicState,
): PreviousStateForOverride<TPublicState, TPrivateState> => {
    const setupStateAsRecord = setupState as Record<string, unknown>;
    const publicStateKeys = Object.keys(publicState);

    return getOverrideVisibleStateKeys(setupState).reduce<PreviousStateForOverride<TPublicState, TPrivateState>>(
        (previousState, key) => {
            if (publicStateKeys.includes(key)) {
                (previousState as Record<string, unknown>)[key] = setupStateAsRecord[key];
                return previousState;
            }

            (previousState._private as Record<string, unknown>)[key] = setupStateAsRecord[key];
            return previousState;
        },
        { _private: {} as TPrivateState } as PreviousStateForOverride<TPublicState, TPrivateState>,
    );
};

/**
 * @private
 *
 * Creates the runtime setup wrapper used by compiled base setup components.
 *
 * Not a public entry point: authors write native setup SFCs and the compiler pass emits the calls into
 * this module for them. Reachable on the `Shopware.Component` global only because generated code has to
 * resolve it at runtime.
 *
 * The wrapper separates public and private setup state, applies all registered Composition API and
 * Options API shim overrides once, and returns a data scope that `sw-block` can read during slot
 * rendering.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function createExtendableSetup<
    TProps extends Record<string, unknown>,
    TContext,
    TComponentName extends keyof ComponentPublicApiMapping,
    TSetupResult extends ComponentPublicApiMapping[TComponentName],
    TPrivateSetupResult extends object,
>(
    options: {
        name: TComponentName;
        props: TProps;
        context?: TContext;
    },
    originalSetup: (
        props: TProps,
        context: TContext,
    ) => {
        public?: Exact<TSetupResult, ComponentPublicApiMapping[TComponentName]>;
        private?: TPrivateSetupResult;
    },
): ExtendableSetupState<Exact<TSetupResult, ComponentPublicApiMapping[TComponentName]> & TPrivateSetupResult> {
    const componentContext = options.context ? options.context : (getComponentContext() as TContext);
    // Call the original setup function
    const originalSetupResultRaw = originalSetup(options.props, componentContext);

    // Stop execution and throw an error if the original setup function does not return a public or private property
    if (!originalSetupResultRaw.public && !originalSetupResultRaw.private) {
        throw new Error(
            `[${options.name}] The original setup function for the originalComponent component must return at least one public or private property.`,
        );
    }

    // Check if any other return value was returned from the original setup
    Object.keys(originalSetupResultRaw).forEach((key) => {
        if (key !== 'public' && key !== 'private') {
            console.error(
                `[${options.name}] The original setup function for the originalComponent component returned an unexpected value. Only public and private properties at first level are allowed.`,
            );
        }
    });

    const publicSetupState =
        originalSetupResultRaw.public ?? ({} as Exact<TSetupResult, ComponentPublicApiMapping[TComponentName]>);
    const privateSetupState = originalSetupResultRaw.private ?? ({} as TPrivateSetupResult);

    // Merge public and private properties
    const setupState: Exact<TSetupResult, ComponentPublicApiMapping[TComponentName]> & TPrivateSetupResult = {
        ...publicSetupState,
        ...privateSetupState,
    };
    const overrideLocalState = createOverrideLocalState();
    exposeOverrideLocalState(setupState, overrideLocalState);

    // Check if any prop value was returned from the original setup
    Object.keys(options.props).forEach((key) => {
        if (Object.keys(setupState).includes(key)) {
            console.error(
                `[${options.name}] The original setup function for the originalComponent component returned a prop. This is not allowed. Props are only available for overrides with the second argument.`,
            );

            // Delete the prop values from the original setup result
            delete setupState[key];
        }
    });

    if (!_overridesMap[options.name]) {
        _overridesMap[options.name] = reactive([]);
    }

    // Process pending overrides from the component factory override registry.
    // This is the single canonical path for routing Options API overrides through the shim.
    // Plugins always register overrides (via Shopware.Component.override()) before the Vue
    // application mounts, so all pending overrides are present in the registry at this point.
    void (async () => {
        try {
            const overrideRegistry = Shopware?.Component?.getOverrideRegistry?.();
            if (!overrideRegistry) {
                // Shopware global not available (e.g. in unit tests that don't bootstrap the app)
                return;
            }

            if (overrideRegistry.has(options.name as string)) {
                const pendingOverrides = overrideRegistry.get(options.name as string)!;
                await Promise.all(
                    pendingOverrides.map(async (pendingOverride) => {
                        const resolvedConfig = await pendingOverride.config();
                        if (typeof resolvedConfig !== 'boolean' && shouldActivateShim(resolvedConfig)) {
                            const compositionOverride = convertOptionsApiOverrideToCompositionApi(
                                options.name as string,
                                resolvedConfig,
                            );
                            _overridesMap[options.name].push(compositionOverride);
                        }
                    }),
                );
            }
        } catch (e) {
            console.error(`[Options API Shim] Failed to process pending overrides for "${options.name as string}":`, e);
        }
    })();

    const registeredOverrides = _overridesMap[options.name];

    // Create a reactive wrapper for the original setup result
    const reactiveSetupState = reactive(setupState);

    // Keep track of applied overrides to avoid duplicates
    const appliedOverrides = reactive<OverrideFn[]>([]);

    // Function to apply overrides
    const applyOverrides = () => {
        registeredOverrides.forEach((override) => {
            // Skip if this override has already been applied
            if (appliedOverrides.includes(override)) {
                return;
            }

            const previousStateForOverride = createPreviousStateForOverride<
                Exact<TSetupResult, ComponentPublicApiMapping[TComponentName]>,
                TPrivateSetupResult
            >(setupState, publicSetupState);

            // Apply the override with a destructured copy of the wrapped state to prevent calling himself
            let overrideResult: ReturnType<typeof override>;
            try {
                overrideResult = override({ ...previousStateForOverride }, options.props, componentContext);
            } catch (e) {
                // Mark as applied to prevent infinite retry loops when subsequent overrides are added,
                // then re-throw so Vue's error handling (onErrorCaptured / app.config.errorHandler) takes over.
                appliedOverrides.push(override);
                throw e;
            }

            // Process each property in the override result
            Object.keys(overrideResult).forEach((key) => {
                if (isOverrideLocalStateKey(key)) {
                    mergeOverrideState(getOverrideLocalState(reactiveSetupState), overrideResult[key] as OverrideLocalState);
                    return;
                }

                // Skip if the key is a prop, as props should not be overridden
                if (Object.keys(options.props).includes(key)) {
                    console.error(
                        `[${options.name}] Override result value not working. Cannot override props. Following prop should be changed: "${key}"`,
                    );
                    return;
                }
                const resultValue = overrideResult[key];

                if (
                    !isReadonly(resultValue) &&
                    isRef(resultValue) &&
                    // @ts-expect-error - "effect" is not part of the Ref type
                    !resultValue?.effect
                ) {
                    if (setupState[key] !== undefined && isRef(setupState[key])) {
                        // Handle normal ref values with 2-Way sync
                        syncRef(resultValue, setupState[key]);
                    } else {
                        // New property from override (e.g. Options API shim data), add directly
                        reactiveSetupState[key] = resultValue;
                    }
                } else if (isReadonly(resultValue) && isRef(resultValue)) {
                    // Handle readonly computed values
                    reactiveSetupState[key] = resultValue;
                    // @ts-expect-error - "effect" is part of a writable computed value
                } else if (!isReadonly(resultValue) && isRef(resultValue) && resultValue?.effect) {
                    // Handle writable computed values, create a new computed property with getter and setter
                    reactiveSetupState[key] = computed({
                        get: () => resultValue.value,
                        set: (value) => {
                            resultValue.value = value;
                        },
                    });
                } else if (isReactive(resultValue)) {
                    // Check if new structure contains at least all keys of the old structure (nested)
                    const validationResult = checkNestedStructure({
                        oldObj: reactiveSetupState[key] as Record<string, unknown>,
                        newObj: resultValue as Record<string, unknown>,
                        componentName: options.name as string,
                        path: key,
                    });

                    if (!validationResult.isValid) {
                        console.error(validationResult.error);
                        return;
                    }

                    // Assign reactive objects directly
                    Object.assign(reactiveSetupState[key], resultValue);
                } else if (typeof resultValue === 'function') {
                    // Handle functions, assign directly
                    reactiveSetupState[key] = resultValue;
                } else {
                    // Log an error for unhandled types
                    console.error(
                        `[${options.name}] Override value not working. No handling declared for:`,
                        key,
                        resultValue,
                    );
                }
            });

            // Mark this override as applied
            appliedOverrides.push(override);
        });
    };

    // Overrides registered after mount are applied from inside this watcher, where no effect scope
    // is active — watchers and computeds they create would outlive the component. Re-enter the
    // owning scope so Vue disposes them on unmount.
    const ownerScope = getCurrentScope();

    watch(registeredOverrides, ownerScope ? () => ownerScope.run(applyOverrides) : applyOverrides, {
        deep: true,
        immediate: true,
    });

    const state = createDataScope<Exact<TSetupResult, ComponentPublicApiMapping[TComponentName]> & TPrivateSetupResult>(
        reactiveSetupState,
    );

    const instance = getCurrentInstance();

    if (instance) {
        setDataScopeForInstance(instance, state);
    }

    return state;
}

/**
 * Extracts runtime component props without Vue's framework-level public props.
 */
type InferComponentProps<T> = T extends new () => { $props: infer P } ? P : never;
type ExtractedProps<T> = Omit<
    {
        [key in keyof InferComponentProps<T>]: InferComponentProps<T>[key];
    },
    keyof PublicProps
>;

/**
 * @private
 *
 * Registers a setup override callback for one extendable component.
 *
 * Generated override SFCs call this during their hidden component setup so the base component can
 * apply replacement bindings when its own extendable setup wrapper runs.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function overrideComponentSetup<TOriginalComponent>() {
    return function <TComponentName extends keyof ComponentPublicApiMapping>(
        componentName: TComponentName,
        override: (
            previousState: ComponentPublicApiMapping[TComponentName],
            props: ExtractedProps<TOriginalComponent>,
            context: SetupContext,
        ) => ReturnType<OverrideFn>,
    ): void {
        // Initialize the overrides array for this component if it doesn't exist
        if (!_overridesMap[componentName]) {
            _overridesMap[componentName] = reactive([]);
        }

        // Cast required: typed generics → internal OverrideFn (parameter types are contravariant)
        _overridesMap[componentName].push(override as unknown as OverrideFn);
    };
}

/**
 * @private
 *
 * Returns the current component's props as read-only refs, keyed by prop name.
 *
 * The generated `defineExpose()` of a base component spreads these in front of its swDefinePublic()
 * bindings, so a parent holding a template ref reads props off the child exactly as it did before the
 * component was lowered. Computeds rather than plain values, because `defineExpose()` receives its
 * object once while props keep changing; readonly because a prop belongs to the parent that passes it.
 */
export function getExposedProps(): Record<string, ComputedRef<unknown>> {
    const props = (getCurrentInstance()?.props ?? {}) as Record<string, unknown>;
    const exposedProps: Record<string, ComputedRef<unknown>> = {};

    Object.keys(props).forEach((key) => {
        exposedProps[key] = computed(() => props[key]);
    });

    return exposedProps;
}

/**
 * @private
 *
 * Hooks the override machinery into an already-executed native
 * `<script setup>` body. The author's code runs natively (no hoisting, macros in place); the
 * generated footer passes the finished bindings here, and this delegates to createExtendableSetup()
 * with a callback that just returns them - all override application, previous-state, effect-scope,
 * and data-scope semantics are reused unchanged.
 *
 * The props object handed to override callbacks is read from the current instance, so the generated
 * footer never has to thread a props binding through (and destructured `defineProps()` works too).
 */
export function attachOverrides<TComponentName extends keyof ComponentPublicApiMapping>(options: {
    name: TComponentName;
    public?: Record<string, unknown>;
    private?: Record<string, unknown>;
}): ExtendableSetupState<Record<string, unknown>> {
    const props = (getCurrentInstance()?.props ?? {}) as Record<string, unknown>;

    // No `context` is threaded through: createExtendableSetup() falls back to getComponentContext(),
    // and the generated footer has no context binding to pass anyway.
    return createExtendableSetup(
        {
            name: options.name,
            props: props as never,
        },
        () =>
            ({
                public: options.public ?? {},
                private: options.private ?? {},
            }) as never,
    );
}
