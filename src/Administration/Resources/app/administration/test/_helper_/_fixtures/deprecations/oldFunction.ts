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

/**
 * A deprecated function for the deprecation specs that calls the guard through a helper of its module,
 * like `src/core/helper/i18n-legacy-syntax.helper.ts`.
 *
 * @private
 */
export function oldWrappedFunction(): void {
    reportDeprecation();
}

/**
 * The guard call of `oldWrappedFunction()`, a frame of the deprecated module between the guard and its caller.
 */
function reportDeprecation(): void {
    Feature.triggerDeprecationOrThrow('V7_0_0_0', 'oldWrappedFunction() is deprecated.');
}
