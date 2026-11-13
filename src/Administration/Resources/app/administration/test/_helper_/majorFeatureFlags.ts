/**
 * @sw-package framework
 */

/**
 * Extension Jest setups require this path.
 *
 * @public
 * @deprecated tag:v6.8.0 - Will be removed. Use `getMajorFeatureFlags` of `scripts/feature-flags` instead.
 */
export { getMajorFeatureFlags as default, isMajorRun, loadFeatureConfig } from '../../scripts/feature-flags';
