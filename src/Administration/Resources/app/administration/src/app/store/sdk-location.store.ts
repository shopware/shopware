/**
 * @sw-package framework
 */

interface SdkLocationState {
    locations: {
        [locationId: string]: string;
    };
}

const sdkLocation = Shopware.Store.register({
    id: 'sdkLocation',

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
