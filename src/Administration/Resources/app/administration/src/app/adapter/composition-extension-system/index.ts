/**
 * @sw-package framework
 *
 * Runtime of the composition extension system: applies the setup overrides registered for a component
 * while that component is set up.
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { Component, Reactive, Ref, SetupContext, ToRefs } from 'vue';
import {
    ErrorCodes,
    customRef,
    getCurrentInstance,
    handleError,
    isReactive,
    isReadonly,
    isRef,
    reactive,
    ref,
    shallowReactive,
    toRaw,
} from 'vue';
import type { ComponentInternalInstance, PublicProps } from '@vue/runtime-core';
import { syncRef } from '@vueuse/core';
import { getOptionsApiOverrides, isConvertedOptionsApiOverride } from '../options-composition-shim';
import type { OverrideFn } from '../options-composition-shim';
import { OVERRIDE_LOCAL_STATE_KEY, createLazyRefs, setScriptSetupDataScope } from './data-scope-helper';
import type { OverrideLocalState } from './data-scope-helper';

/** @private */
export { getScriptSetupDataScope } from './data-scope-helper';

declare global {
    /**
     * Maps an extendable component name to the shape of its public setup state. Augment it for your
     * component to type `overrideComponentSetup()` and `createExtendableSetup()`.
     */
    interface ComponentPublicApiMapping {
        [componentName: string]: { [key: string]: any };
    }
}

/**
 * @private
 */
export type ExtendableSetupState<TState extends object> = ToRefs<Reactive<TState>> & {
    readonly [OVERRIDE_LOCAL_STATE_KEY]: Ref<Reactive<OverrideLocalState>>;
};

type SetupState = Record<string, unknown>;

type Exact<T, Shape> = T extends Shape ? (Exclude<keyof T, keyof Shape> extends never ? T : never) : never;

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
 * Setup overrides per component name, keyed by the override file that registered them. Re-registering a
 * key replaces the entry in place, so a hot-reloaded override keeps its position.
 */
export const _overridesMap = new Map<string, Map<PropertyKey, OverrideFn>>();

const overrideComponents = shallowReactive<Component[]>([]);

const lateBinders = new WeakMap<object, (state: SetupState) => void>();

// Vue has no public `isComputed()`; `ref()` and `shallowRef()` share this prototype, computeds and custom refs don't.
const PLAIN_REF_PROTOTYPE = Object.getPrototypeOf(ref()) as object;

const isDevelopment = (): boolean => process.env.NODE_ENV !== 'production';

function registerOverride(componentName: string, fileKey: PropertyKey, override: OverrideFn): void {
    let overrides = _overridesMap.get(componentName);

    if (!overrides) {
        overrides = new Map();
        _overridesMap.set(componentName, overrides);
    }

    overrides.set(fileKey, override);
}

function createPreviousState(state: SetupState, publicKeys: Set<string>): SetupState {
    const privateState: SetupState = {};
    const previousState: SetupState = { _private: privateState };

    Object.keys(state).forEach((key) => {
        (publicKeys.has(key) ? previousState : privateState)[key] = state[key];
    });

    return previousState;
}

function findMissingKey(base: object, replacement: object, path: string): string | null {
    for (const [key, value] of Object.entries(base)) {
        const currentPath = `${path}.${key}`;
        const replacementValue = (replacement as SetupState)[key];

        if (!Object.hasOwn(replacement, key)) {
            return currentPath;
        }

        if (value && typeof value === 'object' && replacementValue && typeof replacementValue === 'object') {
            const missingKey = findMissingKey(value as object, replacementValue, currentPath);

            if (missingKey) {
                return missingKey;
            }
        }
    }

    return null;
}

