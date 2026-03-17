import { mount } from '@vue/test-utils';
import { MtModal, MtModalClose, MtModalAction, MtModalTrigger, MtModalRoot } from '@shopware-ag/meteor-component-library';
import SwSettingsServicesGrantPermissionsModal from './index';
import { useShopwareServicesStore } from '../../store/shopware-services.store';
import * as permissionsComposable from '../../composables/permissions';

jest.mock('../../composables/permissions', () => {
    const useShopwareServicesStore = require('../../store/shopware-services.store').useShopwareServicesStore;
    const _reloadPageMock = jest.fn();
    return {
        async grantPermissions() {
            const store = useShopwareServicesStore();
            const revision = store.currentRevision?.revision;
            if (!revision) throw new Error('No revision available');
            await Shopware.Service('shopwareServicesService').acceptRevision(revision);
            _reloadPageMock();
        },
        revokePermissions: jest.fn(),
        _reloadPage: _reloadPageMock,
    };
});

const createWrapper = async () => {
    return mount(SwSettingsServicesGrantPermissionsModal, {
        global: {
            stubs: {
                'mt-modal': MtModal,
                'mt-modal-close': MtModalClose,
                'mt-modal-action': MtModalAction,
                'mt-modal-trigger': MtModalTrigger,
                'mt-modal-root': MtModalRoot,
            },
        },
    });
};

describe('src/module/sw-settings-services/component/sw-settings-services-grant-permissions-modal', () => {
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
    });

    it('can be opened by the pinia store', async () => {
        const shopwareServicesStore = useShopwareServicesStore();
        expect(shopwareServicesStore.revisions).toBeNull();

        const grantPermissionsModal = await createWrapper();
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

        const grantPermissionsModal = await createWrapper();

        shopwareServicesStore.showGrantPermissionsModal = true;
        await flushPromises();
        const modal = grantPermissionsModal.getComponent(MtModal);
        await modal.getComponent(MtModalAction).trigger('click');
        await flushPromises();

        expect(notificationSpy).not.toHaveBeenCalled();
        expect(Shopware.Service('shopwareServicesService').acceptRevision).toHaveBeenCalledWith('2025-06-25');

        expect(permissionsComposable._reloadPage).toHaveBeenCalled();
    });

    it('shows error notification if no revision is available', async () => {
        const shopwareServicesStore = useShopwareServicesStore();
        const notificationStore = Shopware.Store.get('notification');
        const notificationSpy = jest.spyOn(notificationStore, 'createNotification');

        const grantPermissionsModal = await createWrapper();

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
        expect(permissionsComposable._reloadPage).not.toHaveBeenCalled();
    });
});
