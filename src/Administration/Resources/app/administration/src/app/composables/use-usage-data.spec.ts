import useUsageData from './use-usage-data';

describe('use-usage-data', () => {
    beforeEach(() => {
        const store = useUsageData();
        store.$reset();
    });

    it('has initial state', () => {
        const store = useUsageData();
        expect(store.isConsentGiven.value).toBe(false);
        expect(store.isBannerHidden.value).toBe(false);
    });

    it('can update usage data context', () => {
        const store = useUsageData();
        store.updateConsent({
            isConsentGiven: true,
            isBannerHidden: true,
        });

        expect(store.isConsentGiven.value).toBe(true);
        expect(store.isBannerHidden.value).toBe(true);
    });

    it('can update consent approval', () => {
        const store = useUsageData();
        expect(store.isConsentGiven.value).toBe(false);

        store.updateIsConsentGiven(true);
        expect(store.isConsentGiven.value).toBe(true);

        store.updateIsConsentGiven(false);
        expect(store.isConsentGiven.value).toBe(false);
    });

    it('can hide dashboard banner', () => {
        const store = useUsageData();
        expect(store.isBannerHidden.value).toBe(false);

        store.hideBanner();
        expect(store.isBannerHidden.value).toBe(true);
    });
});
