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

const createWrapper = async (props = {}) => {
    return mount(SwSettingsServicesRevokePermissionsModal, {
        props,
        global: {
            stubs: {
                'mt-modal': MtModal,
                'mt-modal-close': MtModalClose,
                'mt-modal-action': MtModalAction,
                'mt-modal-trigger': MtModalTrigger,
                'mt-modal-root': MtModalRoot,
                'mt-icon': {
                    template: '<span :class="$attrs.class" />',
                },
            },
        },
    });
};

const createContentWrapper = async (props = {}) => {
    return mount(SwSettingsServicesRevokePermissionsModal, {
        props,
        global: {
            stubs: {
                'mt-modal-root': {
                    template: '<div><slot /></div>',
                },
                'mt-modal': {
                    template: '<div><slot /><slot name="footer" /></div>',
                },
                'mt-modal-close': {
                    template: '<button><slot /></button>',
                },
                'mt-modal-action': {
                    template: '<button><slot /></button>',
                },
                'mt-modal-trigger': {
                    template: '<button><slot /></button>',
                },
                'mt-icon': {
                    template: '<span :class="$attrs.class" />',
                },
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

    it('shows services that keep their Shopware Account permissions', async () => {
        const revokePermissionsModal = await createContentWrapper({
            servicesWithAccountRequirement: [
                {
                    name: 'account-service',
                    label: 'Account Service',
                },
                {
                    name: 'another-account-service',
                    label: 'Another Account Service',
                },
            ],
        });
        await flushPromises();

        expect(
            revokePermissionsModal.find('.sw-settings-services-revoke-permissions-modal__account-requirement-info').exists(),
        ).toBe(true);
        expect(revokePermissionsModal.text()).toContain('sw-settings-services.revoke-permissions-modal.p-3');
        expect(
            revokePermissionsModal.findAll('.sw-settings-services-revoke-permissions-modal__services-list li'),
        ).toHaveLength(2);
        expect(revokePermissionsModal.text()).toContain('Account Service');
        expect(revokePermissionsModal.text()).toContain('Another Account Service');
    });

    it('does not show the Shopware Account permissions notice without matching services', async () => {
        const revokePermissionsModal = await createContentWrapper();
        await flushPromises();

        expect(
            revokePermissionsModal.find('.sw-settings-services-revoke-permissions-modal__account-requirement-info').exists(),
        ).toBe(false);
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
