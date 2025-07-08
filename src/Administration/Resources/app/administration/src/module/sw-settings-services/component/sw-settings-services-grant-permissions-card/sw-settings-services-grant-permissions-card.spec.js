import { mount } from '@vue/test-utils';
import SwSettingsServicesGrantPermissionsCard from './index';
import { useShopwareServicesStore } from '../../store/shopware-services.store';

describe('src/module/sw-settings-services/component/sw-settings-services-permissions-card', () => {
    beforeAll(() => {
        Shopware.Service().register('shopwareServicesService', () => ({
            acceptRevision: jest.fn(() => ({
                disabled: false,
                permissionsConsent: {
                    identifier: 'revision-id',
                    revision: '2025-06-25',
                    consentingUserId: 'user-id',
                    grantedAt: '2025-07-08',
                },
            })),
        }));
    });

    it('has a linkt to docs page', async () => {
        const permissionsCard = await mount(SwSettingsServicesGrantPermissionsCard, {
            props: {
                docsLink: 'https://docs.shopware.com/en/shopware-6-en/shopware-services',
            },
        });

        expect(permissionsCard.get('a').attributes('href')).toBe(
            'https://docs.shopware.com/en/shopware-6-en/shopware-services',
        );
    });

    it('send permissions accepted request', async () => {
        const notificationStore = Shopware.Store.get('notification');
        const notificationSpy = jest.spyOn(notificationStore, 'createNotification');

        const shopwareServicesStore = useShopwareServicesStore();
        shopwareServicesStore.revisions = {
            'latest-revision': '2025-06-25',
            'available-revisions': [
                {
                    revision: '2025-06-25',
                    links: {},
                },
            ],
        };

        const permissionsCard = await mount(SwSettingsServicesGrantPermissionsCard, {
            props: {
                docsLink: 'https://docs.shopware.com/en/shopware-6-en/shopware-services',
            },
        });

        await permissionsCard.get('.mt-button--primary').trigger('click');
        await flushPromises();

        expect(notificationSpy).not.toHaveBeenCalled();
        expect(permissionsCard.emitted('service-permissions-granted')).toHaveLength(1);
        expect(Shopware.Service('shopwareServicesService').acceptRevision).toHaveBeenCalledWith('2025-06-25');
        expect(shopwareServicesStore.config).toEqual({
            disabled: false,
            permissionsConsent: {
                identifier: 'revision-id',
                revision: '2025-06-25',
                consentingUserId: 'user-id',
                grantedAt: '2025-07-08',
            },
        });
    });

    it('shows error notification if no revision is available', async () => {
        const notificationStore = Shopware.Store.get('notification');
        const notificationSpy = jest.spyOn(notificationStore, 'createNotification');

        const shopwareServicesStore = useShopwareServicesStore();
        shopwareServicesStore.revisions = null;

        const permissionsCard = await mount(SwSettingsServicesGrantPermissionsCard, {
            props: {
                docsLink: 'https://docs.shopware.com/en/shopware-6-en/shopware-services',
            },
        });

        await permissionsCard.get('.mt-button--primary').trigger('click');
        await flushPromises();

        expect(notificationSpy).toHaveBeenCalledWith({
            variant: 'critical',
            title: 'global.default.error',
            message: 'No revision available',
        });
        expect(permissionsCard.emitted('service-permissions-granted')).toBeUndefined();
    });
});
