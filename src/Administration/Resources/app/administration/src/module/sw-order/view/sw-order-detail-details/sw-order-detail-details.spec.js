/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

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

orderMock.primaryOrderDelivery = orderMock.deliveries[0];

const deliveryCardSelector = '.sw-order-details-state-card[state-label="sw-order.stateCard.headlineDeliveryState"]';

function createShipment(id, shippingOrderAddressId, trackingCodes = []) {
    return {
        id,
        shippingOrderAddress: { id: shippingOrderAddressId },
        shippingCosts: { calculatedTaxes: [], totalPrice: 4.99 },
        stateMachineState: { translated: { name: 'Open' } },
        trackingCodes,
    };
}

function setMultiShipmentOrder() {
    const primaryShipment = createShipment('primary-shipment', 'address-primary');

    Shopware.Store.get('swOrderDetail').order = {
        ...orderMock,
        primaryOrderDeliveryId: 'primary-shipment',
        primaryOrderDelivery: primaryShipment,
        deliveries: [
            createShipment('first-shipment', 'address-first', ['FIRST-1']),
            primaryShipment,
            {
                ...createShipment('discount-delivery', 'address-primary'),
                shippingCosts: { calculatedTaxes: [], totalPrice: -5 },
            },
        ],
    };
}

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
                'sw-datepicker': true,
                'sw-multi-tag-select': {
                    props: ['validate'],
                    template: '<div class="sw-multi-tag-select"></div>',
                },
                'sw-textarea-field': true,
                'sw-extension-component-section': true,
                'sw-custom-field-set-renderer': true,
                'sw-order-state-history-modal': true,
            },
            mocks: {
                $t: (key, value) => {
                    if (!value) {
                        return key;
                    }
                    return key + JSON.stringify(value);
                },
            },
            provide: {
                customFieldDataProviderService: { getCustomFieldSets: () => Promise.resolve([]) },
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve([]),
                    }),
                },
            },
        },
        props: {
            orderId: '1a2b3c',
        },
    });
}

