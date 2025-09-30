import { mount } from '@vue/test-utils';
import { MtModal, MtModalClose, MtModalAction } from '@shopware-ag/meteor-component-library';
import SwSettingsServicesGrantPermissionsModal from './index';
import { useShopwareServicesStore } from '../../store/shopware-services.store';

describe('src/module/sw-settings-services/component/sw-settings-services-grant-permissions-modal', () => {
    let originalLocation;

    beforeAll(() => {
        Shopware.Service().register('serviceRegistryClient', () => ({
            getCurrentRevision: jest.fn(async () => ({
                'latest-revision': '2025-06-25',
                'available-revisions': [
                    {
                        revision: '2025-06-25',
                        links: {
                            'feedback-url': 'https://shopware.com/feedback',
                            'docs-url': 'https://docs.shopware.com/services',
                            'tos-url': 'https://shopware.com/agb',
                        },
                    },
                ],
            })),
        }));

        Shopware.Service().register('shopwareServicesService', () => ({
            acceptRevision: jest.fn(),
        }));

        originalLocation = window.location;

        Object.defineProperty(window, 'location', { configurable: true, value: { reload: jest.fn() } });
    });

    afterAll(() => {
        Object.defineProperty(window, 'location', { configurable: true, value: originalLocation });
    });

    it('can be opened by the pinia store', async () => {
        const shopwareServicesStore = useShopwareServicesStore();
        expect(shopwareServicesStore.revisions).toBeNull();

        const grantPermissionsModal = await mount(SwSettingsServicesGrantPermissionsModal);
        const modal = grantPermissionsModal.getComponent(MtModal);

        expect(modal.findComponent(MtModalClose).exists()).toBe(false);

        shopwareServicesStore.showGrantPermissionsModal = true;
        await flushPromises();

        expect(shopwareServicesStore.revisions).toEqual({
            'latest-revision': '2025-06-25',
            'available-revisions': [
                {
                    revision: '2025-06-25',
                    links: {
                        'feedback-url': 'https://shopware.com/feedback',
                        'docs-url': 'https://docs.shopware.com/services',
                        'tos-url': 'https://shopware.com/agb',
                    },
                },
            ],
        });

        await modal.getComponent(MtModalClose).trigger('click');

        expect(modal.findComponent(MtModalClose).exists()).toBe(false);
        expect(shopwareServicesStore.showGrantPermissionsModal).toBe(false);
    });

    it('sends grant permissions request', async () => {
        const shopwareServicesStore = useShopwareServicesStore();
        const notificationStore = Shopware.Store.get('notification');
        const notificationSpy = jest.spyOn(notificationStore, 'createNotification');

        const grantPermissionsModal = await mount(SwSettingsServicesGrantPermissionsModal);

        shopwareServicesStore.showGrantPermissionsModal = true;
        await flushPromises();
        const modal = grantPermissionsModal.getComponent(MtModal);
        await modal.getComponent(MtModalAction).trigger('click');
        await flushPromises();

        expect(notificationSpy).not.toHaveBeenCalled();
        expect(Shopware.Service('shopwareServicesService').acceptRevision).toHaveBeenCalledWith('2025-06-25');

        expect(window.location.reload).toHaveBeenCalled();
    });

    it('shows error notification if no revision is available', async () => {
        const shopwareServicesStore = useShopwareServicesStore();
        const notificationStore = Shopware.Store.get('notification');
        const notificationSpy = jest.spyOn(notificationStore, 'createNotification');

        const grantPermissionsModal = await mount(SwSettingsServicesGrantPermissionsModal);

        shopwareServicesStore.showGrantPermissionsModal = true;
        await flushPromises();
        shopwareServicesStore.revisions = null;

        const modal = grantPermissionsModal.getComponent(MtModal);
        await modal.getComponent(MtModalAction).trigger('click');
        await flushPromises();

        expect(notificationSpy).toHaveBeenCalledWith({
            variant: 'critical',
            title: 'global.default.error',
            message: 'No revision available',
        });
        expect(Shopware.Service('shopwareServicesService').acceptRevision).not.toHaveBeenCalled();
        expect(window.location.reload).not.toHaveBeenCalled();
    });
});
