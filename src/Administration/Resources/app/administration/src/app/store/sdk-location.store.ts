/**
 * @sw-package framework
 */

interface SdkLocationState {
    locations: {
        [locationId: string]: string;
    };
}

/**
 * @private
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const sdkLocationStoreId = 'sdkLocation';

const sdkLocation = Shopware.Store.register({
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
 * @private
 */
export type SdkLocation = ReturnType<typeof sdkLocation>;

/**
 * @private
 */
export default sdkLocation;