function mergeOverrideValue(
    componentName: string,
    state: SetupState,
    key: string,
    value: unknown,
    allowsNewKeys: boolean,
): void {
    const current = state[key];

    if (value === current) {
        return;
    }

    if (!(key in state)) {
        if (isDevelopment() && !allowsNewKeys) {
            console.warn(`[${componentName}] The override returns "${key}", which the component does not provide.`);
        }
    } else if (isRef(current) && !isReadonly(current)) {
        if (isRef(value) && !isReadonly(value) && Object.getPrototypeOf(value) === PLAIN_REF_PROTOTYPE) {
            syncRef(value, current);
            return;
        }

        if (!isRef(value)) {
            current.value = value;
            return;
        }
    } else if (isDevelopment() && isReactive(current) && isReactive(value)) {
        const missingKey = findMissingKey(current as object, value as object, key);

        if (missingKey) {
            console.warn(`[${componentName}] The override of "${key}" does not contain "${missingKey}".`);
        }
    }

    state[key] = value;
}

/**
 * Builds the setup state of an extendable component and applies every override registered for it, in
 * registration order and before anything reads the state.
 */
function applyOverrides(
    componentName: string,
    publicState: SetupState,
    privateState: SetupState,
    props: SetupState,
    context: SetupContext | undefined,
): SetupState {
    const state: SetupState = { ...publicState, ...privateState };
    const overrideLocalState = reactive<OverrideLocalState>({});
    const publicKeys = new Set(Object.keys(publicState));
    const instance = getCurrentInstance();

    if (isDevelopment()) {
        const collisions = Object.keys(state).filter((key) => Object.hasOwn(props, key));

        if (collisions.length > 0) {
            throw new Error(
                `[${componentName}] Setup bindings must not share a name with a prop: "${collisions.join('", "')}".`,
            );
        }
    }

    Object.defineProperty(state, OVERRIDE_LOCAL_STATE_KEY, { value: overrideLocalState, enumerable: false });

    const apply = (override: OverrideFn) => {
        let result: SetupState;

        try {
            result = override(createPreviousState(state, publicKeys), props, context);
        } catch (error) {
            // One failing override must not keep the others from applying. Vue's handler still reports it.
            if (!instance) {
                throw error;
            }

            handleError(error, instance, ErrorCodes.SETUP_FUNCTION);
            return;
        }

        Object.entries(result).forEach(([key, value]) => {
            if (key === OVERRIDE_LOCAL_STATE_KEY) {
                Object.assign(overrideLocalState, value);
            } else if (Object.hasOwn(props, key)) {
                console.error(
                    `[${componentName}] Override result value not working. Cannot override props. Following prop should be changed: "${key}"`,
                );
            } else {
                mergeOverrideValue(componentName, state, key, value, isConvertedOptionsApiOverride(override));
            }
        });
    };

    _overridesMap.get(componentName)?.forEach(apply);
    getOptionsApiOverrides(componentName).forEach(apply);

    return reactive(state);
}

function getSetupContext(instance: ComponentInternalInstance | null): SetupContext | undefined {
    return (
        (instance as (ComponentInternalInstance & { setupContext: SetupContext | null }) | null)?.setupContext ?? undefined
    );
}

/**
 * Creates the object behind `late(...)`: one getter per key that returns `fallbacks[key]()` until
 * `attach()` binds it, and the final, override-aware binding afterwards.
 */
function late(fallbacks: Record<string, () => unknown>): Record<string, unknown> {
    let boundState: SetupState | null = null;
    const bindings: Record<string, unknown> = {};

    Object.keys(fallbacks).forEach((key) => {
        Object.defineProperty(bindings, key, {
            enumerable: true,
            get: () => (boundState && key in boundState ? boundState[key] : fallbacks[key]()),
        });
    });

    lateBinders.set(bindings, (state) => {
        boundState = state;
    });

    return bindings;
}

/**
 * @private
 *
 * Entry point of a compiled native setup component: applies the overrides to the finished bindings of the
 * author's `<script setup>` body and returns one lazy ref per binding.
 */
export function attachOverrides(options: {
    name: string;
    public?: SetupState;
    private?: SetupState;
    late?: Record<string, unknown>;
}): ExtendableSetupState<SetupState> {
    const instance = getCurrentInstance();
    const state = applyOverrides(
        options.name,
        options.public ?? {},
        options.private ?? {},
        (instance?.props ?? {}) as SetupState,
        getSetupContext(instance),
    );

    if (options.late) {
        lateBinders.get(options.late)?.(toRaw(state));
    }

    if (instance) {
        setScriptSetupDataScope(instance, state);
    }

    return createLazyRefs(state) as ExtendableSetupState<SetupState>;
}

