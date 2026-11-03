import { mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import type { Component } from 'vue';
import { serviceRegistryClient, shopwareServicesService } from 'src/app/service-names';
import { useShopwareServicesStore } from '../dependencies';
import SwSettingsServicesIndex from '../private';

/**
 * @sw-package framework
 */

const { MtBanner, MtButton, MtModal, MtModalAction, MtModalClose, MtModalRoot, MtModalTrigger } = jest.requireActual<
    Record<string, Component>
>('@shopware-ag/meteor-component-library');

/**
 * The fake services service the page talks to; each test may change what its calls return.
 *
 * @private
 */
export const servicesService = {
    getInstalledServices: jest.fn(),
    getServicesContext: jest.fn(),
    enableAllServices: jest.fn(),
    acceptRevision: jest.fn(),
    revokePermissions: jest.fn(),
};

/**
 * Two installed services, the services enabled and their permissions granted, as the page loads them by default.
 *
 * @private
 */
export function arrangeInstalledServices(): void {
    servicesService.getInstalledServices.mockResolvedValue([
        { id: 'service-id', active: true, name: 'first-service-name', label: 'First Service', requirements: [] },
        { id: 'service-id-2', active: true, name: 'second-service-name', label: 'Second Service', requirements: [] },
    ]);
    servicesService.getServicesContext.mockResolvedValue({
        disabled: false,
        permissionsConsent: { identifier: 'revision-id', revision: '2025-06-25', consentingUserId: 'user-id' },
    });
    servicesService.enableAllServices.mockResolvedValue({ disabled: false, permissionsConsent: null });
}

/**
 * Mounts the services page with the real page components and the service cards stubbed.
 *
 * @private
 */
export async function mountServicesPage(): Promise<VueWrapper> {
    if (!Shopware.Service(shopwareServicesService)) {
        /** The fake services service, for the tests only. @private */
        Shopware.Service().register(shopwareServicesService, () => servicesService as never);
        /** The fake registry client, for the tests only. @private */
        Shopware.Service().register(
            serviceRegistryClient,
            () =>
                ({
                    getCurrentRevision: jest.fn(() =>
                        Promise.resolve({
                            'latest-revision': '2025-06-25',
                            'available-revisions': [
                                {
                                    revision: '2025-06-25',
                                    links: { 'feedback-url': 'feedback', 'docs-url': 'docs', 'tos-url': 'tos' },
                                },
                            ],
                        }),
                    ),
                }) as never,
        );
    }

    const pinia = createPinia();
    setActivePinia(pinia);
    useShopwareServicesStore();

    const page = mount((await SwSettingsServicesIndex.load()).default, {
        global: {
            stubs: {
                'sw-page': {
                    template: '<div class="sw-page"><slot name="smart-bar-header" /><slot name="content" /></div>',
                },
                'sw-settings-services-service-card': true,
                'mt-modal': MtModal,
                'mt-modal-close': MtModalClose,
                'mt-modal-action': MtModalAction,
                'mt-modal-trigger': MtModalTrigger,
                'mt-modal-root': MtModalRoot,
                'sw-extension-component-section': true,
            },
            plugins: [pinia],
        },
    });
    await flushPromises();

    return page;
}

/**
 * Counts the service cards the page shows.
 *
 * @private
 */
export function countServiceCards(page: VueWrapper): number {
    return page.findAll('sw-settings-services-service-card-stub').length;
}

/**
 * Clicks the button of the banner that offers to activate the deactivated services.
 *
 * @private
 */
export async function clickActivateServices(page: VueWrapper): Promise<void> {
    await page.getComponent(MtBanner).getComponent(MtButton).trigger('click');
    await flushPromises();
}

/**
 * Returns the variant and text of the banner the page shows.
 *
 * @private
 */
export function getBanner(page: VueWrapper): { variant: string; text: string } {
    const banner = page.getComponent(MtBanner);

    return { variant: (banner.props() as { variant: string }).variant, text: banner.text() };
}
