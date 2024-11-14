/**
 * @internal
 *
 * @package checkout
 */

import { mount } from '@vue/test-utils';

const extensionStoreActionService = {
    downloadExtension: jest.fn(() => Promise.resolve()),
    installExtension: jest.fn(() => Promise.resolve()),
    activateExtension: jest.fn(() => Promise.resolve()),
};

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-first-run-wizard-paypal-info', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-container': await wrapTestComponent('sw-container'),
                    'sw-icon': true,
                    'sw-alert': true,
                    'sw-loader': true,
                },
                provide: {
                    extensionStoreActionService,
                },
            },
        },
    );
}

describe('src/module/sw-first-run-wizard-paypal-info', () => {
    const originalWindowLocation = window.location;

    beforeAll(() => {
        Object.defineProperty(window, 'location', {
            configurable: true,
            value: { reload: jest.fn() },
        });
    });

    afterAll(() => {
        Object.defineProperty(window, 'location', {
            configurable: true,
            value: originalWindowLocation,
        });
    });

    it('should download and install the PayPal plugin', async () => {
        await createWrapper();

        expect(extensionStoreActionService.downloadExtension).toHaveBeenCalledTimes(1);
        expect(extensionStoreActionService.installExtension).toHaveBeenCalledTimes(1);
    });

    it('should activate the PayPal plugin', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.activatePayPalAndRedirect();
        await flushPromises();

        expect(extensionStoreActionService.activateExtension).toHaveBeenCalled();
        expect(wrapper.vm.pluginInstallationFailed).toBe(false);
    });
});
