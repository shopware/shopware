/**
 * @private
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const licenseViolationStoreId = 'licenseViolation';

/**
 * @sw-package framework
 * @private
 */
const licenseViolationStore = Shopware.Store.register({
    id: licenseViolationStoreId,

    state: () => ({
        violations: [] as unknown[],
        warnings: [] as unknown[],
        other: [] as unknown[],
    }),
});

/**
 * @private
 */
export type LicenseViolationStore = ReturnType<typeof licenseViolationStore>;

/**
 * @private
 */
export default licenseViolationStore;
