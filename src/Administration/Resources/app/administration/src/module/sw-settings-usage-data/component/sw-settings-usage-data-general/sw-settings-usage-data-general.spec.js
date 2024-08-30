import { mount } from '@vue/test-utils';

/**
 * @package data-services
 */

const usageDataService = {
    getConsent: () => jest.fn(),
    acceptConsent: () => jest.fn(),
    revokeConsent: () => jest.fn(),
    hideBanner: () => jest.fn(),
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-settings-usage-data-general', {
        sync: true,
    }), {
        global: {
            provide: {
                usageDataService,
            },
            renderStubDefaultSlot: true,
            stubs: {
                'sw-usage-data-consent-banner': await wrapTestComponent('sw-usage-data-consent-banner'),
                'sw-extension-component-section': true,
                'sw-icon': true,
                'sw-internal-link': true,
                'i18n-t': true,
                'sw-help-text': true,
                'sw-external-link': true,
                'sw-button': true,
            },
        },
    });
}

describe('src/module/sw-settings-usage-data/component/sw-settings-usage-data-general', () => {
    let wrapper;

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
