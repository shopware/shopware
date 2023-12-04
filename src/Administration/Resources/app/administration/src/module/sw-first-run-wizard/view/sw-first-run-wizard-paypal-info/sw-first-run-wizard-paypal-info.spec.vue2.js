/**
 * @internal
 *
 * @package services-settings
 */

import { shallowMount } from '@vue/test-utils_v2';
import swFirstRunWizardPaypalInfo from 'src/module/sw-first-run-wizard/view/sw-first-run-wizard-paypal-info';
import 'src/app/component/base/sw-container';

Shopware.Component.register('sw-first-run-wizard-paypal-info', swFirstRunWizardPaypalInfo);

const extensionStoreActionService = {
    downloadExtension: jest.fn(() => Promise.resolve()),
    installExtension: jest.fn(() => Promise.resolve()),
};

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-first-run-wizard-paypal-info'), {
        stubs: {
            'sw-container': await Shopware.Component.build('sw-container'),
            'sw-icon': true,
        },

        provide: {
            extensionStoreActionService,
        },
    });
}

describe('src/module/sw-first-run-wizard-paypal-info', () => {
    it('should download and install the PayPal plugin', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.installPayPal();

        expect(extensionStoreActionService.downloadExtension).toHaveBeenCalled();
        expect(extensionStoreActionService.installExtension).toHaveBeenCalled();
    });
});
