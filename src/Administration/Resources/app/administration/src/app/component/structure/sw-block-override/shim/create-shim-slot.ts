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
import useBlockContext from 'src/app/composables/use-block-context';
import type { BlockEntry } from 'src/core/factory/twig-block-index';
import swBlockParent from '../sw-block-parent/index';

type DataScope = Record<string | symbol, unknown>;
type DataScopeWithAppContext = DataScope & {
    $?: {
        uid?: number;
        appContext?: {
            config?: {
                globalProperties?: Record<string, unknown>;
            };
        };
    };
};
type LegacyBlockHelper = (...args: unknown[]) => unknown;

const warnedBlocks = new Set<string>();
const allowedLegacyBlockHelperKeys = new Set<string>([
    '$swLegacyBlockIf',
    '$swLegacyBlockElseIf',
    '$swLegacyBlockElse',
]);

function resolveAllowedLegacyBlockHelper(source: DataScope, helperName: string): unknown {
    const helper =
        source[helperName] ?? (source as DataScopeWithAppContext).$?.appContext?.config?.globalProperties?.[helperName];

    return typeof helper === 'function' ? helper.bind(source) : helper;
}

/** Builds the same per-component condition key used by the global legacy helpers. */
function getLegacyBlockConditionKey(source: DataScope, chainKey: string): string {
    const componentUid = (source as DataScopeWithAppContext).$?.uid;

    if (typeof componentUid !== 'number') {
        return chainKey;
    }

    return `${componentUid}:${chainKey}`;
}

function getReservedLegacyConditionChainKeys(source: DataScope, entry: BlockEntry): string[] {
    return Array.from(
        new Set(
            entry.legacyConditionCases.map((reservation) => {
                return getLegacyBlockConditionKey(source, reservation.chainKey);
            }),
        ),
    );
}

/** Guards against accidentally exposing Vue internals or private properties into the shim template. */
function isInternalKey(key: string | symbol): boolean {
    if (typeof key !== 'string' || allowedLegacyBlockHelperKeys.has(key)) {
        return false;
    }

    return key[0] === '$' || key[0] === '_';
}

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
    // The render version still changes every slot call so Vue patches the
    // reused shim instance even when the host component proxy identity is stable.
    const dataScopeRef = shallowRef<DataScope>({});
    let renderVersion = 0;
    const { reserveLegacyConditionCases, clearLegacyConditionChain } = useBlockContext();
    /** Drops persisted chain state when the owning shim component is removed. */
    const clearExtensionChain = () => {
        getReservedLegacyConditionChainKeys(dataScopeRef.value, entry).forEach((chainKey) => {
            clearLegacyConditionChain(chainKey);
        });
    };
    const methods = Object.fromEntries(
        Array.from(allowedLegacyBlockHelperKeys).map((helperName) => [
            helperName,
            (...args: unknown[]): unknown => {
                const helper = resolveAllowedLegacyBlockHelper(dataScopeRef.value, helperName);

                if (typeof helper !== 'function') {
                    return undefined;
                }

                return (helper as LegacyBlockHelper)(...args);
            },
        ]),
    );

    const shimComponent = {
        ...def,
        methods,
        props: {
            swBlockShimRenderVersion: {
                type: Number,
                required: true,
            },
        },
        setup: () => buildSetupContext(() => dataScopeRef.value),
        beforeUnmount: clearExtensionChain,
    };

    return (dataScope) => {
        dataScopeRef.value = (dataScope ?? {}) as DataScope;
        renderVersion += 1;

        // Reserve before mounting so later native cases wait for this shim chain to evaluate.
        entry.legacyConditionCases.forEach((reservation) => {
            reserveLegacyConditionCases(getLegacyBlockConditionKey(dataScopeRef.value, reservation.chainKey), reservation);
        });

        return [
            h(shimComponent, {
                swBlockShimRenderVersion: renderVersion,
            }),
        ];
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
function buildSetupContext(getDataScope: () => DataScope): Record<string, unknown> {
    return new Proxy({} as Record<string, unknown>, {
        get(_t, key: string | symbol): unknown {
            const source = getDataScope();

            if (typeof key === 'string' && allowedLegacyBlockHelperKeys.has(key)) {
                return resolveAllowedLegacyBlockHelper(source, key);
            }

            return isInternalKey(key) ? undefined : source[key];
        },
        has(_t, key: string | symbol): boolean {
            const source = getDataScope();

            // Symbol keys are intentionally passed through: Vue uses private symbols
            // (e.g. __v_isRef, __v_isVue) on component proxies, and exposing them
            // here ensures that Vue's internal identity checks work correctly on
            // the host proxy without leaking them as template-visible bindings
            // (isInternalKey only guards against string-prefixed private names).
            if (typeof key === 'string' && allowedLegacyBlockHelperKeys.has(key)) {
                return typeof resolveAllowedLegacyBlockHelper(source, key) === 'function';
            }

            return !isInternalKey(key) && key in source;
        },
        getOwnPropertyDescriptor(_t, key: string | symbol): PropertyDescriptor | undefined {
            const source = getDataScope();

            if (typeof key === 'string' && allowedLegacyBlockHelperKeys.has(key)) {
                if (typeof resolveAllowedLegacyBlockHelper(source, key) !== 'function') return undefined;

                return {
                    configurable: true,
                    enumerable: false,
                    get: () => resolveAllowedLegacyBlockHelper(source, key),
                };
            }

            if (isInternalKey(key) || !(key in source)) return undefined;
            return {
                configurable: true,
                enumerable: false,
                get: () => source[key],
            };
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
