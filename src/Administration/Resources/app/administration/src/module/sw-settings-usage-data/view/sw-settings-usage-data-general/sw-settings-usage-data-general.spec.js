import { mount } from '@vue/test-utils';
import SwSettingsUsageDataStoreDataConsent from '../../component/sw-settings-usage-data-store-data-consent';

/**
 * @sw-package data-services
 */

const usageDataService = {
    getConsent: () => jest.fn(),
    acceptConsent: () => jest.fn(),
    revokeConsent: () => jest.fn(),
    hideBanner: () => jest.fn(),
};

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-settings-usage-data-general', {
            sync: true,
        }),
        {
            global: {
                provide: {
                    usageDataService,
                },
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-usage-data-consent-banner': await wrapTestComponent('sw-usage-data-consent-banner'),
                    'sw-extension-component-section': true,
                    'sw-internal-link': true,
                    'i18n-t': {
                        template: '<div class="i18n-stub"><slot></slot></div>',
                    },
                    'sw-help-text': true,
                    'sw-external-link': true,
                },
            },
        },
    );
}

describe('src/module/sw-settings-usage-data/view/sw-settings-usage-data-general', () => {
    let wrapper;

    it('shows the store data consent card', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.findComponent(SwSettingsUsageDataStoreDataConsent).exists()).toBe(true);
    });

    it('should refresh the consent information when created', async () => {
        const getConsentSpy = jest.spyOn(usageDataService, 'getConsent');

        wrapper = await createWrapper();
        await flushPromises();

        expect(getConsentSpy).toHaveBeenCalled();
    });
});
