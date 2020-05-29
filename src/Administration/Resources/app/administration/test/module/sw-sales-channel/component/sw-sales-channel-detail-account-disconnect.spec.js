import { shallowMount } from '@vue/test-utils';
import state from 'src/module/sw-sales-channel/state/salesChannel.store';
import 'src/module/sw-sales-channel/component/sw-sales-channel-detail-account-disconnect';

Shopware.State.registerModule('swSalesChannel', state);

describe('src/module/sw-sales-channel/component/sw-sales-channel-detail-account-disconnect', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.Service().register('googleShoppingService', () => {
            return {
                getAccountProfile: () => {
                    return Promise.resolve();
                },

                getMerchantInfo: () => {
                    return Promise.resolve();
                },

                getMerchantStatus: () => {
                    return Promise.resolve();
                }
            };
        });
    });

    beforeEach(() => {
        wrapper = shallowMount(
            Shopware.Component.build(
                'sw-sales-channel-detail-account-disconnect'
            ),
            {
                store: Shopware.State._store,

                propsData: {
                    salesChannel: {}
                },

                stubs: {
                    'sw-button': true,
                    'sw-avatar': true,
                    'sw-alert': true,
                    'sw-label': true,
                    'sw-icon': true,
                    'sw-color-badge': true,
                    'sw-sales-channel-detail-protect-link': true
                },

                mocks: {
                    $tc: key => key
                }
            }
        );
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should emit an event when execute onDisconnectToGoogle()', () => {
        wrapper.vm.onDisconnectToGoogle();

        expect(wrapper.emitted()).toEqual({ 'on-disconnect-to-google': [[]] });
    });

    it('should show error notification when getAccountProfile() failly', async () => {
        wrapper.setProps({ salesChannel: { id: '' } });

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.$nextTick();

        await wrapper.vm.getAccountProfile();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            title: 'global.default.error',
            message: 'global.notification.notificationFetchErrorMessage'
        });

        wrapper.vm.createNotificationError.mockRestore();
    });
});
