/**
 * @sw-package framework
 * @private
 *
 * Slot factory for the Twig → Native Block Runtime Adapter.
 *
 * Reactivity works because the Proxy in `buildSetupContext` delegates every
 * property read to the host component proxy, so Vue's reactivity system tracks
 * those reads as dependencies and re-renders ShimContent when they change.
 *
 * `<sw-block-parent />` resolves correctly because ShimContent is rendered
 * inside `sw-block`'s tree, which already provides the parent VNode stack —
 * identical behaviour to a natively written `<sw-block extends="...">`.
 */

import { h, shallowRef, type Slot } from 'vue';
import type { BlockEntry } from 'src/core/factory/twig-block-index';
import swBlockParent from '../sw-block-parent/index';

const warnedBlocks = new Set<string>();

/** Guards against accidentally exposing Vue internals or private properties into the shim template. */
const isInternalKey = (key: string | symbol): boolean =>
    typeof key === 'string' && (key.startsWith('$') || key.startsWith('_'));

/** @private */
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

    const dataScopeRef = shallowRef<object | null>(null);

    // A stable object reference is required so Vue's VDOM diff recognises the
    // same component type across slot calls and reuses the instance. Creating a
    // new object on every call (e.g. via spread) causes unmount + remount,
    // which destroys focus on every keystroke.
    const shimComponent = {
        ...def,
        setup: () => buildSetupContext(dataScopeRef.value),
    };

    return (dataScope) => {
        dataScopeRef.value = dataScope as object | null;
        return [h(shimComponent)];
    };
}

/**
 * A Proxy is used instead of `Object.keys` enumeration because Vue component
 * proxies return an empty array in production and emit a warning in development
 * when enumerated. The Proxy delegates property reads on-demand, which is how
 * Vue's `hasSetupBinding()` check resolves template identifiers and how the
 * reactivity system tracks dependencies.
 *
 * The Proxy target is a plain `{}` rather than the component proxy itself.
 * The ECMAScript spec validates `ownKeys` trap results by calling
 * `Reflect.ownKeys` on the *actual* target. Using the component proxy as the
 * target would trigger Vue's `ownKeys` warning on that validation call even
 * though our trap returns `[]`. A plain `{}` target keeps that check silent.
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

/** For test teardown only — never call in production code. @private */
export function resetShimSlotState(): void {
    warnedBlocks.clear();
}
