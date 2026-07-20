import { mount } from '@vue/test-utils';
import SwSettingsUsageDataStoreDataConsent from '../../component/sw-settings-usage-data-store-data-consent';

/**
 * @sw-package data-services
 */

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-settings-usage-data-general', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
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
});
