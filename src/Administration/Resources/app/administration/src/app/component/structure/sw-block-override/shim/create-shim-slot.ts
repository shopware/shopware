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
        // Prefix with "TwigShimBlock_" so the component is clearly identifiable
        // in Vue DevTools as a compatibility shim rather than a production component.
        name: `TwigShimBlock_${blockName}`,
        template: entry.innerTemplate,
        components: { 'sw-block-parent': swBlockParent },
    };

    // A stable object reference is required so Vue's VDOM diff recognises the
    // same component type across slot calls and reuses the instance. Creating a
    // new object on every call (e.g. via spread) causes unmount + remount,
    // which destroys focus on every keystroke.
    const dataScopeRef = shallowRef<Record<string | symbol, unknown> | null>(null);

    const shimComponent = {
        ...def,
        setup: () => buildSetupContext(dataScopeRef.value),
    };

    return (dataScope) => {
        dataScopeRef.value = dataScope as Record<string | symbol, unknown> | null;
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
function buildSetupContext(dataScope: Record<string | symbol, unknown> | null): Record<string, unknown> {
    if (!dataScope) return {};

    const source = dataScope;

    return new Proxy({} as Record<string, unknown>, {
        get(_t, key: string | symbol): unknown {
            return isInternalKey(key) ? undefined : source[key];
        },
        has(_t, key: string | symbol): boolean {
            // Symbol keys are intentionally passed through: Vue uses private symbols
            // (e.g. __v_isRef, __v_isVue) on component proxies, and exposing them
            // here ensures that Vue's internal identity checks work correctly on
            // the host proxy without leaking them as template-visible bindings
            // (isInternalKey only guards against string-prefixed private names).
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
