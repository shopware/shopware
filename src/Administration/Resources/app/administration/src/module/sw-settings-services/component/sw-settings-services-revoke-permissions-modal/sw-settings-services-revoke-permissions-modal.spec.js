import { mount } from '@vue/test-utils';
import { MtModal, MtModalClose, MtModalAction, MtModalTrigger, MtModalRoot } from '@shopware-ag/meteor-component-library';
import SwSettingsServicesRevokePermissionsModal from './index';
import * as permissionsComposable from '../../composables/permissions';

jest.mock('../../composables/permissions', () => {
    const _reloadPageMock = jest.fn();
    return {
        grantPermissions: jest.fn(),
        async revokePermissions() {
            await Shopware.Service('shopwareServicesService').revokePermissions();
            _reloadPageMock();
        },
        _reloadPage: _reloadPageMock,
    };
});

const createWrapper = async () => {
    return mount(SwSettingsServicesRevokePermissionsModal, {
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

describe('src/module/sw-settings-services/component/sw-settings-services-revoke-permissions-modal', () => {
    beforeAll(() => {
        Shopware.Service().register('shopwareServicesService', () => ({
            revokePermissions: jest.fn(),
        }));
    });

    it('can be opened and closed', async () => {
        const revokePermissionsModal = await createWrapper();
        await flushPromises();

        let modal = revokePermissionsModal.getComponent(MtModal);
        expect(modal.findComponent(MtModalClose).exists()).toBe(false);

        const openButton = revokePermissionsModal.get('button');

        expect(openButton.text()).toBe('sw-settings-services.revoke-permissions-modal.label-button-revoke-permissions');

        await openButton.trigger('click');

        modal = revokePermissionsModal.getComponent(MtModal);
        expect(modal.findComponent(MtModalClose).exists()).toBe(true);

        await modal.getComponent(MtModalClose).trigger('click');

        modal = revokePermissionsModal.getComponent(MtModal);
        expect(modal.findComponent(MtModalClose).exists()).toBe(false);
    });

    it('revokes permissions', async () => {
        const notificationStore = Shopware.Store.get('notification');
        const notificationSpy = jest.spyOn(notificationStore, 'createNotification');

        Shopware.Service('shopwareServicesService').revokePermissions.mockImplementationOnce(() => ({
            permissionConsent: null,
            enabled: true,
        }));

        const revokePermissionsModal = await createWrapper();
        await flushPromises();

        await revokePermissionsModal.get('button').trigger('click');
        await revokePermissionsModal.getComponent(MtModal).getComponent(MtModalAction).trigger('click');
        await flushPromises();

        expect(notificationSpy).not.toHaveBeenCalled();
        expect(Shopware.Service('shopwareServicesService').revokePermissions).toHaveBeenCalled();
        expect(permissionsComposable._reloadPage).toHaveBeenCalled();
    });

    it('shows notification if permissions request fails', async () => {
        const notificationStore = Shopware.Store.get('notification');
        const notificationSpy = jest.spyOn(notificationStore, 'createNotification');

        Shopware.Service('shopwareServicesService').revokePermissions.mockImplementationOnce(() => {
            throw new Error('Revoke Permissions failed');
        });

        const revokePermissionsModal = await createWrapper();
        await flushPromises();

        await revokePermissionsModal.get('button').trigger('click');
        await revokePermissionsModal.getComponent(MtModal).getComponent(MtModalAction).trigger('click');
        await flushPromises();

        expect(notificationSpy).toHaveBeenCalledWith({
            variant: 'critical',
            title: 'global.default.error',
            message: 'Revoke Permissions failed',
        });
        expect(revokePermissionsModal.emitted('service-permissions-revoked')).toBeUndefined();
        expect(permissionsComposable._reloadPage).not.toHaveBeenCalled();
    });
});
