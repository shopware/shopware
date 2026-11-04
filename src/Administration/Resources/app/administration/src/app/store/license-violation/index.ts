/**
 * @sw-package framework
 *
 * The `licenseViolation` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/licenseViolation`.
 */
/**
 * @public
 * @shopware-kind stores
 */
export { licenseViolationStoreId, useLicenseViolationStore } from './src/license-violation.store';

/** @public */
export type { LicenseViolationStore } from './src/license-violation.store';
