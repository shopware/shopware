import type { ComputedRef, Ref } from 'vue';
import {
    computed,
    getCurrentInstance as vueGetCurrentInstance,
    getCurrentScope,
    onScopeDispose,
    reactive,
    watch,
} from 'vue';
import { publishOverrideState } from './publish-override-state';
import type { ComponentInternalInstance, SetupContext, PublicProps } from '@vue/runtime-core';
import { synchronizeLegacyOverrides, type Registration } from './legacy-overrides';
import type { OverrideFn } from '../options-composition-shim';
import {
    createDataScope,
    createOverrideLocalState,
    exposeOverrideLocalState,
    isOverrideLocalStateKey,
    setDataScopeForInstance,
} from './data-scope-helper';
import type { ExtendableSetupState } from './data-scope-helper';

/** @private */
export { getScriptSetupDataScope, createBlockDataScope } from './data-scope-helper';

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
/* eslint-disable @typescript-eslint/no-explicit-any */
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
    privateKeys: string[],
): PreviousStateForOverride<TPublicState, TPrivateState> => {
    const setupStateAsRecord = setupState as Record<string, unknown>;
    const publicStateKeys = Object.keys(publicState);

    return getOverrideVisibleStateKeys(setupState).reduce<PreviousStateForOverride<TPublicState, TPrivateState>>(
        (previousState, key) => {
            if (publicStateKeys.includes(key) || !privateKeys.includes(key)) {
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

    const instance = getCurrentInstance();
    const definition = instance?.type as
        | { __swLegacyRegistrations?: Registration[]; __swLegacyNames?: string[] }
        | undefined;
    const legacyOverrides = reactive<OverrideFn[]>([]);
    const names = definition?.__swLegacyNames ?? [options.name as string];
    const synchronize = () => {
        const prepared = definition?.__swLegacyRegistrations;
        const registrations = prepared ? [...prepared] : [];
        names.forEach((name) => {
            for (const entry of Shopware.Component.getOverrideRegistry?.().get(name) ?? []) {
                if (!registrations.includes(entry)) registrations.push(entry);
            }
        });
        synchronizeLegacyOverrides(options.name as string, legacyOverrides, registrations);
    };
    synchronize();
    names.forEach((name) => {
        const unsubscribe = Shopware.Component.subscribeToOverrides?.(name, synchronize);
        if (unsubscribe && getCurrentScope()) onScopeDispose(unsubscribe);
    });
    const nativeOverrides = names.map((name) => (_overridesMap[name] ??= reactive([])));
    const registeredOverrides = computed(() => [
        ...legacyOverrides,
        ...nativeOverrides.flat(),
    ]);

    // Create a reactive wrapper for the original setup result
    const reactiveSetupState = reactive(setupState);

    const owner = {
        instance,
        scope: getCurrentScope(),
        state: reactiveSetupState,
        privateKeys: new Set(Object.keys(privateSetupState)),
        data: reactive({}),
        options: { ...(instance?.proxy?.$options ?? {}) },
    };

    // Keep track of applied overrides to avoid duplicates
    const appliedOverrides = reactive<OverrideFn[]>([]);

    // Function to apply overrides
    const applyOverrides = () => {
        registeredOverrides.value.forEach((override) => {
            // Skip if this override has already been applied
            if (appliedOverrides.includes(override)) {
                return;
            }

            const previousStateForOverride = createPreviousStateForOverride<
                Exact<TSetupResult, ComponentPublicApiMapping[TComponentName]>,
                TPrivateSetupResult
            >(setupState, publicSetupState, Object.keys(privateSetupState));

            // Apply the override with a destructured copy of the wrapped state to prevent calling himself
            let overrideResult: ReturnType<typeof override>;
            const layerOwner = { ...owner, initializing: true };
            try {
                overrideResult = override({ ...previousStateForOverride }, options.props, componentContext, layerOwner);
            } catch (e) {
                // Mark as applied to prevent infinite retry loops when subsequent overrides are added,
                // then re-throw so Vue's error handling (onErrorCaptured / app.config.errorHandler) takes over.
                appliedOverrides.push(override);
                throw e;
            }

            publishOverrideState({
                componentName: options.name as string,
                props: options.props,
                result: overrideResult,
                rawState: setupState,
                state: reactiveSetupState,
                instance,
            });

            layerOwner.initializing = false;

            // Mark this override as applied
            appliedOverrides.push(override);
        });
    };

    // Overrides registered after mount are applied from inside this watcher, where no effect scope
    // is active — watchers and computeds they create would outlive the component. Re-enter the
    // owning scope so Vue disposes them on unmount.
    const ownerScope = getCurrentScope();

    watch(registeredOverrides, ownerScope ? () => ownerScope.run(applyOverrides) : applyOverrides, {
        immediate: true,
    });

    const state = createDataScope<Exact<TSetupResult, ComponentPublicApiMapping[TComponentName]> & TPrivateSetupResult>(
        reactiveSetupState,
    );

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
