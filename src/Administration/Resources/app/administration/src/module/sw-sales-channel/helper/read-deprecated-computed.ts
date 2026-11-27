/**
 * @sw-package discovery
 */

type ComputedOption = (() => unknown) | { get: () => unknown };

/**
 * Reads the deprecated computed `name` of `vm` for core, with the deprecations of 6.8 silenced, so an
 * override of it keeps working. Use it in a `…Deprecated` wrapper computed, instead of reading `this[name]`.
 *
 * It runs the getter itself instead of reading the cached computed: Vue checks a cached computed that another
 * computed or the template depends on again later, outside of the silence, and would report it then.
 * An extension that reads the computed directly still gets the deprecation.
 *
 * @private
 * @deprecated tag:v6.8.0 - Will be removed with the Agentic Commerce computeds
 * @example
 * isAgenticCommerceDeprecated() {
 *     if (Shopware.Feature.isActive('V6_8_0_0')) {
 *         return false;
 *     }
 *
 *     return readDeprecatedComputed(this, 'isAgenticCommerce');
 * },
 */
export default function readDeprecatedComputed<TValue>(
    vm: { $options: { computed: Record<string, ComputedOption> } },
    name: string,
): TValue {
    const computed = vm.$options.computed[name];
    const getter = typeof computed === 'function' ? computed : computed.get;

    return Shopware.Feature.silence('V6_8_0_0', () => getter.call(vm) as TValue);
}
