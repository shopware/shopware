/**
 * @sw-package framework
 */

/** @private */
export default function normalizeFeatureFlag(featureFlag: string): string {
    return featureFlag.toUpperCase().replace(/[.:-]/g, '_');
}
