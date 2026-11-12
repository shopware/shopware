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
        calculatedTaxes: [
            {
                tax: 1,
                taxRate: 10,
            },
            {
                tax: 1.9,
                taxRate: 19,
            },
        ],
        totalPrice: 10,
    },
    currency: {
        isoCode: 'EUR',
        translated: {
            isoCode: 'EUR',
        },
    },
    deliveries: [
        {
            stateMachineState: {
                translated: {
                    name: 'Open',
                },
            },
            shippingCosts: {
                calculatedTaxes: [],
                totalPrice: 10,
            },
            shippingOrderAddress: {
                id: 'address1',
            },
        },
    ],
    price: {
        calculatedTaxes: [
            {
                tax: 10,
                taxRate: 10,
            },
            {
                tax: 19,
                taxRate: 19,
            },
        ],
        taxStatus: 'gross',
        totalPrice: 139,
    },
    totalRounding: {
        interval: 0.01,
        decimals: 2,
    },
    itemRounding: {
        interval: 0.01,
        decimals: 2,
    },
    amountNet: 100,
    lineItems: [],
};

orderMock.primaryOrderDelivery = orderMock.deliveries[0];

function createShipment(id, shippingMethodName, totalPrice, calculatedTaxes = []) {
    return {
        id,
        shippingMethod: { translated: { name: shippingMethodName } },
        shippingCosts: { calculatedTaxes, totalPrice, unitPrice: totalPrice },
    };
}

