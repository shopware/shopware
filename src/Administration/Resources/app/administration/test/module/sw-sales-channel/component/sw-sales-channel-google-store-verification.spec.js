import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/sw-sales-channel-google-store-verification';
import 'src/app/component/base/sw-label';
import 'src/app/component/base/sw-icon';
import 'src/app/component/base/sw-button';
import state from 'src/module/sw-sales-channel/state/salesChannel.store';

Shopware.State.registerModule('swSalesChannel', state);

describe('module/sw-sales-channel/component/sw-sales-channel-google-store-verification', () => {
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
            const response = {
                data: {
                    data: {
                        siteIsVerified: true,
                        shoppingAdsPolicies: true,
                        contactPage: false,
                        secureCheckoutProcess: true,
                        revocationPage: true,
                        shippingPaymentInfoPage: true,
                        completeCheckoutProcess: false
                    }
                }
            };

            return {
                verifyStore: (salesChannelId) => {
                    if (salesChannelId) {
                        return Promise.resolve(response);
                    }

                    // eslint-disable-next-line prefer-promise-reject-errors
                    return Promise.reject(errorResponse);
                }
            };
        });
    });

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-sales-channel-google-store-verification'), {
            store: Shopware.State._store,
            stubs: {
                'sw-label': Shopware.Component.build('sw-label'),
                'sw-icon': true,
                'sw-sales-channel-detail-protect-link': true,
                'sw-button': true,
                'router-link': true
            },
            mocks: {
                $tc: (translationPath) => translationPath,
                $route: { name: 'sw.sales.channel.detail.base.step-4' },
                $router: { push: () => {} }
            },
            propsData: {
                salesChannel: {
                    id: 1,
                    productExports: [{
                        storefrontSalesChannelId: 2
                    }]
                }
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should render number of item which equals total store verification items length', async () => {
        wrapper.setData({
            items: [
                {
                    status: 'success',
                    description: 'Shopping Ads policy'
                },
                {
                    status: 'success',
                    description: 'Accurate contact information'
                },
                {
                    status: 'success',
                    description: 'Complete checkout process'
                },
                {
                    status: 'danger',
                    description: 'Return policy'
                }
            ]
        });

        await wrapper.vm.$nextTick();

        const verifiedItems = wrapper.findAll('.sw-sales-channel-google-store-verification__check-item');
        expect(verifiedItems.length).toEqual(wrapper.vm.items.length);
    });

    it('should render item status and description correctly', async () => {
        wrapper.setData({
            items: [
                {
                    status: 'success',
                    key: 'siteIsVerified',
                    description: 'Verified storefront domain'
                },
                {
                    status: 'danger',
                    key: 'shoppingAdsPolicies',
                    description: 'Shopping Ads policy'
                },
                {
                    status: 'danger',
                    key: 'contactPage',
                    description: 'Accurate contact information'
                },
                {
                    status: 'success',
                    key: 'secureCheckoutProcess',
                    description: 'Complete checkout process'
                },
                {
                    status: 'success',
                    key: 'revocationPage',
                    description: 'Return policy'
                },
                {
                    status: 'success',
                    key: 'shippingPaymentInfoPage',
                    description: 'Billing terms and conditions'
                }
            ]
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.element).toMatchSnapshot();
    });

    it('items should update correctly when verifyStore is successful', async () => {
        wrapper.setMethods({ createNotificationError: jest.fn() });
        wrapper.setData({
            items: [
                {
                    status: 'info',
                    key: 'siteIsVerified',
                    description: 'Verified storefront domain'
                },
                {
                    status: 'info',
                    key: 'shoppingAdsPolicies',
                    description: 'Shopping Ads policy'
                },
                {
                    status: 'info',
                    key: 'contactPage',
                    description: 'Accurate contact information'
                },
                {
                    status: 'info',
                    key: 'secureCheckoutProcess',
                    description: 'Complete checkout process'
                },
                {
                    status: 'info',
                    key: 'revocationPage',
                    description: 'Return policy'
                },
                {
                    status: 'info',
                    key: 'shippingPaymentInfoPage',
                    description: 'Billing terms and conditions'
                },
                {
                    status: 'info',
                    key: 'completeCheckoutProcess',
                    description: 'Complete checkout process'
                }
            ]
        });

        await wrapper.vm.$nextTick();
        await wrapper.vm.verifyStore();

        const newItems = [
            {
                status: 'success',
                key: 'siteIsVerified',
                description: 'Verified storefront domain'
            },
            {
                status: 'success',
                key: 'shoppingAdsPolicies',
                description: 'Shopping Ads policy'
            },
            {
                status: 'danger',
                key: 'contactPage',
                description: 'Accurate contact information'
            },
            {
                status: 'success',
                key: 'secureCheckoutProcess',
                description: 'Complete checkout process'
            },
            {
                status: 'success',
                key: 'revocationPage',
                description: 'Return policy'
            },
            {
                status: 'success',
                key: 'shippingPaymentInfoPage',
                description: 'Billing terms and conditions'
            },
            {
                status: 'danger',
                key: 'completeCheckoutProcess',
                description: 'Complete checkout process'
            }
        ];

        expect(wrapper.vm.items).toStrictEqual(newItems);
    });

    it('showErrorNotification should be called update when verifyStore is failed', async () => {
        wrapper.setProps({
            salesChannel: {
                ...wrapper.vm.salesChannel,
                id: ''
            }
        });
        wrapper.setMethods({ createNotificationError: jest.fn() });

        const spy = jest.spyOn(wrapper.vm, 'showErrorNotification');

        await wrapper.vm.$nextTick();
        await wrapper.vm.verifyStore();

        expect(spy).toHaveBeenCalled();
    });
});
