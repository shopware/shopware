/** @sw-package framework */
import { computed, isReactive, isReadonly, isRef, type Ref, type ComponentInternalInstance } from 'vue';
import { syncRef } from '@vueuse/core';
import {
    isOverrideLocalStateKey,
    mergeOverrideState,
    getOverrideLocalState,
    type OverrideLocalState,
} from './data-scope-helper';

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
    visited = new WeakMap<object, WeakSet<object>>(),
}: {
    visited?: WeakMap<object, WeakSet<object>>;
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

    let replacements = visited.get(oldObj);
    if (replacements?.has(newObj)) return result;
    if (!replacements) {
        replacements = new WeakSet();
        visited.set(oldObj, replacements);
    }
    replacements.add(newObj);

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
                visited,
            });

            if (!nestedResult.isValid) {
                result = nestedResult;
                break;
            }
        }
    }

    return result;
};

/**
 * Publish one override's bindings while retaining refs and reactive object identity used by the base.
 * New bindings also need Vue proxy accessors when an override arrives after the first render.
 * @private
 */
export function publishOverrideState({
    componentName,
    props,
    result,
    rawState,
    state,
    instance,
}: {
    componentName: string;
    props: Record<string, unknown>;
    result: Record<string, unknown>;
    rawState: Record<string, unknown>;
    state: Record<string, unknown>;
    instance: ComponentInternalInstance | null;
}): void {
    Object.keys(result).forEach((key) => {
        if (isOverrideLocalStateKey(key)) {
            mergeOverrideState(getOverrideLocalState(state), result[key] as OverrideLocalState);
            return;
        }

        // Skip if the key is a prop, as props should not be overridden
        if (Object.keys(props).includes(key)) {
            console.error(
                `[${componentName}] Override result value not working. Cannot override props. Following prop should be changed: "${key}"`,
            );
            return;
        }
        const isNewField = !Object.hasOwn(rawState, key);
        const resultValue = result[key];

        if (
            !isReadonly(resultValue) &&
            isRef(resultValue) &&
            // @ts-expect-error - "effect" is not part of the Ref type
            !resultValue?.effect
        ) {
            if (rawState[key] !== undefined && isRef(rawState[key])) {
                // Handle normal ref values with 2-Way sync
                syncRef(resultValue, rawState[key] as Ref);
            } else {
                // Vue caches missing instance bindings on first render. Late additions also need
                // a context accessor so that cache does not hide the newly available setup field.
                state[key] = resultValue;
            }
        } else if (isReadonly(resultValue) && isRef(resultValue)) {
            // Handle readonly computed values
            state[key] = resultValue;
            // @ts-expect-error - "effect" is part of a writable computed value
        } else if (!isReadonly(resultValue) && isRef(resultValue) && resultValue?.effect) {
            // Handle writable computed values, create a new computed property with getter and setter
            state[key] = computed({
                get: () => resultValue.value,
                set: (value) => {
                    resultValue.value = value;
                },
            });
        } else if (isReactive(resultValue)) {
            if (isNewField || state[key] === null || typeof state[key] !== 'object') {
                state[key] = resultValue;
            } else {
                // Check if new structure contains at least all keys of the old structure (nested)
                const validationResult = checkNestedStructure({
                    oldObj: state[key] as Record<string, unknown>,
                    newObj: resultValue as Record<string, unknown>,
                    componentName: componentName,
                    path: key,
                });

                if (!validationResult.isValid) {
                    console.error(validationResult.error);
                    return;
                }

                // Assign reactive objects directly
                Object.assign(state[key], resultValue);
            }
        } else if (typeof resultValue === 'function') {
            // Handle functions, assign directly
            state[key] = resultValue;
        } else {
            // Log an error for unhandled types
            console.error(`[${componentName}] Override value not working. No handling declared for:`, key, resultValue);
        }
        if (isNewField && instance?.proxy && instance.isMounted) {
            Object.defineProperty(instance.proxy, key, {
                configurable: true,
                enumerable: true,
                get: (): unknown => state[key],
                set: (value: unknown) => {
                    state[key] = value;
                },
            });
        }
    });
}
