import { mount } from '@vue/test-utils';
import AxiosMockAdapter from "axios-mock-adapter";
import { MtModal, MtModalRoot, MtModalClose, MtModalAction } from '@shopware-ag/meteor-component-library';
import ShopwareServicesService from "../../service/shopware-services.service";
import createHTTPClient from "../../../../core/factory/http.factory";
import createLoginService from "../../../../core/service/login.service";
import SystemConfigApiService from "../../../../core/service/api/system-config.api.service";

describe('src/module/sw-settings-services/component/sw-settings-services-deactivate-modal', () => {
    let axiosMock;
    const location = window.location;

    beforeAll(() => {
        const httpClient = createHTTPClient(Shopware.Context.api);
        const loginService = createLoginService(httpClient, Shopware.Context.api);

        axiosMock = new AxiosMockAdapter(httpClient);

        Shopware.Service().register(
            'shopwareServicesService',
            () => new ShopwareServicesService(
                httpClient,
                loginService,
                new SystemConfigApiService(httpClient, loginService),
            )
        )
    });

    beforeEach(() => {
        Object.defineProperty(window, 'location', {
            configurable: true,
            value: { reload: jest.fn() },
        });
    })

    afterEach(() => {
        Object.defineProperty(window, 'location', { configurable: true, value: location });
    })

    it ('can be opened and closed', async () => {
        const deactivateModal = await mount(
            await wrapTestComponent(
                'sw-settings-services-deactivate-modal',
                { sync: true, },
            ),
            {
                global: {
                    stubs: {
                        MtModalRoot,
                        MtModal,
                    }
                }
            }
        )
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

        const deactivateModal = await mount(
            await wrapTestComponent(
                'sw-settings-services-deactivate-modal',
                { sync: true, },
            ),
            {
                global: {
                    stubs: {
                        MtModalRoot,
                        MtModal,
                    }
                }
            }
        )
        await flushPromises();

        await deactivateModal.get('button').trigger('click');

        axiosMock.onPost('services/disable').replyOnce(204);
        axiosMock.onGet('_action/system-config', {
            params: {
                domain: 'core.services',
                salesChannelId: null,
            }
        }).replyOnce(200, {});

        const modal = deactivateModal.getComponent(MtModal);
        await modal.getComponent(MtModalAction).trigger('click');
        await flushPromises();

        expect(notificationSpy).not.toHaveBeenCalled();
        expect(window.location.reload).toHaveBeenCalled();
    });

    it('shows notification if request fails', async () => {
        const notificationStore = Shopware.Store.get('notification');
        const notificationSpy = jest.spyOn(notificationStore, 'createNotification');

        const deactivateModal = await mount(
            await wrapTestComponent(
                'sw-settings-services-deactivate-modal',
                { sync: true, },
            ),
            {
                global: {
                    stubs: {
                        MtModalRoot,
                        MtModal,
                    }
                }
            }
        )
        await flushPromises();

        await deactivateModal.get('button').trigger('click');

        const modal = deactivateModal.getComponent(MtModal);
        await modal.getComponent(MtModalAction).trigger('click');
        await flushPromises();

        expect(notificationSpy).toHaveBeenCalled();
        expect(notificationSpy).toHaveBeenCalledWith({
            title: 'global.default.error',
            variant: 'critical',
            message: 'Request failed with status code 404',
            autoClose: false,
        });
    })
});