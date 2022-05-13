import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/mixin/cart-notification.mixin';
import swOrderCreateInitialModal from 'src/module/sw-order/component/sw-order-create-initial-modal';

import Vuex from 'vuex';
import orderStore from 'src/module/sw-order/state/order.store';

Shopware.Component.register('sw-order-create-initial-modal', swOrderCreateInitialModal);

async function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(await Shopware.Component.build('sw-order-create-initial-modal'), {
        localVue,
        propsData: {
            taxStatus: 'gross',
            currency: {
                shortName: 'EUR',
                symbol: '€'
            },
            customItem: {}
        },
        stubs: {
            'sw-modal': {
                template: `
                    <div class="sw-modal">
                        <slot name="default"></slot>
                        <slot name="footer"></slot>
                    </div>`
            },
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-tabs': {
                template: '<div class="sw-tabs"><slot></slot><slot name="content" active="content"></slot></div>'
            },
            'sw-tabs-item': true,
            'sw-order-product-grid': true,
            'sw-order-customer-grid': true,
        },
    });
}


const tabs = [
    '.sw-order-create-initial-modal__tab-product',
    '.sw-order-create-initial-modal__tab-custom-item',
    '.sw-order-create-initial-modal__tab-options',
    '.sw-order-create-initial-modal__tab-credit',
];

describe('src/module/sw-order/view/sw-order-create-initial-modal', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swOrder', orderStore);
    });

    afterEach(() => {
        Shopware.State.commit('swOrder/setCart', {
            token: null,
            lineItems: []
        });
    });

    it('should disabled other tabs if customer is not selected', async () => {
        const wrapper = await createWrapper();

        tabs.forEach(tab => {
            expect(wrapper.find(tab).attributes().disabled).toBeTruthy();
        });
    });

    it('should enable other tabs if customer is selected', async () => {
        Shopware.State.commit('swOrder/setCustomer', {
            id: '1234'
        });

        const wrapper = await createWrapper();

        tabs.forEach(tab => {
            expect(wrapper.find(tab).attributes().disabled).toBeUndefined();
        });
    });
});
