import { mount } from '@vue/test-utils';
import swSettingsUsageData from 'src/module/sw-settings-usage-data/page/sw-settings-usage-data';
import swUsageDataConsentBanner from 'src/module/sw-settings-usage-data/component/sw-usage-data-consent-banner';

const usageDataService = {
    getConsent: () => jest.fn(),
    acceptConsent: () => jest.fn(),
    revokeConsent: () => jest.fn(),
    hideBanner: () => jest.fn(),
};

async function createWrapper() {
    const wrapper = mount(await wrapTestComponent('sw-settings-usage-data', {
        sync: true,
    }), {
        global: {
            provide: {
                usageDataService,
            },
            renderStubDefaultSlot: true,
            stubs: {
                'sw-icon': await wrapTestComponent('sw-icon'),
                'sw-usage-data-consent-banner': await wrapTestComponent('sw-usage-data-consent-banner'),
                'sw-page': {
                    template: `
                        <div class="sw-page">
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-search-bar': true,
                'sw-card-view': true,
                'sw-external-link': true,
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-internal-link': true,
                'sw-help-text': true,
                'i18n-t': true,
                i18n: true,
            },
        },
    });

    return wrapper;
}

describe('src/module/sw-settings-usage-data/page/sw-settings-usage-data', () => {
    let wrapper;

    beforeEach(async () => {
        Shopware.State.registerModule('usageData', swSettingsUsageData);
        Shopware.State.registerModule('usageData', swUsageDataConsentBanner);
    });

    it('should show the usage data consent banner', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.getComponent('.sw-usage-data-consent-banner').isVisible()).toBe(true);
        expect(wrapper.find('.sw-usage-data-consent-banner').isVisible()).toBe(true);
    });

    it('should refresh the consent information when created', async () => {
        const getConsentSpy = jest.spyOn(usageDataService, 'getConsent');

        wrapper = await createWrapper();
        await flushPromises();

        expect(getConsentSpy).toHaveBeenCalled();
    });

    it('should not allow the consent banner to be hidden', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const banner = wrapper.getComponent('.sw-usage-data-consent-banner');
        const declineButton = banner.find('.sw-usage-data-consent-banner__decline-button');

        expect(declineButton.exists()).toBe(false);
    });
});
