/**
 * @package checkout
 * @group disabledCompat
 */

import { mount } from '@vue/test-utils';
import orderDetailStore from 'src/module/sw-order/state/order-detail.store';

const orderMock = {
    orderCustomer: {
        email: 'test@example.com',
    },
    shippingCosts: {
        calculatedTaxes: [],
        totalPrice: 0,
    },
    currency: {
        translated: {
            isoCode: 'EUR',
        },
    },
    transactions: [
        {
            stateMachineState: {
                translated: {
                    name: '',
                },
            },
        },
    ],
    deliveries: [
        {
            stateMachineState: {
                translated: {
                    name: 'Open',
                },
            },
            shippingCosts: {
                calculatedTaxes: [],
                totalPrice: 0,
            },
            shippingOrderAddress: {
                id: 'address1',
            },
        },
    ],
    stateMachineState: {
        translated: {
            name: 'Open',
        },
    },
    price: {
        calculatedTaxes: [],
        taxStatus: 'gross',
    },
    totalRounding: {
        interval: 0.01,
        decimals: 2,
    },
    itemRounding: {
        interval: 0.01,
        decimals: 2,
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

async function createWrapper() {
    orderMock.transactions.last = () => ({
        stateMachineState: {
            translated: {
                name: '',
            },
        },
    });

    orderMock.addresses.get = () => ({});

    return mount(await wrapTestComponent('sw-order-detail-details', { sync: true }), {
        global: {
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
                'sw-order-details-state-card': {
                    template: `
                        <div class="sw-order-details-state-card"><slot></slot></div>
                    `,
                },
                'sw-order-address-selection': true,
                'sw-entity-single-select': true,
                'sw-number-field': {
                    template: '<input class="sw-number-field" type="number" @input="$emit(\'input\', Number($event.target.value))" />',
                    props: {
                        value: 0,
                    },
                },
                'sw-datepicker': true,
                'sw-multi-tag-select': true,
                'sw-textarea-field': true,
                'sw-order-promotion-field': true,
                'sw-extension-component-section': true,
                'sw-custom-field-set-renderer': true,
                'sw-order-state-history-modal': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve([]),
                    }),
                },

            },
        },
        props: {
            orderId: '1a2b3c',
            isSaveSuccessful: false,
        },
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

    it('should be a Vue.js component', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have a disabled on transaction card', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        const stateCard = wrapper.find('.sw-order-details-state-card[state-label="sw-order.stateCard.headlineTransactionState"]');
        const addressSelection = wrapper.find('.sw-order-detail-details__billing-address');

        expect(stateCard.attributes().disabled).toBeTruthy();
        expect(addressSelection.attributes().disabled).toBeTruthy();
    });

    it('should not have an disabled on transaction card', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();
        const stateCard = wrapper.find('.sw-order-details-state-card[state-label="sw-order.stateCard.headlineTransactionState"');
        const addressSelection = wrapper.find('.sw-order-detail-details__billing-address');

        expect(stateCard.attributes().disabled).toBeUndefined();
        expect(addressSelection.attributes().disabled).toBeUndefined();
    });

    it('should have a disabled on delivery card', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        const stateCard = wrapper.find('.sw-order-details-state-card[state-label="sw-order.stateCard.headlineDeliveryState"');
        const addressSelection = wrapper.find('.sw-order-detail-details__shipping-address');
        const trackingCodeField = wrapper.find('.sw-order-user-card__tracking-code-select');

        expect(stateCard.attributes().disabled).toBeTruthy();
        expect(addressSelection.attributes().disabled).toBeTruthy();
        expect(trackingCodeField.attributes().disabled).toBeTruthy();
    });

    it('should not have a disabled on detail card', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();

        const stateCard = wrapper.find('.sw-order-details-state-card[state-label="sw-order.stateCard.headlineDeliveryState"');
        const addressSelection = wrapper.find('.sw-order-detail-details__shipping-address');
        const trackingCodeField = wrapper.find('.sw-order-user-card__tracking-code-select');

        expect(stateCard.attributes().disabled).toBeUndefined();
        expect(addressSelection.attributes().disabled).toBeUndefined();
        expect(trackingCodeField.attributes().disabled).toBeUndefined();
    });

    it('should have a disabled on order card', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        const stateCard = wrapper.find('.sw-order-details-state-card[state-label="sw-order.stateCard.headlineOrderState"');
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
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();

        const stateCard = wrapper.find('.sw-order-details-state-card[state-label="sw-order.stateCard.headlineOrderState"');
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

    it('should able to edit shipping cost', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();
        const shippingCostField = wrapper.findComponent('.sw-order-detail-details__shipping-cost');
        await shippingCostField.vm.$emit('update:value', 20);

        expect(wrapper.vm.delivery.shippingCosts.unitPrice).toBe(20);
        expect(wrapper.vm.delivery.shippingCosts.totalPrice).toBe(20);
        expect(wrapper.emitted('save-and-recalculate')).toBeTruthy();
    });
});
