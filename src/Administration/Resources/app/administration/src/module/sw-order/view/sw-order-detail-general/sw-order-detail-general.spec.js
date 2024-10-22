/**
 * @package checkout
 */

import { mount } from '@vue/test-utils';
import orderDetailStore from 'src/module/sw-order/state/order-detail.store';

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

async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-detail-general', { sync: true }), {
        global: {
            stubs: {
                'sw-container': await wrapTestComponent('sw-container', {
                    sync: true,
                }),
                'sw-card-section': await wrapTestComponent('sw-card-section', { sync: true }),
                'sw-description-list': await wrapTestComponent('sw-description-list', { sync: true }),
                'sw-card': {
                    template: `
                        <div class="sw-card">
                            <slot></slot>
                            <slot name="grid"></slot>
                        </div>
                    `,
                },
                'sw-order-general-info': true,
                'sw-order-line-items-grid': true,
                'sw-order-saveable-field': await wrapTestComponent('sw-order-saveable-field', { sync: true }),
                'sw-number-field': await wrapTestComponent('sw-number-field'),
                'sw-number-field-deprecated': await wrapTestComponent('sw-number-field-deprecated'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-copyable': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-field-error': true,
                'sw-extension-component-section': true,
                'sw-icon': true,
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'router-link': true,
                'sw-loader': true,
            },
            mocks: {
                $tc: (key, number, value) => {
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

    it('should tax description correctly for shipping cost if taxStatus is not tax-free', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();

        const shippingCostField = wrapper.find('.sw-order-detail__summary div[role="button"]');
        expect(shippingCostField.attributes()['tooltip-message']).toBe(
            'sw-order.detailBase.tax<br>sw-order.detailBase.shippingCostsTax{"taxRate":10,"tax":"€1.00"}<br>sw-order.detailBase.shippingCostsTax{"taxRate":19,"tax":"€1.90"}',
        );
    });

    it('should tax description correctly if taxStatus is not tax-free', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        const descriptionTitles = wrapper.findAll('dt');
        const descriptionInfos = wrapper.findAll('dd');

        expect(descriptionTitles[3].text()).toBe('sw-order.detailBase.summaryLabelTaxes{"taxRate":10}');
        expect(descriptionInfos[3].text()).toBe('€10.00');

        expect(descriptionTitles[4].text()).toBe('sw-order.detailBase.summaryLabelTaxes{"taxRate":19}');
        expect(descriptionInfos[4].text()).toBe('€19.00');
    });

    it('should able to edit shipping cost', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();

        let button = wrapper.find('.sw-order-detail__summary div[role="button"]');
        await button.trigger('click');
        await flushPromises();

        const saveableField = wrapper.find('.sw-order-saveable-field input');
        await saveableField.setValue(20);
        await saveableField.trigger('input');

        button = wrapper.find('.sw-order-saveable-field .sw-button--primary');
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

    it('should emit event recalculate-and-reload when cancel editing line item successfully', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();
        const generalInfo = wrapper.findComponent('sw-order-line-items-grid-stub');
        await generalInfo.vm.$emit('item-cancel');

        expect(wrapper.emitted('recalculate-and-reload')).toBeTruthy();
    });

    it('should emit event save-and-recalculate when editing line item successfully', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();
        const generalInfo = wrapper.findComponent('sw-order-line-items-grid-stub');
        await generalInfo.vm.$emit('existing-item-edit');

        expect(wrapper.emitted('save-and-recalculate')).toBeTruthy();
    });
});
