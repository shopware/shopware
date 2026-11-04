/**
 * @sw-package framework
 */
import { defineStoreOptions, getStore, type StoreOf } from '../dependencies';
interface SdkLocationState {
    locations: {
        [locationId: string]: string;
    };
}

/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const sdkLocationStoreId = 'sdkLocation';

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const sdkLocationStoreOptions = defineStoreOptions({
    id: sdkLocationStoreId,

    state: (): SdkLocationState => ({
        locations: {},
    }),

    actions: {
        addLocation({ locationId, componentName }: { locationId: string; componentName: string }) {
            if (!this.locations[locationId]) {
                this.locations[locationId] = componentName;
            }
        },
    },
});

/**
 * @public
 */
export type SdkLocation = StoreOf<typeof sdkLocationStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useSdkLocationStore = (): SdkLocation => getStore(sdkLocationStoreId);