describe('src/module/sw-order/view/sw-order-detail-details', () => {
    let wrapper;

    beforeAll(() => {
        setActivePinia(createPinia());
        Shopware.Store.get('swOrderDetail').order = orderMock;
    });

    afterEach(() => {
        Shopware.Store.get('swOrderDetail').order = orderMock;
        Shopware.Store.get('swOrderDetail').setOrderAddressIds(null);
    });

    it('should have a disabled on transaction card', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        const stateCard = wrapper.find(
            '.sw-order-details-state-card[state-label="sw-order.stateCard.headlineTransactionState"]',
        );
        const addressSelection = wrapper.find('.sw-order-detail-details__billing-address');

        expect(stateCard.attributes().disabled).toBeTruthy();
        expect(addressSelection.attributes().disabled).toBeTruthy();
    });

    it('should not have an disabled on transaction card', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();
        const stateCard = wrapper.find(
            '.sw-order-details-state-card[state-label="sw-order.stateCard.headlineTransactionState"',
        );
        const addressSelection = wrapper.find('.sw-order-detail-details__billing-address');

        expect(stateCard.attributes().disabled).toBeUndefined();
        expect(addressSelection.attributes().disabled).toBeUndefined();
    });

    it('should have a disabled on delivery card', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        const stateCard = wrapper.find(
            '.sw-order-details-state-card[state-label="sw-order.stateCard.headlineDeliveryState"',
        );
        const addressSelection = wrapper.find('.sw-order-detail-details__shipping-address');
        const trackingCodeField = wrapper.find('.sw-order-user-card__tracking-code-select');

        expect(stateCard.attributes().disabled).toBeTruthy();
        expect(addressSelection.attributes().disabled).toBeTruthy();
        expect(trackingCodeField.attributes().disabled).toBeTruthy();
    });

    it('should not have a disabled on detail card', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();

        const stateCard = wrapper.find(
            '.sw-order-details-state-card[state-label="sw-order.stateCard.headlineDeliveryState"',
        );
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
        const emailField = wrapper.findComponent('.sw-order-detail-details__email');
        const phoneNumberField = wrapper.findComponent('.sw-order-detail-details__phone-number');
        const affiliateCodeField = wrapper.findComponent('.sw-order-detail-details__affiliate-code');
        const campaignCodeField = wrapper.findComponent('.sw-order-detail-details__campaign-code');

        expect(stateCard.attributes().disabled).toBeTruthy();
        expect(emailField.props().disabled).toBeTruthy();
        expect(phoneNumberField.props().disabled).toBeTruthy();
        expect(affiliateCodeField.props().disabled).toBeTruthy();
        expect(campaignCodeField.props().disabled).toBeTruthy();
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

    // @deprecated tag:v6.8.0 - The test will be removed with shipping-cost editing in the Details view.
    it.deprecated('v6.8.0.0')('should able to edit shipping cost', async () => {
        jest.useFakeTimers();
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();
        const shippingCostField = wrapper.findComponent('.sw-order-detail-details__shipping-cost');
        await shippingCostField.setValue(20);

        jest.advanceTimersByTime(1000);

        expect(wrapper.vm.delivery.shippingCosts.unitPrice).toBe(20);
        expect(wrapper.vm.delivery.shippingCosts.totalPrice).toBe(20);
        expect(wrapper.emitted('save-and-recalculate')).toBeTruthy();
    });

    // @deprecated tag:v6.8.0 - The test will be removed with shipping-cost editing in the Details view.
    it.deprecated('v6.8.0.0')('should recalculate shipping cost while the field is not out of focus yet', async () => {
        jest.useFakeTimers();
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();
        const shippingCostInput = wrapper.find('.sw-order-detail-details__shipping-cost input');

        shippingCostInput.element.value = '20';
        await shippingCostInput.trigger('input');

        jest.advanceTimersByTime(1000);

        expect(wrapper.vm.delivery.shippingCosts.unitPrice).toBe(20);
        expect(wrapper.vm.delivery.shippingCosts.totalPrice).toBe(20);
        expect(wrapper.emitted('save-and-recalculate')).toBeTruthy();
    });

    it('should render a single delivery card for an order with one shipment', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();

        const deliveryCards = wrapper.findAll(deliveryCardSelector);

        expect(deliveryCards).toHaveLength(1);
        expect(deliveryCards[0].attributes('title')).toBe('sw-order.detailsTab.labelDeliveryCard');
        expect(deliveryCards[0].find('.sw-order-detail-details__shipping-address').attributes('address-id')).toBe(
            'address1',
        );
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should render one delivery card per shipment, the primary one first', async () => {
        global.activeAclRoles = ['order.editor'];
        setMultiShipmentOrder();
        wrapper = await createWrapper();

        const deliveryCards = wrapper.findAll(deliveryCardSelector);

        expect(deliveryCards.map((card) => card.attributes('title'))).toEqual([
            'sw-order.detailDeliveries.cardTitleItems{"count":1}',
            'sw-order.detailDeliveries.cardTitleItems{"count":2}',
        ]);
        expect(
            deliveryCards.map((card) => card.find('.sw-order-detail-details__shipping-address').attributes('address-id')),
        ).toEqual([
            'address-primary',
            'address-first',
        ]);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should keep the shipping address selections of the shipments apart', async () => {
        global.activeAclRoles = ['order.editor'];
        setMultiShipmentOrder();
        wrapper = await createWrapper();

        const secondCard = wrapper.findAll(deliveryCardSelector)[1];
        await secondCard.findComponent('.sw-order-detail-details__shipping-address').vm.$emit('change-address', {
            orderAddressId: 'address-first',
            customerAddressId: 'customer-address-new',
            type: 'shipping',
            edited: false,
        });
        await flushPromises();

        expect(Shopware.Store.get('swOrderDetail').orderAddressIds).toEqual([
            {
                orderAddressId: 'address-first',
                customerAddressId: 'customer-address-new',
                type: 'shipping',
                edited: false,
                deliveryId: 'first-shipment',
            },
        ]);
        expect(
            wrapper
                .findAll(deliveryCardSelector)
                .map((card) => card.find('.sw-order-detail-details__shipping-address').attributes('address-id')),
        ).toEqual([
            'address-primary',
            'customer-address-new',
        ]);
    });

    it('should show a shipping address change without a delivery id on the primary delivery card', async () => {
        global.activeAclRoles = ['order.editor'];
        const primaryShipment = createShipment('primary-shipment', 'address-primary');
        Shopware.Store.get('swOrderDetail').order = {
            ...orderMock,
            primaryOrderDeliveryId: 'primary-shipment',
            primaryOrderDelivery: primaryShipment,
            deliveries: [primaryShipment],
        };
        Shopware.Store.get('swOrderDetail').setOrderAddressIds({
            orderAddressId: 'address-primary',
            customerAddressId: 'customer-address-new',
            type: 'shipping',
            edited: false,
        });
        wrapper = await createWrapper();

        expect(
            wrapper.find(`${deliveryCardSelector} .sw-order-detail-details__shipping-address`).attributes('address-id'),
        ).toBe('customer-address-new');
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should validate tracking codes against the shipment they belong to', async () => {
        global.activeAclRoles = ['order.editor'];
        setMultiShipmentOrder();
        wrapper = await createWrapper();

        const validators = wrapper
            .findAll(deliveryCardSelector)
            .map((card) => card.findComponent('.sw-order-user-card__tracking-code-select').props('validate'));

        expect(validators[0]('FIRST-1')).toBe(true);
        expect(validators[1]('FIRST-1')).toBe(false);
        expect(validators[1]('FIRST-2')).toBe(true);
    });

    it('should be able to edit internal comment', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();
        const internalCommentField = wrapper.findComponent('.sw-order-detail-details__internal-comment');
        await internalCommentField.setValue('This is a longtext');

        expect(wrapper.vm.order.internalComment).toBe('This is a longtext');
        expect(wrapper.emitted('save-and-recalculate')).toBeFalsy();
    });
});
