/**
 * @sw-package framework
 */
import Feature from 'src/core/feature';

/**
 * A deprecated function for the deprecation specs. It imports `Feature` directly, like
 * `src/core/shopware.ts`, instead of using `Shopware.Feature`.
 *
 * @private
 */
export default function oldFunction(): void {
    Feature.triggerDeprecationOrThrow('V7_0_0_0', 'oldFunction() is deprecated.');
}
