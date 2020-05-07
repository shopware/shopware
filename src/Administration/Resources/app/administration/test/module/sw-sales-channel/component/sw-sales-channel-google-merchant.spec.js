import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/sw-sales-channel-google-merchant';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';

import state from 'src/module/sw-sales-channel/state/salesChannel.store';

Shopware.State.registerModule('swSalesChannel', state);

describe('module/sw-sales-channel/component/sw-sales-channel-google-merchant', () => {
    let wrapper;

    beforeAll(() => {
        const errorResponse = {
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
        };

        Shopware.Service().register('googleShoppingService', () => {
            const listResponse = {
                data: {
                    data: [
                        { name: 'store A', id: 1 },
                        { name: 'store B', id: 2 }
                    ]
                }
            };

            return {
                getMerchantList: (salesChannelId) => {
                    if (salesChannelId) {
                        return Promise.resolve(listResponse);
                    }

                    // eslint-disable-next-line prefer-promise-reject-errors
                    return Promise.reject(errorResponse);
                },

                assignMerchant: (salesChannelId, merchantId) => {
                    if (salesChannelId && merchantId) {
                        return Promise.resolve();
                    }

                    // eslint-disable-next-line prefer-promise-reject-errors
                    return Promise.reject(errorResponse);
                },

                unassignMerchant: (salesChannelId) => {
                    if (salesChannelId) {
                        return Promise.resolve();
                    }

                    // eslint-disable-next-line prefer-promise-reject-errors
                    return Promise.reject(errorResponse);
                }
            };
        });
    });

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-sales-channel-google-merchant'), {
            store: Shopware.State._store,
            stubs: {
                'sw-single-select': Shopware.Component.build('sw-single-select'),
                'sw-select-base': Shopware.Component.build('sw-select-base'),
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
                'sw-sales-channel-detail-protect-link': true,
                'sw-card-section': true,
                'sw-button': true,
                'sw-icon': true,
                'sw-field-error': true,
                'sw-loader': true
            },
            mocks: {
                $tc: (translationPath) => translationPath,
                $route: { name: 'sw.sales.channel.detail.base.step-3' },
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

    it('googleShoppingMerchantAccount in state should update correctly when onClickNext is successful', async () => {
        let googleShoppingMerchantAccount = Shopware.State.getters['swSalesChannel/googleShoppingMerchantAccount'];

        expect(googleShoppingMerchantAccount).toBeNull();
        expect(wrapper.vm.googleShoppingMerchantAccount).toBeNull();

        // First merchant assignment
        wrapper.setData({ selectedMerchant: wrapper.vm.merchantAccounts[0].id });

        await wrapper.vm.$nextTick();
        await wrapper.vm.onClickNext();

        googleShoppingMerchantAccount = Shopware.State.getters['swSalesChannel/googleShoppingMerchantAccount'];

        expect(googleShoppingMerchantAccount).toStrictEqual({ merchantId: wrapper.vm.merchantAccounts[0].id });
        expect(wrapper.vm.googleShoppingMerchantAccount).toStrictEqual({ merchantId: wrapper.vm.merchantAccounts[0].id });

        // Merchant reassignment
        wrapper.setData({ selectedMerchant: wrapper.vm.merchantAccounts[1].id });

        await wrapper.vm.$nextTick();
        await wrapper.vm.onClickNext();

        googleShoppingMerchantAccount = Shopware.State.getters['swSalesChannel/googleShoppingMerchantAccount'];

        expect(googleShoppingMerchantAccount).toStrictEqual({ merchantId: wrapper.vm.merchantAccounts[1].id });
        expect(wrapper.vm.googleShoppingMerchantAccount).toStrictEqual({ merchantId: wrapper.vm.merchantAccounts[1].id });
    });

    it('showErrorNotification should be called update when onClickNext is failed', async () => {
        wrapper.setProps({ salesChannel: { id: '' } });
        wrapper.setMethods({ createNotificationError: jest.fn() });

        const spy = jest.spyOn(wrapper.vm, 'showErrorNotification');

        await wrapper.vm.$nextTick();
        await wrapper.vm.onClickNext();

        expect(spy).toHaveBeenCalled();
    });
});
