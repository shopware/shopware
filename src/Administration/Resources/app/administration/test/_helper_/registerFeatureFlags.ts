/**
 * @sw-package framework
 * @private
 */

import Feature from 'src/core/feature';
import { loadFeatureConfig, type FeatureConfig } from './majorFeatureFlags';
import normalizeFeatureFlag from './normalizeFeatureFlag';

/**
 * Registers every flag of `feature.yaml` in both spellings, like `window._features_` in the browser, so a
 * deprecation guard reports only an unknown flag as a typo. Only the flags in `activeFlags` start active.
 *
 * @private
 * @example
 * registerFeatureFlags(global.activeFeatureFlags);
 */
export default function registerFeatureFlags(
    activeFlags: readonly string[],
    featureConfig: FeatureConfig = loadFeatureConfig(),
): void {
    const active = new Set(activeFlags.map(normalizeFeatureFlag));
    const flagNames = (featureConfig.shopware?.feature?.flags ?? []).flatMap(({ name }) => [
        name,
        normalizeFeatureFlag(name),
    ]);

    Feature.init(Object.fromEntries(flagNames.map((name) => [name, active.has(normalizeFeatureFlag(name))])));
}