/**
 * @private
 *
 * Returns one read-only ref per prop of the current instance, so that a parent holding a template ref
 * keeps reading props off a compiled native setup component.
 */
export function getExposedProps(): Record<string, Ref<unknown>> {
    const props = (getCurrentInstance()?.props ?? {}) as SetupState;
    const exposedProps: Record<string, Ref<unknown>> = {};

    Object.keys(props).forEach((key) => {
        exposedProps[key] = customRef(() => ({
            get: () => props[key],
            set: () => {
                if (isDevelopment()) {
                    console.warn(`The prop "${key}" is exposed read-only. Pass a new value from the parent instead.`);
                }
            },
        }));
    });

    return exposedProps;
}

/**
 * @private
 *
 * Not a public entry point: authors write native setup SFCs, whose generated code calls
 * `__setupRuntime.v1`. It stays on the `Shopware.Component` global for hand-written extendable components.
 *
 * Makes the setup of a Composition API component extendable. Call it from `setup()` and return its result.
 *
 * `originalSetup` returns the component's bindings split into `public` (the extension API, typed through
 * `ComponentPublicApiMapping`) and `private` (reachable by overrides under `previousState._private`). Every
 * override registered with `overrideComponentSetup()` and every Options API override of the component is
 * applied before the bindings are returned as refs.
 *
 * Pass `context` if `setup()` declares only the `props` parameter, because Vue then creates no setup context.
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
    const componentName = options.name as string;
    const instance = getCurrentInstance();
    const context = options.context ?? (getSetupContext(instance) as TContext);
    const setupResult = originalSetup(options.props, context);

    if (!setupResult.public && !setupResult.private) {
        throw new Error(
            `[${componentName}] The original setup function for the originalComponent component must return at least one public or private property.`,
        );
    }

    if (Object.keys(setupResult).some((key) => key !== 'public' && key !== 'private')) {
        console.error(
            `[${componentName}] The original setup function for the originalComponent component returned an unexpected value. Only public and private properties at first level are allowed.`,
        );
    }

    const state = applyOverrides(
        componentName,
        (setupResult.public ?? {}) as SetupState,
        (setupResult.private ?? {}) as SetupState,
        options.props,
        context as SetupContext | undefined,
    );

    if (instance) {
        setScriptSetupDataScope(instance, state);
    }

    return createLazyRefs(state) as unknown as ExtendableSetupState<
        Exact<TSetupResult, ComponentPublicApiMapping[TComponentName]> & TPrivateSetupResult
    >;
}

/**
 * @private
 *
 * Not a public entry point: authors write `.override.vue` files, whose generated code registers through
 * `__setupRuntime.v1.override`.
 *
 * Registers a setup override for an extendable component. The override receives the component's state
 * after all earlier overrides (public keys at the top level, private keys under `_private`), its props and
 * its setup context, and returns the bindings it replaces. It applies to every instance set up afterwards.
 *
 * Curried so that the original component can be passed as a type argument while the name is inferred:
 * `overrideComponentSetup<typeof SwFoo>()('sw-foo', (previousState, props) => ({ … }))`.
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
        registerOverride(componentName as string, Symbol(componentName as string), override as unknown as OverrideFn);
    };
}

/**
 * @private
 *
 * Components that must be mounted once so their setup registers block content, rendered hidden by `sw-admin`.
 */
export function registerOverrideComponent(component: Component): void {
    if (!overrideComponents.includes(component)) {
        overrideComponents.push(component);
    }
}

/**
 * @private
 */
export function getOverrideComponents(): Component[] {
    return overrideComponents;
}

/**
 * @private
 *
 * Versioned interface for compiled native setup SFCs. Built plugin bundles call it, so a `v1` member must
 * keep its signature; incompatible changes go into a new version.
 */
export const setupRuntime = Object.freeze({
    v1: Object.freeze({
        attach: attachOverrides,
        expose: getExposedProps,
        late,
        override: registerOverride,
        registerComponent: registerOverrideComponent,
        getComponents: getOverrideComponents,
    }),
});
