import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/sw-sales-channel-google-introduction';

import state from 'src/module/sw-sales-channel/state/salesChannel.store';

Shopware.State.registerModule('swSalesChannel', state);

describe('module/sw-sales-channel/component/sw-sales-channel-google-introduction', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.Service().register('googleAuthService', () => {
            return {
                load: (option) => {
                    if (option) {
                        return Promise.resolve();
                    }

                    // eslint-disable-next-line prefer-promise-reject-errors
                    return Promise.reject({
                        error: 'This is an error code',
                        details: 'This is an detailed error message'
                    });
                },
                getAuthCode: () => Promise.resolve('1234')
            };
        });

        Shopware.Service().register('systemConfigApiService', () => {
            return {
                getValues: (value) => {
                    if (value) {
                        return Promise.resolve({ 'core.googleShopping.clientId': '1234' });
                    }

                    // eslint-disable-next-line prefer-promise-reject-errors
                    return Promise.reject({
                        error: 'This is an error code',
                        details: 'This is an detailed error message'
                    });
                }
            };
        });

        Shopware.Service().register('googleShoppingService', () => {
            const response = {
                data: {
                    data: {
                        name: 'JohnDode',
                        email: 'test@abc.com',
                        picture: 'image.jpg'
                    }
                }
            };

            return {
                connectGoogle: (salesChannelId, code) => {
                    if (salesChannelId && code) {
                        return Promise.resolve(response);
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
        wrapper = shallowMount(Shopware.Component.build('sw-sales-channel-google-introduction'), {
            store: Shopware.State._store,
            stubs: {
                'sw-sales-channel-detail-protect-link': true,
                'sw-card-section': true
            },
            mocks: {
                $tc: (translationPath) => translationPath,
                $route: { name: 'sw.sales.channel.detail.base.step-1' },
                $router: { push: () => {} }
            },
            propsData: {
                salesChannel: { id: 1 }
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('googleShoppingAccount should update correctly when onClickConnect is successful', async () => {
        let googleShoppingAccount;

        googleShoppingAccount = Shopware.State.get('swSalesChannel').googleShoppingAccount;
        expect(googleShoppingAccount).toBeNull();

        await wrapper.vm.onClickConnect();

        googleShoppingAccount = Shopware.State.get('swSalesChannel').googleShoppingAccount;
        expect(googleShoppingAccount).toStrictEqual({
            name: 'JohnDode',
            email: 'test@abc.com',
            picture: 'image.jpg'
        });
    });

    it('showErrorNotification should be called update when onClickConnect is failed', async () => {
        wrapper.setProps({ salesChannel: { id: '' } });
        wrapper.setMethods({ createNotificationError: jest.fn() });

        const spy = jest.spyOn(wrapper.vm, 'showErrorNotification');

        await wrapper.vm.$nextTick();
        await wrapper.vm.onClickConnect();

        expect(spy).toHaveBeenCalled();
    });
});
