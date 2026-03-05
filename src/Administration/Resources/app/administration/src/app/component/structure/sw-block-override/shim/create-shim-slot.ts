/**
 * @sw-package framework
 * @private
 *
 * Slot factory for the Twig → Native Block Runtime Adapter.
 *
 * Takes a `BlockEntry` (pre-built inner template string from the block index)
 * and returns a native `Slot` function that `sw-block` can push into its
 * `blockContext` alongside slots from real `<sw-block extends>` components.
 *
 * Reactivity:
 *   `buildSetupContext` creates a Proxy whose getters delegate to the component
 *   proxy. When ShimContent's render function reads `ctx.someProperty`, Vue's
 *   reactivity system tracks `proxy.someProperty` as a dependency. Subsequent
 *   changes to that property on the host component automatically trigger
 *   ShimContent to re-render.
 *
 * `<sw-block-parent />` resolution:
 *   ShimContent is rendered inside `sw-block`'s render tree. `sw-block` already
 *   `provide()`s the parent VNode stack via `parentsInjectionKey`. `<sw-block-parent />`
 *   injects from that stack, so it resolves the previous content exactly as a
 *   natively written `<sw-block extends="...">` would.
 */

import { h, type Slot } from 'vue';
import type { BlockEntry } from 'src/core/factory/twig-block-index';
import swBlockParent from '../sw-block-parent/index';

/** Deprecation warnings are emitted once per block name across the app's lifetime. */
const warnedBlocks = new Set<string>();

/**
 * Returns `true` for Vue-internal (`$`) or private-convention (`_`) property
 * names. These are excluded from the reactive context proxy so that
 * ShimContent's template cannot accidentally resolve Vue internals.
 */
const isInternalKey = (key: string | symbol): boolean =>
    typeof key === 'string' && (key.startsWith('$') || key.startsWith('_'));

/**
 * Compiles `entry.innerTemplate` into a Slot function compatible with
 * `sw-block`'s `blockContext`. Emits a `console.warn` deprecation on first
 * use of each block name so developers know which overrides to migrate.
 *
 * Vue's runtime template compiler handles the `template` string on first mount
 * and caches the result internally — no manual caching is required.
 *
 * @private
 */
export function createShimSlot(entry: BlockEntry, blockName: string): Slot {
    if (!warnedBlocks.has(blockName)) {
        warnedBlocks.add(blockName);
        console.warn(
            `[Shopware Deprecation] Block "${blockName}" in component "${entry.componentName}" ` +
                `uses a legacy Twig override. ` +
                `Migrate to: <sw-block extends="${blockName}">...</sw-block>`,
        );
    }

    const def = {
        name: `__twig-shim__${blockName}`,
        template: entry.innerTemplate,
        components: { 'sw-block-parent': swBlockParent },
    };

    return (dataScope) => [h({ ...def, setup: () => buildSetupContext(dataScope as object | null) })];
}

/**
 * Builds a setup-context Proxy that gives ShimContent's compiled render
 * function transparent, reactive read access to every public property of the
 * host component proxy — without triggering Vue's ownKeys warning.
 *
 * Why a Proxy instead of Object.keys enumeration:
 *   Calling `Object.keys()` on a Vue component proxy logs
 *   "[Vue warn]: Avoid app logic that relies on enumerating keys on a component
 *   instance. The keys will be empty in production mode to avoid performance
 *   overhead." and returns an empty array in production, making a plain
 *   enumeration approach broken in production builds.
 *
 * Why a plain `{}` is used as the Proxy target:
 *   The ECMAScript spec requires that, when validating a Proxy's `ownKeys` trap
 *   result, the engine always calls `Reflect.ownKeys(target)` on the *actual*
 *   Proxy target to check invariants. If the component proxy were used as the
 *   target, that validation call would trigger `PublicInstanceProxyHandlers
 *   .ownKeys` — which emits Vue's warning — even though our trap returns `[]`.
 *   Using `{}` as the target means the invariant check calls
 *   `Reflect.ownKeys({})` = `[]`, which is completely silent.
 *
 * How the Proxy works:
 *   - `ownKeys()` returns `[]` so that `Object.keys(proxy)` enumerates nothing
 *     on the component proxy. This eliminates the warning and the
 *     production-empty-array problem.
 *   - `getOwnPropertyDescriptor` returns a fake configurable descriptor for any
 *     key that exists on the source (component proxy). This makes
 *     `hasOwnProperty(proxy, key)` return true for every publicly accessible
 *     property, which is how Vue's `hasSetupBinding()` check decides that the
 *     key lives in setup state and should be read from there.
 *   - `get` reads directly from the `source` closure (the component proxy) so
 *     Vue's reactivity system tracks each read as a dependency exactly as it
 *     would for any direct reactive access.
 *
 * Internal Vue (`$`) and private convention (`_`) keys are excluded via
 * `isInternalKey`.
 */
function buildSetupContext(dataScope: object | null): Record<string, unknown> {
    if (!dataScope) return {};

    const source = dataScope as Record<string | symbol, unknown>;

    return new Proxy({} as Record<string, unknown>, {
        get(_t, key: string | symbol): unknown {
            return isInternalKey(key) ? undefined : source[key];
        },
        has(_t, key: string | symbol): boolean {
            return !isInternalKey(key) && key in source;
        },
        getOwnPropertyDescriptor(_t, key: string | symbol): PropertyDescriptor | undefined {
            if (isInternalKey(key) || !(key in source)) return undefined;
            return { configurable: true, enumerable: false, get: () => source[key] };
        },
        ownKeys(): (string | symbol)[] {
            return [];
        },
    });
}

/**
 * Clears all module-level caches. For test teardown only — never call in
 * production code.
 *
 * @private
 */
export function resetShimSlotState(): void {
    warnedBlocks.clear();
}
