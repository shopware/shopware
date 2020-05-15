import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/sw-sales-channel-google-terms-verification';
import 'src/app/component/form/sw-checkbox-field';

import state from 'src/module/sw-sales-channel/state/salesChannel.store';

Shopware.State.registerModule('swSalesChannel', state);

describe('module/sw-sales-channel/component/sw-sales-channel-google-terms-verification', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.Service().register('googleShoppingService', () => {
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

            return {
                saveTermsOfService: (salesChannelId, isAgree) => {
                    if (salesChannelId && isAgree) {
                        return Promise.resolve();
                    }

                    // eslint-disable-next-line prefer-promise-reject-errors
                    return Promise.reject(errorResponse);
                }
            };
        });
    });

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-sales-channel-google-terms-verification'), {
            stubs: {
                'sw-checkbox-field': true,
                'sw-sales-channel-detail-protect-link': true
            },
            mocks: {
                $tc: (translationPath) => translationPath,
                $route: { name: 'sw.sales.channel.detail.base.step-5' },
                $router: { push: () => {} }
            },
            propsData: {
                salesChannel: {
                    id: 1
                }
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('showErrorNotification should not be called update when onClickNext is successful', async () => {
        wrapper.setData({
            isAgree: true
        });

        wrapper.setMethods({ createNotificationError: jest.fn() });

        const spy = jest.spyOn(wrapper.vm, 'showErrorNotification');

        await wrapper.vm.$nextTick();
        await wrapper.vm.onClickNext();

        expect(spy).not.toHaveBeenCalled();
    });

    it('showErrorNotification should be called update when onClickNext is failed', async () => {
        wrapper.setData({
            isAgree: false
        });

        wrapper.setMethods({ createNotificationError: jest.fn() });

        const spy = jest.spyOn(wrapper.vm, 'showErrorNotification');

        await wrapper.vm.$nextTick();
        await wrapper.vm.onClickNext();

        expect(spy).toHaveBeenCalled();
    });
});
