/**
 * @sw-package framework
 * @private
 */

import { computed, ref, type Ref, type ComputedRef } from 'vue';
import type { ComponentState, AnyFn, ComputedDefinition } from './types';

/** @private */
export function convertMethods(methods: Record<string, AnyFn>, thisProxy: object): ComponentState {
    const converted: ComponentState = {};

    Object.entries(methods).forEach(
        ([
            name,
            method,
        ]) => {
            converted[name] = function (...args: unknown[]) {
                return method.apply(thisProxy, args);
            };
        },
    );

    return converted;
}

/** @private */
export function convertComputed(
    computedDefs: Record<string, ComputedDefinition>,
    thisProxy: object,
): Record<string, ComputedRef> {
    const converted: Record<string, ComputedRef> = {};

    Object.entries(computedDefs).forEach(
        ([
            name,
            computedDef,
        ]) => {
            if (typeof computedDef === 'function') {
                // Simple getter
                converted[name] = computed(() => computedDef.call(thisProxy));
            } else if (computedDef && typeof computedDef === 'object' && (computedDef.get || computedDef.set)) {
                // Getter/setter
                const getter = computedDef.get ? () => computedDef.get!.call(thisProxy) : undefined;
                const setter = computedDef.set ? (val: unknown) => computedDef.set!.call(thisProxy, val) : undefined;

                if (getter && setter) {
                    converted[name] = computed({
                        get: getter,
                        set: setter,
                    });
                } else if (getter) {
                    converted[name] = computed(getter);
                } else {
                    console.error(
                        `[Options-Composition-Shim] Computed property "${name}" has a setter but no getter. ` +
                            'A computed property must have at least a getter. The property will be skipped.',
                    );
                }
            }
        },
    );

    return converted;
}

/** @private */
export function convertData(dataFn: (() => Record<string, unknown>) | Record<string, unknown>): Record<string, Ref> {
    const data = typeof dataFn === 'function' ? dataFn() : dataFn;
    const converted: Record<string, Ref> = {};

    if (!data || typeof data !== 'object') {
        return converted;
    }

    Object.entries(data).forEach(
        ([
            key,
            value,
        ]) => {
            converted[key] = ref(value);
        },
    );

    return converted;
}
