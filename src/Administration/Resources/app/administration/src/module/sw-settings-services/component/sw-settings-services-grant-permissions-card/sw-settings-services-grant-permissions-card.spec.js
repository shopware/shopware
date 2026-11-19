import { mount } from '@vue/test-utils';
import SwSettingsServicesGrantPermissionsCard from './index';
import { useShopwareServicesStore } from '../../store/shopware-services.store';
import * as permissionsComposable from '../../composables/permissions';

jest.mock('../../composables/permissions', () => {
    const useShopwareServicesStore = require('../../store/shopware-services.store').useShopwareServicesStore;
    const SERVICE_CONSENT_NAME = require('../../store/shopware-services.store').SERVICE_CONSENT_NAME;
    const _reloadPageMock = jest.fn();
    return {
        async grantPermissions() {
            const store = useShopwareServicesStore();
            const revision = store.currentRevision?.revision;
            if (!revision) throw new Error('No revision available');
            await Shopware.Service('consentApiService').accept(SERVICE_CONSENT_NAME, revision);
            _reloadPageMock();
        },
        revokePermissions: jest.fn(),
        _reloadPage: _reloadPageMock,
    };
});

describe('src/module/sw-settings-services/component/sw-settings-services-permissions-card', () => {
    beforeAll(() => {
        Shopware.Service().register('consentApiService', () => ({
            accept: jest.fn(),
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
        expect(Shopware.Service('consentApiService').accept).toHaveBeenCalledWith('service_consent', '2025-06-25');
        expect(permissionsComposable._reloadPage).toHaveBeenCalled();
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
        expect(permissionsComposable._reloadPage).not.toHaveBeenCalled();
    });
});
