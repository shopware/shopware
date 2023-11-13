// eslint-disable-next-line filename-rules/match
import { shallowMount } from '@vue/test-utils';
import swSettingsUsageDataPage from 'src/module/sw-settings-usage-data/page/sw-settings-usage-data';
import swUsageDataConsentBanner from 'src/module/sw-settings-usage-data/component/sw-usage-data-consent-banner';
import 'src/app/component/base/sw-icon';

Shopware.Component.register('sw-settings-usage-data', swSettingsUsageDataPage);

const usageDataService = {
    getConsent: () => jest.fn(),
    acceptConsent: () => jest.fn(),
    revokeConsent: () => jest.fn(),
    hideBanner: () => jest.fn(),
};

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-settings-usage-data'), {
        stubs: {
            'sw-icon': await Shopware.Component.build('sw-icon'),
            'sw-usage-data-consent-banner': swUsageDataConsentBanner,
            'sw-page': true,
            'sw-search-bar': true,
            'sw-card-view': true,
            'sw-external-link': true,
            'sw-button': true,
            'sw-internal-link': true,
            'sw-help-text': true,
            'i18n-t': true,
            i18n: true,
        },
        provide: {
            usageDataService,
        },
    });
}

describe('src/module/sw-settings-usage-data/page/sw-settings-usage-data', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        if (wrapper) {
            wrapper.destroy();
        }
    });

    it('should show the usage data consent banner', () => {
        expect(wrapper.findComponent(swUsageDataConsentBanner).isVisible()).toBe(true);
        expect(wrapper.find('.sw-usage-data-consent-banner').isVisible()).toBe(true);
    });

    it('should refresh the consent information when created', async () => {
        const getConsentSpy = jest.spyOn(usageDataService, 'getConsent');

        await createWrapper();

        expect(getConsentSpy).toHaveBeenCalled();
    });

    it('should not allow the consent banner to be hidden', () => {
        const banner = wrapper.findComponent(swUsageDataConsentBanner);

        expect(banner.vm.canBeHidden).toBe(false);
    });
});
