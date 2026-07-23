import type { Reactive } from 'vue';
import { computed, effectScope } from 'vue';
import { _overridesMap } from './index';
import type { OverrideLocalState } from './data-scope-helper';
import { createOverrideLocalState, isOverrideLocalStateKey, mergeOverrideState } from './data-scope-helper';

/**
 * @sw-package framework
 * @private
 *
 * Applies native setup overrides for Options API host components.
 *
 * `createExtendableSetup(...)` is what runs registered `overrideComponentSetup(...)` callbacks — and
 * only native-setup components call it. A native override targeting an **Options API** component
 * therefore never executes: its template contribution still renders (the block registry is global),
 * but any override-local (`__swOverride`) state it returns would silently not exist when the host
 * renders a block. This module closes that gap for the block data scope: it runs those callbacks once
 * per Options host component and collects their override-local payloads so `getBlockDataScope()` can
 * attach the `__swOverride` channel to the host's slot data.
 *
 * Deliberately conservative semantics:
 * - `previousState` fields are read-only computed refs over the host's public instance proxy, so
 *   callbacks can read Options data through the usual `.value` access. Replacing an Options
 *   component's state through `swDefineOverride(...)` is not possible — non-local result keys are
 *   reported and skipped (use `Shopware.Component.override()` for Options API components).
 * - A callback that throws is reported and skipped; remaining overrides still apply.
 * - Like the rest of the extension system, overrides are expected to be registered before the
 *   application mounts; the callbacks run once per host component name.
 */

/**
 * Keeps the previous-state computed refs alive for the application lifetime.
 *
 * The refs are created outside any component, so they must not be collected by a component's effect
 * scope (the first rendering host may unmount while other instances keep using the applied state).
 */
const detachedScope = effectScope(true);

const overrideLocalStateByComponent = new Map<string, Reactive<OverrideLocalState>>();
const appliedOverridesByComponent = new Map<string, Set<unknown>>();

/**
 * Wraps the host's public instance so previous-state reads look like composition state.
 *
 * e.g. an override doing `previousState.headline.value` reads `proxy.headline` reactively.
 */
function createOptionsPreviousState(proxy: object): Record<string, unknown> {
    const refCache = new Map<PropertyKey, unknown>();

    return new Proxy(
        {},
        {
            get(_, key) {
                // Options API components have no composition-private state to expose.
                if (key === '_private') {
                    return {};
                }

                if (!refCache.has(key)) {
                    refCache.set(
                        key,
                        detachedScope.run(() => computed(() => (proxy as Record<PropertyKey, unknown>)[key])),
                    );
                }

                return refCache.get(key);
            },
        },
    ) as Record<string, unknown>;
}

/**
 * @private
 *
 * Runs pending native overrides for one Options API host and returns its override-local state.
 *
 * Returns null when no native override is registered for the component, so callers can keep using the
 * plain instance proxy as the block data scope.
 */
export function applyOverridesForOptionsHost(componentName: string, proxy: object): Reactive<OverrideLocalState> | null {
    const overrides = _overridesMap[componentName];

    if (!overrides || overrides.length === 0) {
        return overrideLocalStateByComponent.get(componentName) ?? null;
    }

    let localState = overrideLocalStateByComponent.get(componentName);

    if (!localState) {
        localState = createOverrideLocalState();
        overrideLocalStateByComponent.set(componentName, localState);
    }

    let applied = appliedOverridesByComponent.get(componentName);

    if (!applied) {
        applied = new Set();
        appliedOverridesByComponent.set(componentName, applied);
    }

    const previousState = createOptionsPreviousState(proxy);

    overrides.forEach((override) => {
        if (applied.has(override)) {
            return;
        }

        applied.add(override);

        let overrideResult: Record<string, unknown>;

        try {
            overrideResult = override(
                previousState as Parameters<typeof override>[0],
                {} as Parameters<typeof override>[1],
                {} as Parameters<typeof override>[2],
            ) as Record<string, unknown>;
        } catch (error) {
            console.error(
                `[${componentName}] A native setup override could not be applied on this Options API component:`,
                error,
            );
            return;
        }

        Object.keys(overrideResult).forEach((key) => {
            if (isOverrideLocalStateKey(key)) {
                mergeOverrideState(localState, overrideResult[key] as OverrideLocalState);
                return;
            }

            console.error(
                `[${componentName}] swDefineOverride() cannot replace state of an Options API component; ` +
                    `the key "${key}" was ignored. Use Shopware.Component.override() to override Options API components.`,
            );
        });
    });

    return localState;
}

/**
 * @private
 *
 * Test-only reset of the per-component application caches.
 */
export function _resetOptionsHostOverrides(): void {
    overrideLocalStateByComponent.clear();
    appliedOverridesByComponent.clear();
}
