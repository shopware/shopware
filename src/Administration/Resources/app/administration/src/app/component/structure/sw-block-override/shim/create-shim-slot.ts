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
import type { BlockEntry } from 'src/core/factory/transform-legacy-block-conditionals';
import swBlockParent from '../sw-block-parent/index';
import useLegacyConditionContext from './legacy-condition-context';

/**
 * Represents the host component data exposed to a legacy Twig shim template.
 * Use it when reading reactive values or global helpers from the component that owns the original block.
 *
 * @example
 * const scope: DataScope = { product, $swLegacyBlockElse };
 */
type DataScope = Record<string | symbol, unknown>;

/**
 * Extends `DataScope` with the Vue internals needed to reach instance ids and global properties.
 * Use it when helper calls must be scoped by component uid or resolved from `app.config.globalProperties`.
 *
 * @example
 * const uid = (source as DataScopeWithAppContext).$?.uid;
 */
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

/**
 * Describes one allowlisted legacy block helper exposed to the shim template.
 * Use it after resolving `$swLegacyBlockIf`, `$swLegacyBlockElseIf`, or `$swLegacyBlockElse`.
 *
 * @example
 * const helper: LegacyBlockHelper = (...args) => args.length;
 */
type LegacyBlockHelper = (...args: unknown[]) => unknown;

const warnedBlocks = new Set<string>();
const allowedLegacyBlockHelperKeys = new Set<string>([
    '$swLegacyBlockIf',
    '$swLegacyBlockElseIf',
    '$swLegacyBlockElse',
]);

/**
 * Resolves an allowlisted legacy helper from the data scope or the owning app's global properties.
 * Use it when a shim template calls a generated `$swLegacyBlock*` expression.
 *
 * @example
 * const helper = resolveAllowedLegacyBlockHelper(dataScopeRef.value, '$swLegacyBlockElse');
 */
function resolveAllowedLegacyBlockHelper(source: DataScope, helperName: string): unknown {
    const helper =
        source[helperName] ?? (source as DataScopeWithAppContext).$?.appContext?.config?.globalProperties?.[helperName];

    return typeof helper === 'function' ? helper.bind(source) : helper;
}

/**
 * Builds the same per-component condition key used by the global legacy helpers.
 * Use it before reserving or clearing shim condition cases for a specific component instance.
 *
 * @example
 * getLegacyBlockConditionKey(dataScope, 'sw_product_detail_base:0');
 */
function getLegacyBlockConditionKey(source: DataScope, chainKey: string): string {
    const componentUid = (source as DataScopeWithAppContext).$?.uid;

    if (typeof componentUid !== 'number') {
        return chainKey;
    }

    return `${componentUid}:${chainKey}`;
}

/**
 * Lists the unique runtime chain keys that a shim slot reserved.
 * Use it during unmount to clear condition chains owned by that shim component.
 *
 * @example
 * const chainKeys = getReservedLegacyConditionChainKeys(dataScopeRef.value, entry);
 */
function getReservedLegacyConditionChainKeys(source: DataScope, entry: BlockEntry): string[] {
    return Array.from(
        new Set(
            entry.legacyConditionCases.map((reservation) => {
                return getLegacyBlockConditionKey(source, reservation.chainKey);
            }),
        ),
    );
}

/**
 * Guards against accidentally exposing Vue internals or private properties into the shim template.
 * Use it from the setup proxy traps before forwarding a property read to the host component.
 *
 * @example
 * isInternalKey('$store');
 */
function isInternalKey(key: string | symbol): boolean {
    if (typeof key !== 'string' || allowedLegacyBlockHelperKeys.has(key)) {
        return false;
    }

    return key[0] === '$' || key[0] === '_';
}

/**
 * Creates a Vue slot that renders one indexed legacy Twig block as a compatibility shim.
 * Use it from `<sw-block name="...">` when `twig-block-index` reports legacy override entries.
 *
 * @example
 * const slot = createShimSlot(entry, 'sw_product_detail_base');
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
    const { reserveLegacyConditionCases, clearLegacyConditionChain } = useLegacyConditionContext();
    /**
     * Drops persisted chain state when the owning shim component is removed.
     * Use it as the shim component `beforeUnmount` hook.
     *
     * @example
     * clearExtensionChain();
     */
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
                    throw new Error(
                        `[sw-block] Legacy Twig conditional override for block "${blockName}" ` +
                            `in component "${entry.componentName}" requires host data scope. ` +
                            `Pass :data="$dataScope" to <sw-block name="${blockName}">.`,
                    );
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
 *
 * Use it as the setup result for a shim component so identifiers in the legacy
 * template resolve against the current host component data scope.
 *
 * @example
 * const setupContext = buildSetupContext(() => dataScope);
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

/**
 * Resets module-level shim-slot state used to de-duplicate warnings.
 * Use it in test teardown only, never in production code.
 *
 * @example
 * resetShimSlotState();
 *
 * @private
 */
export function resetShimSlotState(): void {
    warnedBlocks.clear();
}
