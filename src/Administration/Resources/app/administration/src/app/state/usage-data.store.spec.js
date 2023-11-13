import Vuex from 'vuex';
import usageDataStoreModule from './usage-data.store';

describe('usage-data.store', () => {
    let store = null;

    beforeEach(() => {
        store = new Vuex.Store(usageDataStoreModule);
    });

    afterEach(() => {
        store.state.isConsentGiven = false;
        store.state.isBannerHidden = false;
    });

    it('has initial state', () => {
        expect(store.state.isConsentGiven).toBe(false);
        expect(store.state.isBannerHidden).toBe(false);
    });

    it('can update usage data context', () => {
        store.commit('updateConsent', {
            isConsentGiven: true,
            isBannerHidden: true,
        });

        expect(store.state.isConsentGiven).toBe(true);
        expect(store.state.isBannerHidden).toBe(true);
    });

    it('can update consent approval', () => {
        expect(store.state.isConsentGiven).toBe(false);

        store.commit('updateIsConsentGiven', true);
        expect(store.state.isConsentGiven).toBe(true);

        store.commit('updateIsConsentGiven', false);
        expect(store.state.isConsentGiven).toBe(false);
    });

    it('can hide dashboard banner', () => {
        expect(store.state.isBannerHidden).toBe(false);

        store.commit('hideBanner');
        expect(store.state.isBannerHidden).toBe(true);
    });
});
