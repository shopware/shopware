/**
 * @sw-package framework
 */
import { defineStoreOptions, getStore, type StoreOf } from '../dependencies';
/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const licenseViolationStoreId = 'licenseViolation';

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const licenseViolationStoreOptions = defineStoreOptions({
    id: licenseViolationStoreId,

    state: () => ({
        violations: [] as unknown[],
        warnings: [] as unknown[],
        other: [] as unknown[],
    }),
});

/**
 * @public
 */
export type LicenseViolationStore = StoreOf<typeof licenseViolationStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useLicenseViolationStore = (): LicenseViolationStore => getStore(licenseViolationStoreId);
