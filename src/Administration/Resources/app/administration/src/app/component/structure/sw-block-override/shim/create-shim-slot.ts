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
 *   `buildSetupContext` creates an object whose properties are backed by
 *   `Object.defineProperty` getters delegating to the component proxy. When
 *   ShimContent's render function reads `ctx.someProperty`, Vue's reactivity
 *   system tracks `proxy.someProperty` as a dependency. Subsequent changes to
 *   that property on the host component automatically trigger ShimContent to
 *   re-render — no extra effort required from the developer.
 *
 * `<sw-block-parent />` resolution:
 *   ShimContent is rendered inside `sw-block`'s render tree. `sw-block` already
 *   `provide()`s the parent VNode stack via `parentsInjectionKey`. `<sw-block-parent />`
 *   injects from that stack, so it resolves the previous content exactly as a
 *   natively written `<sw-block extends="...">` would.
 */

import { h, type Slot } from 'vue';
import type { BlockEntry } from './block-index';
import swBlockParent from '../sw-block-parent/index';

/** Deprecation warnings are emitted once per block name across the app's lifetime. */
const warnedBlocks = new Set<string>();

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

    return (dataScope) => {
        const ShimContent = {
            name: `__twig-shim__${blockName}`,
            template: entry.innerTemplate,
            /**
             * Register sw-block-parent locally so that `<sw-block-parent />` in
             * the reconstructed template string is resolved correctly. In a
             * production Vue app, sw-block-parent is globally registered at boot
             * time; this explicit registration ensures it is also available for
             * tests (which only register components locally on the host wrapper).
             */
            components: { 'sw-block-parent': swBlockParent },
            setup: () => buildSetupContext(dataScope as object | null),
        };

        return [h(ShimContent)];
    };
}

/**
 * Builds a setup context object that exposes all public properties of the host
 * component proxy via `Object.defineProperty` getters.
 *
 * Using getters (rather than copying values) is essential for reactivity: each
 * property access inside ShimContent's render function reads through to the
 * reactive proxy, registering the dependency in Vue's tracking system. Changes
 * to the host component's data, computed properties, or methods are therefore
 * automatically reflected in ShimContent without re-creating the slot.
 *
 * Properties starting with `$` (Vue internals) or `_` (private conventions) are
 * excluded to keep the context clean.
 */
function buildSetupContext(dataScope: object | null): Record<string, unknown> {
    if (!dataScope) return {};

    const ctx: Record<string, unknown> = {};

    Object.keys(dataScope)
        .filter((key) => !key.startsWith('$') && !key.startsWith('_'))
        .forEach((key) => {
            Object.defineProperty(ctx, key, {
                get: () => (dataScope as Record<string, unknown>)[key],
                enumerable: true,
                configurable: true,
            });
        });

    return ctx;
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
