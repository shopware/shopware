/** @sw-package framework */
import { computed, isReactive, isReadonly, isRef, type Ref, type ComponentInternalInstance } from 'vue';
import { syncRef } from '@vueuse/core';
import {
    isOverrideLocalStateKey,
    mergeOverrideState,
    getOverrideLocalState,
    type OverrideLocalState,
} from './data-scope-helper';

type StatePublication = {
    componentName: string;
    props: Record<string, unknown>;
    result: Record<string, unknown>;
    rawState: Record<string, unknown>;
    state: Record<string, unknown>;
    instance: ComponentInternalInstance | null;
};

/**
 * Publish one override's bindings while retaining refs and reactive object identity used by the base.
 * New bindings also need Vue proxy accessors when an override arrives after the first render.
 * @private
 */
export function publishOverrideState(publication: StatePublication): void {
    const { componentName, props, result, rawState, state, instance } = publication;
    for (const key of Object.keys(result)) {
        if (isOverrideLocalStateKey(key)) {
            mergeOverrideState(getOverrideLocalState(state), result[key] as OverrideLocalState);
            continue;
        }
        if (Object.keys(props).includes(key)) {
            console.error(
                `[${componentName}] Override result value not working. Cannot override props. Following prop should be changed: "${key}"`,
            );
            continue;
        }
        const isNewField = !Object.hasOwn(rawState, key);
        if (!publishBinding(publication, key, isNewField)) continue;
        if (isNewField && instance?.proxy && instance.isMounted) exposeLateBinding(instance.proxy, state, key);
    }
}

function publishBinding(publication: StatePublication, key: string, isNewField: boolean): boolean {
    const { componentName, result, rawState, state } = publication;
    const value = result[key];
    if (isRef(value)) {
        publishRef(state, rawState, key, value);
    } else if (isReactive(value)) {
        const previous = state[key];
        if (isNewField || previous === null || typeof previous !== 'object') {
            state[key] = value;
        } else {
            const missing = findMissingPath(previous as Record<string, unknown>, value as Record<string, unknown>, key);
            if (missing) {
                console.error(
                    `[${componentName}] Override value not working. New structure does not contain key: ${missing}`,
                );
                return false;
            }
            Object.assign(previous, value);
        }
    } else if (typeof value === 'function') {
        state[key] = value;
    } else {
        console.error(`[${componentName}] Override value not working. No handling declared for:`, key, value);
    }
    return true;
}

function publishRef(
    state: Record<string, unknown>,
    rawState: Record<string, unknown>,
    key: string,
    value: Ref<unknown>,
): void {
    // Vue's writable computed refs carry an effect; ordinary refs must retain the existing two-way link.
    if (!isReadonly(value) && !(value as Ref<unknown> & { effect?: unknown }).effect) {
        const previous = rawState[key];
        if (isRef(previous)) syncRef(value, previous);
        else state[key] = value;
    } else if (isReadonly(value)) {
        state[key] = value;
    } else {
        state[key] = computed({
            get: () => value.value,
            set: (next) => {
                value.value = next;
            },
        });
    }
}

function exposeLateBinding(proxy: object, state: Record<string, unknown>, key: string): void {
    // Vue caches missing bindings on first render. An explicit accessor makes late additions visible.
    Object.defineProperty(proxy, key, {
        configurable: true,
        enumerable: true,
        get: () => state[key],
        set: (value: unknown) => {
            state[key] = value;
        },
    });
}

function findMissingPath(
    previous: Record<string, unknown>,
    replacement: Record<string, unknown>,
    path: string,
    visited = new WeakMap<object, WeakSet<object>>(),
): string | null {
    const replacements = visited.get(previous) ?? new WeakSet<object>();
    if (replacements.has(replacement)) return null;
    replacements.add(replacement);
    visited.set(previous, replacements);

    for (const key of Object.keys(previous)) {
        const currentPath = path ? `${path}.${key}` : key;
        if (!Object.hasOwn(replacement, key)) return currentPath;
        const before = previous[key];
        const after = replacement[key];
        if (before !== null && typeof before === 'object' && after !== null && typeof after === 'object') {
            const missing = findMissingPath(
                before as Record<string, unknown>,
                after as Record<string, unknown>,
                currentPath,
                visited,
            );
            if (missing) return missing;
        }
    }
    return null;
}
