import { MtModalAction, MtModalTrigger } from '@shopware-ag/meteor-component-library';
import SwSettingsServicesGrantPermissionsCard from '../../../component/sw-settings-services-grant-permissions-card';
import SwSettingsServicesRevokePermissionsModal from '../../../component/sw-settings-services-revoke-permissions-modal';
import * as permissionsComposable from '../../../composables/permissions';
import { mountPage, registerServiceMocks, resetServicesConfig } from './create-wrapper';

jest.mock('../../../composables/permissions', () => {
    const useShopwareServicesStore = require('../../../store/shopware-services.store').useShopwareServicesStore;
    const _reloadPageMock = jest.fn();
    return {
        async grantPermissions() {
            const store = useShopwareServicesStore();
            const revision = store.currentRevision?.revision;
            if (!revision) throw new Error('No revision available');
            await Shopware.Service('shopwareServicesService').acceptRevision(revision);
            _reloadPageMock();
        },
        async revokePermissions() {
            await Shopware.Service('shopwareServicesService').revokePermissions();
            _reloadPageMock();
        },
        _reloadPage: _reloadPageMock,
    };
});

describe('/src/module/sw-setting-services/page/sw-settings-services-index permissions', () => {
    beforeAll(registerServiceMocks);
    beforeEach(resetServicesConfig);

    it('can grant permissions', async () => {
        Shopware.Service('shopwareServicesService').getServicesContext.mockImplementationOnce(async () => ({
            disabled: false,
            permissionConsent: null,
        }));

        const page = await mountPage();
        await flushPromises();

        expect(page.findComponent(SwSettingsServicesRevokePermissionsModal).exists()).toBe(false);

        const grantPermissionsCard = page.getComponent(SwSettingsServicesGrantPermissionsCard);

        await grantPermissionsCard.get('.mt-button--primary').trigger('click');
        await flushPromises();

        expect(permissionsComposable._reloadPage).toHaveBeenCalled();
    });

    it('can revoke permissions', async () => {
        const page = await mountPage();
        await flushPromises();

        const revokePermissionsModal = page.getComponent(SwSettingsServicesRevokePermissionsModal);

        await revokePermissionsModal.getComponent(MtModalTrigger).trigger('click');
        await revokePermissionsModal.getComponent(MtModalAction).trigger('click');
        await flushPromises();

        expect(permissionsComposable._reloadPage).toHaveBeenCalled();
    });
});
