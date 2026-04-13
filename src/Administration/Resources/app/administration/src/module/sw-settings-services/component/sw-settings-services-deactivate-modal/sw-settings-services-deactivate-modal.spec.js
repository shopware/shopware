import { mount } from '@vue/test-utils';
import { MtModal, MtModalClose, MtModalAction, MtModalTrigger, MtModalRoot } from '@shopware-ag/meteor-component-library';
import SwSettingsServicesDeactivateModal from './index';

const createWrapper = async () => {
    return mount(SwSettingsServicesDeactivateModal, {
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

describe('src/module/sw-settings-services/component/sw-settings-services-deactivate-modal', () => {
    beforeAll(() => {
        Shopware.Service().register('shopwareServicesService', () => ({
            disableAllServices: jest.fn(),
        }));
    });

    it('can be opened and closed', async () => {
        const deactivateModal = await createWrapper();
        await flushPromises();

        let modal = deactivateModal.getComponent(MtModal);
        expect(modal.findComponent(MtModalClose).exists()).toBe(false);

        const openButton = deactivateModal.get('button');

        expect(openButton.text()).toBe('sw-settings-services.general.deactivate');

        await openButton.trigger('click');

        modal = deactivateModal.getComponent(MtModal);
        expect(modal.findComponent(MtModalClose).exists()).toBe(true);

        await modal.getComponent(MtModalClose).trigger('click');

        modal = deactivateModal.getComponent(MtModal);
        expect(modal.findComponent(MtModalClose).exists()).toBe(false);
    });

    it('sends deactivation call', async () => {
        const notificationStore = Shopware.Store.get('notification');
        const notificationSpy = jest.spyOn(notificationStore, 'createNotification');

        Shopware.Service('shopwareServicesService').disableAllServices.mockImplementationOnce(() => ({
            disabled: true,
        }));

        const deactivateModal = await createWrapper();
        await flushPromises();

        jest.spyOn(deactivateModal.vm, '_reloadPage').mockImplementation(() => {});

        await deactivateModal.get('button').trigger('click');
        const modal = deactivateModal.getComponent(MtModal);
        await modal.getComponent(MtModalAction).trigger('click');
        await flushPromises();

        expect(notificationSpy).not.toHaveBeenCalled();
        expect(deactivateModal.vm._reloadPage).toHaveBeenCalled();
    });

    it('shows notification if request fails', async () => {
        const notificationStore = Shopware.Store.get('notification');
        const notificationSpy = jest.spyOn(notificationStore, 'createNotification');

        Shopware.Service('shopwareServicesService').disableAllServices.mockImplementationOnce(() => {
            throw new Error('Deactivation failed');
        });

        const deactivateModal = await createWrapper();
        await flushPromises();

        await deactivateModal.get('button').trigger('click');
        const modal = deactivateModal.getComponent(MtModal);
        await modal.getComponent(MtModalAction).trigger('click');
        await flushPromises();

        expect(notificationSpy).toHaveBeenCalled();
        expect(notificationSpy).toHaveBeenCalledWith({
            title: 'global.default.error',
            variant: 'critical',
            message: 'Deactivation failed',
            autoClose: false,
        });
    });
});
