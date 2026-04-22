import type { ComputedRef, Reactive, Ref, ToRefs } from 'vue';
import {
    computed,
    getCurrentInstance as vueGetCurrentInstance,
    isReactive,
    isReadonly,
    isRef,
    reactive,
    toRefs,
    watch,
} from 'vue';
import { syncRef } from '@vueuse/core';
import type { ComponentInternalInstance, SetupContext, PublicProps } from '@vue/runtime-core';
import { shouldActivateShim, convertOptionsApiOverrideToCompositionApi } from './options-composition-shim';
import type { OverrideFn } from './options-composition-shim';

/**
 * @experimental stableVersion:v6.8.0 feature:ADMIN_COMPOSITION_API_EXTENSION_SYSTEM
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
     * @experimental stableVersion:v6.8.0 feature:ADMIN_COMPOSITION_API_EXTENSION_SYSTEM
     *
     * This interface defines the public API mapping for each component that can be extended.
     * It will be used to get the correct types for the overrides and to ensure that the
     * overrides are compatible with the original component's public API.
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
 * @experimental stableVersion:v6.8.0 feature:ADMIN_COMPOSITION_API_EXTENSION_SYSTEM
 * Main function to extend the setup of a component
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
): ToRefs<Reactive<Exact<TSetupResult, ComponentPublicApiMapping[TComponentName]> & TPrivateSetupResult>> {
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

    const originalSetupResultPublic =
        originalSetupResultRaw.public ?? ({} as Exact<TSetupResult, ComponentPublicApiMapping[TComponentName]>);
    const originalSetupResultPrivate = originalSetupResultRaw.private ?? ({} as TPrivateSetupResult);

    // Merge public and private properties
    const originalSetupResult: Exact<TSetupResult, ComponentPublicApiMapping[TComponentName]> & TPrivateSetupResult = {
        ...originalSetupResultPublic,
        ...originalSetupResultPrivate,
    };

    // Check if any prop value was returned from the original setup
    Object.keys(options.props).forEach((key) => {
        if (Object.keys(originalSetupResult).includes(key)) {
            console.error(
                `[${options.name}] The original setup function for the originalComponent component returned a prop. This is not allowed. Props are only available for overrides with the second argument.`,
            );

            // Delete the prop values from the original setup result
            delete originalSetupResult[key];
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

    const overrides = _overridesMap[options.name];

    // Create a reactive wrapper for the original setup result
    const wrappedState = originalSetupResult;
    const reactiveWrappedState = reactive(wrappedState);

    // Keep track of applied overrides to avoid duplicates
    const appliedOverrides = reactive<OverrideFn[]>([]);

    // Function to apply overrides
    const applyOverrides = () => {
        overrides.forEach((override) => {
            // Skip if this override has already been applied
            if (appliedOverrides.includes(override)) {
                return;
            }

            /**
             *  Filter the wrappedState to only include public setup result
             *  and add the private ones in the "_private" property
             */
            type PreviousStateResultForExtensions = Exact<TSetupResult, ComponentPublicApiMapping[TComponentName]> & {
                _private: TPrivateSetupResult;
            };

            const wrappedStateAsRecord = wrappedState as Record<string, unknown>;
            const publicStateKeys = Object.keys(originalSetupResultPublic);

            const previousStateResultForExtensions = Object.keys(wrappedState).reduce<PreviousStateResultForExtensions>(
                (acc, key) => {
                    if (publicStateKeys.includes(key)) {
                        (acc as Record<string, unknown>)[key] = wrappedStateAsRecord[key];
                    }
                    return acc;
                },
                { _private: {} as TPrivateSetupResult } as PreviousStateResultForExtensions,
            );
            previousStateResultForExtensions._private = Object.keys(wrappedState).reduce<TPrivateSetupResult>((acc, key) => {
                if (!publicStateKeys.includes(key)) {
                    (acc as Record<string, unknown>)[key] = wrappedStateAsRecord[key];
                }
                return acc;
            }, {} as TPrivateSetupResult);

            // Apply the override with a destructured copy of the wrapped state to prevent calling himself
            let overrideResult: ReturnType<typeof override>;
            try {
                overrideResult = override({ ...previousStateResultForExtensions }, options.props, componentContext);
            } catch (e) {
                // Mark as applied to prevent infinite retry loops when subsequent overrides are added,
                // then re-throw so Vue's error handling (onErrorCaptured / app.config.errorHandler) takes over.
                appliedOverrides.push(override);
                throw e;
            }

            // Process each property in the override result
            Object.keys(overrideResult).forEach((key) => {
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
                    if (wrappedState[key] !== undefined && isRef(wrappedState[key])) {
                        // Handle normal ref values with 2-Way sync
                        syncRef(resultValue, wrappedState[key]);
                    } else {
                        // New property from override (e.g. Options API shim data), add directly
                        reactiveWrappedState[key] = resultValue;
                    }
                } else if (isReadonly(resultValue) && isRef(resultValue)) {
                    // Handle readonly computed values
                    reactiveWrappedState[key] = resultValue;
                    // @ts-expect-error - "effect" is part of a writable computed value
                } else if (!isReadonly(resultValue) && isRef(resultValue) && resultValue?.effect) {
                    // Handle writable computed values, create a new computed property with getter and setter
                    reactiveWrappedState[key] = computed({
                        get: () => resultValue.value,
                        set: (value) => {
                            resultValue.value = value;
                        },
                    });
                } else if (isReactive(resultValue)) {
                    // Check if new structure contains at least all keys of the old structure (nested)
                    const validationResult = checkNestedStructure({
                        oldObj: reactiveWrappedState[key] as Record<string, unknown>,
                        newObj: resultValue as Record<string, unknown>,
                        componentName: options.name as string,
                        path: key,
                    });

                    if (!validationResult.isValid) {
                        console.error(validationResult.error);
                        return;
                    }

                    // Assign reactive objects directly
                    Object.assign(reactiveWrappedState[key], resultValue);
                } else if (typeof resultValue === 'function') {
                    // Handle functions, assign directly
                    reactiveWrappedState[key] = resultValue;
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

    // Watch for changes in the overrides array and reapply overrides when changed
    watch(overrides, applyOverrides, { deep: true, immediate: true });

    return toRefs(reactiveWrappedState);
}

/**
 * Types for extracting the props of a component
 */
type InferComponentProps<T> = T extends new () => { $props: infer P } ? P : never;
type ExtractedProps<T> = Omit<
    {
        [key in keyof InferComponentProps<T>]: InferComponentProps<T>[key];
    },
    keyof PublicProps
>;

/**
 * @experimental stableVersion:v6.8.0 feature:ADMIN_COMPOSITION_API_EXTENSION_SYSTEM
 * Function to add an override for a specific component
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
