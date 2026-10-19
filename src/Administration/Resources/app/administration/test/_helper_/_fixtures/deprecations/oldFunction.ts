/**
 * @sw-package framework
 */
import Feature from 'src/core/feature';

// Registered, so the guard treats the stand-in major as known and does not report it as a typo.
Feature.init({ V7_0_0_0: false });

/**
 * A deprecated function for the deprecation specs. It imports `Feature` directly, like
 * `src/core/shopware.ts`, instead of using `Shopware.Feature`.
 *
 * @private
 */
export default function oldFunction(): void {
    Feature.triggerDeprecationOrThrow('V7_0_0_0', 'oldFunction() is deprecated.');
}
