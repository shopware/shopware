/**
 * @deprecated tag:v6.7.0 - Will be replaced with Pinia store
 */

import type { Module } from 'vuex';
import type { UsageDataContext } from '../../core/service/api/usage-data.api.service';

type UsageDataModuleState = {
    isConsentGiven: boolean;
    isBannerHidden: boolean;
};

const usageDataModule: Module<UsageDataModuleState, VuexRootState> = {
    namespaced: true,

    state: {
        isConsentGiven: false,
        isBannerHidden: false,
    },

    mutations: {
        resetConsent(state) {
            state.isConsentGiven = false;
            state.isBannerHidden = true;
        },

        updateConsent(state, context: UsageDataContext) {
            state.isConsentGiven = context.isConsentGiven;
            state.isBannerHidden = context.isBannerHidden;
        },

        updateIsConsentGiven(state, isConsentGiven: boolean) {
            state.isConsentGiven = isConsentGiven;
        },

        hideBanner(state) {
            state.isBannerHidden = true;
        },
    },
};

/**
 * @private
 */
export default usageDataModule;

/**
 * @private
 */
export type { UsageDataModuleState };
