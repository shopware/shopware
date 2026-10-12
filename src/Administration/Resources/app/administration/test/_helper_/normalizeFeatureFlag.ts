/**
 * @sw-package framework
 */

/**
 * Brings a feature flag into the upper snake case form the runtime uses, so the same flag written in
 * either notation compares equal.
 *
 * Flags reach us in both spellings: `window._features_` and `.env` use `V6_8_0_0`, while
 * most `isActive()` calls use `v6.8.0.0`. Uppercasing and replacing
 * `.`, `:` and `-` with `_` maps both onto one key.
 *
 * ```
 * normalizeFeatureFlag('v6.8.0.0');              // 'V6_8_0_0'
 * normalizeFeatureFlag('V6_8_0_0');              // 'V6_8_0_0'  (already normalized)
 * normalizeFeatureFlag('FEATURE_NEXT_12345');    // 'FEATURE_NEXT_12345'
 * normalizeFeatureFlag('ENABLE_METEOR_COMPONENTS'); // 'ENABLE_METEOR_COMPONENTS'
 * ```
 *
 * @private
 */
export default function normalizeFeatureFlag(featureFlag: string): string {
    return featureFlag.toUpperCase().replace(/[.:-]/g, '_');
}
