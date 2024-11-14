/**
 * @package admin
 * @deprecated tag:v6.7.0 - Will be replaced with Pinia store
 */

import Vue, { reactive } from 'vue';
// @ts-expect-error - compatUtils is not typed
import { compatUtils } from '@vue/compat';
import type { Module } from 'vuex';

interface SdkLocationState {
    locations: {
        [locationId: string]: string;
    };
}

/**
 * This store contains Vue components for locations as a fallback when no iFrame should get rendered
 */
const SdkLocationStore: Module<SdkLocationState, VuexRootState> = {
    namespaced: true,

    state: (): SdkLocationState => ({
        locations: reactive({}),
    }),

    mutations: {
        addLocation(state, { locationId, componentName }: { locationId: string; componentName: string }) {
            if (!state.locations[locationId]) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
                if (compatUtils.isCompatEnabled('GLOBAL_SET')) {
                    Vue.set(state.locations, locationId, componentName);
                } else {
                    state.locations[locationId] = componentName;
                }
            }
        },
    },
};

/**
 * @private
 */
export default SdkLocationStore;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type { SdkLocationState };
