import Vuex from 'vuex';
import { shallowMount, createLocalVue } from '@vue/test-utils';
import swOrderDetailDetails from 'src/module/sw-order/view/sw-order-detail-details';
import orderDetailStore from 'src/module/sw-order/state/order-detail.store';

// eslint-disable-next-line @typescript-eslint/no-unused-vars
import type Vue from 'vue';
// eslint-disable-next-line @typescript-eslint/no-unused-vars
import type { Wrapper } from '@vue/test-utils';

const orderMock = {
    orderCustomer: {
        email: 'test@example.com'
    },
    shippingCosts: {
        calculatedTaxes: [],
        totalPrice: {}
    },
    currency: {
        translated: {
            shortName: ''
        }
    },
    transactions: [
        {
            stateMachineState: {
                translated: {
                    name: ''
                }
            }
        }
    ],
    deliveries: [
        {
            stateMachineState: {
                translated: {
                    name: ''
                }
            },
            shippingCosts: {
                calculatedTaxes: [],
                totalPrice: {}
            },
            shippingOrderAddress: {
                id: 'address1'
            },
        }
    ],
    stateMachineState: {
        translated: {
            name: ''
        }
    },
    price: {
        calculatedTaxes: [],
        taxStatus: 'gross'
    },
    totalRounding: {
        interval: 0.01,
        decimals: 2
    },
    itemRounding: {
        interval: 0.01,
        decimals: 2
    },
    lineItems: [],
    billingAddressId: 'address1',
    shippingAddressId: 'address1',
    addresses: [
        {
            id: 'address1',
        },
    ],
};

Shopware.Component.register('sw-order-detail-details', swOrderDetailDetails);

async function createWrapper(privileges = []): Promise<Wrapper<Vue>> {
    const localVue = createLocalVue();
    localVue.use(Vuex);
    localVue.directive('tooltip', {});
    localVue.filter('currency', Shopware.Filter.getByName('currency'));

    orderMock.transactions.last = () => ({
        stateMachineState: {
            translated: {
                name: ''
            }
        }
    });

    orderMock.addresses.get = () => ({});

    return shallowMount(await Shopware.Component.build('sw-order-detail-details'), {
        localVue,
        stubs: {
            'sw-card-view': true,
            'sw-order-user-card': true,
            'sw-container': true,
            'sw-order-state-select': true,
            'sw-card': true,
            'sw-order-line-items-grid': true,
            'sw-card-section': true,
            'sw-description-list': true,
            'sw-order-saveable-field': true,
            'sw-order-state-history-card': true,
            'sw-order-delivery-metadata': true,
            'sw-order-document-card': true,
            'sw-text-field': true,
            'sw-order-details-state-card': true,
            'sw-order-address-selection': true,
            'sw-entity-single-select': true,
            'sw-number-field': true,
            'sw-datepicker': true,
            'sw-multi-tag-select': true,
            'sw-textarea-field': true,
            'sw-order-promotion-field': true
        },
        provide: {
            acl: {
                can: (key) => {
                    if (!key) { return true; }

                    return privileges.includes(key);
                }
            },
            orderService: {},
            stateStyleDataProviderService: {
                getStyle: () => ({})
            },
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve([]),
                })
            }

        },
        propsData: {
            orderId: '1a2b3c',
            isSaveSuccessful: false
        }
    });
}

describe('src/module/sw-order/view/sw-order-detail-details', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.State.registerModule('swOrderDetail', {
            ...orderDetailStore,
            state: {
                ...orderDetailStore.state,
                order: orderMock,
                orderAddressIds: [],
            },
        });
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        await wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have a disabled on transaction card', async () => {
        const stateCard = wrapper.find('sw-order-details-state-card-stub[state-label="sw-order.stateCard.headlineTransactionState"]');
        const addressSelection = wrapper.find('.sw-order-detail-details__billing-address');

        expect(stateCard.attributes().disabled).toBeTruthy();
        expect(addressSelection.attributes().disabled).toBeTruthy();
    });

    it('should not have an disabled on transaction card', async () => {
        wrapper = await createWrapper(['order.editor']);
        const stateCard = wrapper.find('sw-order-details-state-card-stub[state-label="sw-order.stateCard.headlineTransactionState"');
        const addressSelection = wrapper.find('.sw-order-detail-details__billing-address');

        expect(stateCard.attributes().disabled).toBeUndefined();
        expect(addressSelection.attributes().disabled).toBeUndefined();
    });

    it('should have a disabled on delivery card', async () => {
        const stateCard = wrapper.find('sw-order-details-state-card-stub[state-label="sw-order.stateCard.headlineDeliveryState"');
        const addressSelection = wrapper.find('.sw-order-detail-details__shipping-address');
        const trackingCodeField = wrapper.find('.sw-order-user-card__tracking-code-select');

        expect(stateCard.attributes().disabled).toBeTruthy();
        expect(addressSelection.attributes().disabled).toBeTruthy();
        expect(trackingCodeField.attributes().disabled).toBeTruthy();
    });

    it('should not have a disabled on detail card', async () => {
        wrapper = await createWrapper(['order.editor']);

        const stateCard = wrapper.find('sw-order-details-state-card-stub[state-label="sw-order.stateCard.headlineDeliveryState"');
        const addressSelection = wrapper.find('.sw-order-detail-details__shipping-address');
        const trackingCodeField = wrapper.find('.sw-order-user-card__tracking-code-select');

        expect(stateCard.attributes().disabled).toBeUndefined();
        expect(addressSelection.attributes().disabled).toBeUndefined();
        expect(trackingCodeField.attributes().disabled).toBeUndefined();
    });

    it('should have a disabled on order card', async () => {
        const stateCard = wrapper.find('sw-order-details-state-card-stub[state-label="sw-order.stateCard.headlineOrderState"');
        const emailField = wrapper.find('.sw-order-detail-details__email');
        const phoneNumberField = wrapper.find('.sw-order-detail-details__phone-number');
        const affiliateCodeField = wrapper.find('.sw-order-detail-details__affiliate-code');
        const campaignCodeField = wrapper.find('.sw-order-detail-details__campaign-code');

        expect(stateCard.attributes().disabled).toBeTruthy();
        expect(emailField.attributes().disabled).toBeTruthy();
        expect(phoneNumberField.attributes().disabled).toBeTruthy();
        expect(affiliateCodeField.attributes().disabled).toBeTruthy();
        expect(campaignCodeField.attributes().disabled).toBeTruthy();
    });

    it('should not have a disabled on order card', async () => {
        wrapper = await createWrapper(['order.editor']);

        const stateCard = wrapper.find('sw-order-details-state-card-stub[state-label="sw-order.stateCard.headlineOrderState"');
        const emailField = wrapper.find('.sw-order-detail-details__email');
        const phoneNumberField = wrapper.find('.sw-order-detail-details__phone-number');
        const affiliateCodeField = wrapper.find('.sw-order-detail-details__affiliate-code');
        const campaignCodeField = wrapper.find('.sw-order-detail-details__campaign-code');

        expect(stateCard.attributes().disabled).toBeUndefined();
        expect(emailField.attributes().disabled).toBeUndefined();
        expect(phoneNumberField.attributes().disabled).toBeUndefined();
        expect(affiliateCodeField.attributes().disabled).toBeUndefined();
        expect(campaignCodeField.attributes().disabled).toBeUndefined();
    });
});
