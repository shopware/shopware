import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/sw-sales-channel-google-authentication';
import 'src/app/component/base/sw-card';
import 'src/app/component/base/sw-button-process';
import 'src/app/component/base/sw-button';
import state from 'src/module/sw-sales-channel/state/salesChannel.store';

Shopware.State.registerModule('swSalesChannel', state);

describe('module/sw-sales-channel/component/sw-sales-channel-google-authentication', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.Service().register('googleShoppingService', () => {
            return {
                disconnectGoogle: (salesChannelId) => {
                    if (salesChannelId) {
                        return Promise.resolve();
                    }

                    // eslint-disable-next-line prefer-promise-reject-errors
                    return Promise.reject({
                        response: {
                            data: {
                                errors: [
                                    {
                                        code: 'This is an error code',
                                        detail: 'This is an detailed error message'
                                    }
                                ]
                            }
                        }
                    });
                }
            };
        });
    });

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-sales-channel-google-authentication'), {
            store: Shopware.State._store,
            stubs: {
                'sw-card': Shopware.Component.build('sw-card'),
                'sw-button-process': Shopware.Component.build('sw-button-process'),
                'sw-button': Shopware.Component.build('sw-button'),
                'sw-avatar': true,
                'sw-alert': true,
                'sw-icon': true
            },
            mocks: {
                $tc: (translationPath) => translationPath,
                $route: { name: 'sw.sales.channel.detail.base.step-2' },
                $router: { push: () => {} }
            },
            propsData: {
                salesChannel: { id: 1 }
            }
        });

        Shopware.State.commit('swSalesChannel/setGoogleShoppingAccount', {
            name: 'JohnDode',
            email: 'test@abc.com',
            picture: 'image.jpg'
        });
    });

    afterEach(() => {
        wrapper.destroy();
    });


    it('onDisconnectAccount should be called when disconnect button is clicked', () => {
        const button = wrapper.find('button');
        const spy = jest.spyOn(wrapper.vm, 'onDisconnectAccount');

        button.trigger('click');

        expect(spy).toHaveBeenCalled();
    });

    it('sw-button-process should update success status when disconnect onDisconnectAccount is successful', async () => {
        await wrapper.vm.onDisconnectAccount();

        const button = wrapper.find('button');

        expect(button.vm.processSuccess).toBeTruthy();
    });

    it('createNotificationError should be called when onDisconnectAccount is failed', async () => {
        wrapper.setProps({ salesChannel: { id: '' } });
        wrapper.setMethods({ createNotificationError: jest.fn() });

        const spy = jest.spyOn(wrapper.vm, 'createNotificationError');

        await wrapper.vm.$nextTick();
        await wrapper.vm.onDisconnectAccount();

        expect(spy).toHaveBeenCalled();
    });
});