async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-detail-general', { sync: true }), {
        global: {
            stubs: {
                'sw-container': await wrapTestComponent('sw-container', {
                    sync: true,
                }),
                'mt-card': {
                    template: `
                        <div class="mt-card">
                            <slot></slot>
                            <slot name="grid"></slot>
                        </div>
                    `,
                },
                'sw-order-general-info': true,
                'sw-order-line-items-grid': true,
                'sw-order-promotion-field': true,
                'sw-number-field': await wrapTestComponent('sw-number-field', { sync: true }),
                'sw-order-saveable-field': await wrapTestComponent('sw-order-saveable-field', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-copyable': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-field-error': true,
                'sw-extension-component-section': true,
                'router-link': true,
                'sw-loader': true,
                'sw-number-field-deprecated': true,
            },
            mocks: {
                $t: (key, value) => {
                    if (!value) {
                        return key;
                    }
                    return key + JSON.stringify(value);
                },
            },
            directives: {
                tooltip: {
                    beforeMount(el, binding) {
                        el.setAttribute('tooltip-message', binding.value.message);
                    },
                    mounted(el, binding) {
                        el.setAttribute('tooltip-message', binding.value.message);
                    },
                    updated(el, binding) {
                        el.setAttribute('tooltip-message', binding.value.message);
                    },
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
    });

    it('should tax description correctly for shipping cost if taxStatus is not tax-free', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();

        const shippingCostField = wrapper.find('.sw-order-detail-general__summary div[role="button"]');
        expect(shippingCostField.attributes()['tooltip-message']).toBe(
            'sw-order.detailBase.tax<br>sw-order.detailBase.shippingCostsTax{"taxRate":10,"tax":"€1.00"}<br>sw-order.detailBase.shippingCostsTax{"taxRate":19,"tax":"€1.90"}',
        );
    });

    it('should show the discount amount for additional deliveries', async () => {
        global.activeAclRoles = [];
        const store = Shopware.Store.get('swOrderDetail');
        store.order = {
            ...orderMock,
            deliveries: [
                ...orderMock.deliveries,
                {
                    id: 'discount-delivery',
                    shippingCosts: {
                        calculatedTaxes: [],
                        totalPrice: -5,
                    },
                },
            ],
        };
        wrapper = await createWrapper();

        const discountRow = wrapper
            .findAll('.sw-order-detail-general__summary-row')
            .find((row) => row.text().includes('sw-order.detailBase.discountLabelShippingCosts'));

        expect(discountRow.findAll('span')[1].text()).toBe('-€5.00');

        store.order = orderMock;
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should recognise a discount delivery by its negative shipping costs', async () => {
        global.activeAclRoles = [];
        const store = Shopware.Store.get('swOrderDetail');
        const shipment = createShipment('shipment', 'DHL', 10);
        store.order = {
            ...orderMock,
            primaryOrderDeliveryId: 'shipment',
            primaryOrderDelivery: shipment,
            deliveries: [
                { id: 'discount-delivery', shippingCosts: { calculatedTaxes: [], totalPrice: -5 } },
                shipment,
            ],
        };
        wrapper = await createWrapper();

        const rows = wrapper.findAll('.sw-order-detail-general__summary-row');

        expect(rows[1].find('span').text()).toBe('sw-order.detailBase.summaryLabelShippingCosts');
        expect(rows[1].find('div[role="button"]').text()).toBe('€10.00');
        expect(rows[2].findAll('span').map((span) => span.text())).toEqual([
            'sw-order.detailBase.discountLabelShippingCosts',
            '-€5.00',
        ]);
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should show one editable shipping cost row per shipment, labelled by its shipping method',
        async () => {
            global.activeAclRoles = ['order.editor'];
            const store = Shopware.Store.get('swOrderDetail');
            const dhlShipment = createShipment('dhl-shipment', 'DHL', 4.99, [{ tax: 0.8, taxRate: 19 }]);
            const upsShipment = createShipment('ups-shipment', 'UPS', 6.99, [{ tax: 0.46, taxRate: 7 }]);
            store.order = {
                ...orderMock,
                primaryOrderDeliveryId: 'ups-shipment',
                primaryOrderDelivery: upsShipment,
                deliveries: [
                    dhlShipment,
                    upsShipment,
                    { id: 'discount-delivery', shippingCosts: { calculatedTaxes: [], totalPrice: -5 } },
                ],
            };
            wrapper = await createWrapper();

            const rows = wrapper.findAll('.sw-order-detail-general__summary-row');

            expect(rows.slice(0, 4).map((row) => row.find('span').text())).toEqual([
                'sw-order.detailBase.summaryLabelAmount',
                'sw-order.detailBase.summaryLabelShipmentShippingCosts{"count":1,"shippingMethod":"UPS"}',
                'sw-order.detailBase.summaryLabelShipmentShippingCosts{"count":2,"shippingMethod":"DHL"}',
                'sw-order.detailBase.discountLabelShippingCosts',
            ]);

            const dhlField = rows[2].find('div[role="button"]');

            expect(dhlField.text()).toBe('€4.99');
            expect(dhlField.attributes()['tooltip-message']).toBe(
                'sw-order.detailBase.tax<br>sw-order.detailBase.shippingCostsTax{"taxRate":19,"tax":"€0.80"}',
            );

            await dhlField.trigger('click');
            await flushPromises();

            const dhlInput = rows[2].find('input');
            await dhlInput.setValue(20);
            await dhlInput.trigger('input');
            await wrapper.findByAriaLabel('button', 'global.default.save').trigger('click');

            expect(dhlShipment.shippingCosts).toEqual(expect.objectContaining({ unitPrice: 20, totalPrice: 20 }));
            expect(upsShipment.shippingCosts.totalPrice).toBe(6.99);
            expect(wrapper.emitted('save-and-recalculate')).toBeTruthy();
        },
    );

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should keep the shipping costs of a shipment confirmed without an edit',
        async () => {
            global.activeAclRoles = ['order.editor'];
            const store = Shopware.Store.get('swOrderDetail');
            const dhlShipment = createShipment('dhl-shipment', 'DHL', 4.99);
            const upsShipment = createShipment('ups-shipment', 'UPS', 6.99);
            store.order = {
                ...orderMock,
                primaryOrderDeliveryId: 'ups-shipment',
                primaryOrderDelivery: upsShipment,
                deliveries: [
                    upsShipment,
                    dhlShipment,
                ],
            };
            wrapper = await createWrapper();

            const dhlRow = wrapper.findAll('.sw-order-detail-general__summary-row')[2];
            await dhlRow.find('div[role="button"]').trigger('click');
            await flushPromises();
            await dhlRow.find('input').setValue(20);
            await dhlRow.find('input').trigger('input');
            await wrapper.findByAriaLabel('button', 'global.default.save').trigger('click');
            await flushPromises();

            const upsRow = wrapper.findAll('.sw-order-detail-general__summary-row')[1];
            await upsRow.find('div[role="button"]').trigger('click');
            await flushPromises();
            await wrapper.findByAriaLabel('button', 'global.default.save').trigger('click');

            expect(dhlShipment.shippingCosts.totalPrice).toBe(20);
            expect(upsShipment.shippingCosts.totalPrice).toBe(6.99);
        },
    );

    it('should tax description correctly if taxStatus is not tax-free', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        const summaryRows = wrapper.findAll('.sw-order-detail-general__summary-row');
        const [firstTaxLabel, firstTaxValue] = summaryRows[3].findAll('span');
        const [secondTaxLabel, secondTaxValue] = summaryRows[4].findAll('span');

        expect(firstTaxLabel.text()).toBe('sw-order.detailBase.summaryLabelTaxes{"taxRate":10}');
        expect(firstTaxValue.text()).toBe('€10.00');

        expect(secondTaxLabel.text()).toBe('sw-order.detailBase.summaryLabelTaxes{"taxRate":19}');
        expect(secondTaxValue.text()).toBe('€19.00');
    });

    it('should able to edit shipping cost', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();

        let button = wrapper.find('.sw-order-detail-general__summary div[role="button"]');
        await button.trigger('click');
        await flushPromises();

        const saveableField = wrapper.find('.sw-order-saveable-field input');
        await saveableField.setValue(20);
        await saveableField.trigger('input');

        button = wrapper.findByAriaLabel('button', 'global.default.save');
        await button.trigger('click');

        expect(wrapper.vm.delivery.shippingCosts.unitPrice).toBe(20);
        expect(wrapper.vm.delivery.shippingCosts.totalPrice).toBe(20);
        expect(wrapper.emitted('save-and-recalculate')).toBeTruthy();
    });

    it('should emit event save-edits when saving general info', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();
        const generalInfo = wrapper.findComponent('sw-order-general-info-stub');
        await generalInfo.vm.$emit('save-edits');

        expect(wrapper.emitted('save-edits')).toBeTruthy();
    });

    it('should emit event recalculate-and-reload when adding line item delete item successfully', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();
        const generalInfo = wrapper.findComponent('sw-order-line-items-grid-stub');
        await generalInfo.vm.$emit('item-edit');

        expect(wrapper.emitted('recalculate-and-reload')).toBeTruthy();
    });

    it('should emit event recalculate-and-reload when deleting line item successfully', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();
        const generalInfo = wrapper.findComponent('sw-order-line-items-grid-stub');
        await generalInfo.vm.$emit('item-delete');

        expect(wrapper.emitted('recalculate-and-reload')).toBeTruthy();
    });

    it('should reload without recalculating when cancel editing line item successfully', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();
        const generalInfo = wrapper.findComponent('sw-order-line-items-grid-stub');
        await generalInfo.vm.$emit('item-cancel');

        expect(wrapper.emitted('recalculate-and-reload')).toBeFalsy();
        expect(wrapper.emitted('reload-entity-data')).toEqual([[false]]);
    });

    it('should emit event save-and-recalculate when editing line item successfully', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();
        const generalInfo = wrapper.findComponent('sw-order-line-items-grid-stub');
        await generalInfo.vm.$emit('existing-item-edit');

        expect(wrapper.emitted('save-and-recalculate')).toBeTruthy();
    });
});
