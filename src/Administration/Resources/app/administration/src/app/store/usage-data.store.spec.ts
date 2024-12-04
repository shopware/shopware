describe('usage-data.store', () => {
    const store = Shopware.Store.get('usageData');

    beforeEach(() => {
        store.$reset();
    });

    it('has initial state', () => {
        expect(store.isConsentGiven).toBe(false);
        expect(store.isBannerHidden).toBe(false);
    });

    it('can update usage data context', () => {
        store.updateConsent({
            isConsentGiven: true,
            isBannerHidden: true,
        });

        expect(store.isConsentGiven).toBe(true);
        expect(store.isBannerHidden).toBe(true);
    });

    it('can update consent approval', () => {
        expect(store.isConsentGiven).toBe(false);

        store.updateIsConsentGiven(true);
        expect(store.isConsentGiven).toBe(true);

        store.updateIsConsentGiven(false);
        expect(store.isConsentGiven).toBe(false);
    });

    it('can hide dashboard banner', () => {
        expect(store.isBannerHidden).toBe(false);

        store.hideBanner();
        expect(store.isBannerHidden).toBe(true);
    });
});
