import Vuex from 'vuex';
import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/mixin/cart-notification.mixin';
import swOrderCreateInitialModal from 'src/module/sw-order/component/sw-order-create-initial-modal';
import 'src/app/component/base/sw-button';
import orderStore from 'src/module/sw-order/state/order.store';

const lineItem = {
    label: 'Product',
    productId: 'product1',
};

const cartResponse = {
    data: {
        token: 'token',
        deliveries: [],
        lineItems: [],
    },
};

const cartToken = 'is-exactly-32-chars-as-required-';

Shopware.Component.register('sw-order-create-initial-modal', swOrderCreateInitialModal);

async function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(await Shopware.Component.build('sw-order-create-initial-modal'), {
        localVue,
        stubs: {
            'sw-modal': {
                template: `
                    <div class="sw-modal">
                        <slot name="default"></slot>
                        <slot name="modal-footer"></slot>
                    </div>`,
            },
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>',
            },
            'sw-tabs': {
                data() {
                    return { active: 'customer' };
                },
                template: '<div class="sw-tabs"><slot></slot><slot name="content" v-bind="{ active }"></slot></div>',
            },
            'sw-tabs-item': true,
            'sw-order-customer-grid': true,
            'sw-order-line-items-grid-sales-channel': true,
            'sw-order-create-options': true,
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-icon': true,
            'sw-loader': true,
        },
    });
}

const tabs = [
    '.sw-order-create-initial-modal__tab-product',
    '.sw-order-create-initial-modal__tab-options',
];

describe('src/module/sw-order/view/sw-order-create-initial-modal', () => {
    beforeAll(() => {
        Shopware.Service().register('cartStoreService', () => {
            return {
                cancelCart: () => Promise.resolve({}),
                saveLineItem: () => Promise.resolve({
                    data: {
                        ...cartResponse.data,
                        lineItems: [{ ...lineItem }],
                    },
                }),
                removeLineItems: () => Promise.resolve(cartResponse),
                disableAutomaticPromotions: () => Promise.resolve(cartResponse),
                addMultipleLineItems: () => Promise.resolve(cartResponse),
                modifyShippingCosts: () => Promise.resolve({ data: { ...cartResponse } }),
            };
        });

        Shopware.Service().register('contextStoreService', () => {
            return {
                updateContext: () => Promise.resolve({}),
            };
        });

        Shopware.State.registerModule('swOrder', orderStore);
    });

    afterEach(() => {
        Shopware.State.commit('swOrder/setCart', {
            token: null,
            lineItems: [],
            deliveries: [],
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
            id: '1234',
        });

        const wrapper = await createWrapper();

        tabs.forEach(tab => {
            expect(wrapper.find(tab).attributes().disabled).toBeUndefined();
        });
    });

    it('should show tab content correctly', async () => {
        Shopware.State.commit('swOrder/setCustomer', {
            id: '1234',
        });
        const wrapper = await createWrapper();

        expect(wrapper.find('sw-order-customer-grid-stub')
            .attributes('style')).toBeUndefined();

        expect(wrapper.find('sw-order-line-items-grid-sales-channel-stub')
            .attributes('style')).toBe('display: none;');

        expect(wrapper.find('sw-order-create-options-stub')
            .exists()).toBeFalsy();

        await wrapper.find('.sw-tabs').setData({
            active: 'products',
        });

        expect(wrapper.find('sw-order-customer-grid-stub')
            .attributes('style')).toBe('display: none;');

        expect(wrapper.find('sw-order-line-items-grid-sales-channel-stub')
            .attributes('style')).toBeFalsy();

        expect(wrapper.find('sw-order-create-options-stub')
            .exists()).toBeFalsy();

        await wrapper.find('.sw-tabs').setData({
            active: 'options',
        });

        expect(wrapper.find('sw-order-customer-grid-stub')
            .attributes('style')).toBe('display: none;');

        expect(wrapper.find('sw-order-line-items-grid-sales-channel-stub')
            .attributes('style')).toBe('display: none;');

        expect(wrapper.find('sw-order-create-options-stub')
            .exists()).toBeTruthy();
    });

    it('should emit modal-close when click cancel button', async () => {
        const wrapper = await createWrapper();
        const buttonCancel = wrapper.find('.sw-order-create-initial-modal__button-cancel');

        await buttonCancel.trigger('click');
        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('should cancel cart when click cancel button', async () => {
        Shopware.State.commit('swOrder/setCartToken', cartToken);

        const wrapper = await createWrapper();
        const spyCancelCart = jest.spyOn(wrapper.vm, 'cancelCart');

        const buttonCancel = wrapper.find('.sw-order-create-initial-modal__button-cancel');
        await buttonCancel.trigger('click');

        expect(spyCancelCart).toHaveBeenCalled();
    });

    it('should be able to save line item', async () => {
        const wrapper = await createWrapper();

        const productGrid = wrapper.find('sw-order-line-items-grid-sales-channel-stub');
        productGrid.vm.$emit('on-save-item', lineItem);

        await flushPromises();

        expect(wrapper.vm.cart.lineItems).toEqual([lineItem]);
    });

    it('should be able to remove line item', async () => {
        const wrapper = await createWrapper();

        const productGrid = wrapper.find('sw-order-line-items-grid-sales-channel-stub');
        productGrid.vm.$emit('on-remove-items', ['product1']);

        await flushPromises();

        expect(wrapper.vm.cart.lineItems).toEqual([]);
    });

    it('should able to get disable auto promotion value when it is toggled', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-tabs').setData({
            active: 'options',
        });

        expect(wrapper.vm.disabledAutoPromotion).toBeFalsy();

        const optionsView = wrapper.find('sw-order-create-options-stub');
        optionsView.vm.$emit('auto-promotion-toggle', true);

        expect(wrapper.vm.disabledAutoPromotion).toBeTruthy();
    });

    it('should able to get promotion codes change', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-tabs').setData({
            active: 'options',
        });

        expect(wrapper.vm.promotionCodes).toEqual([]);

        const optionsView = wrapper.find('sw-order-create-options-stub');
        optionsView.vm.$emit('promotions-change', ['DISCOUNT', 'XMAS']);

        expect(wrapper.vm.promotionCodes).toEqual(['DISCOUNT', 'XMAS']);
    });

    it('should able to get shipping cost change', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-tabs').setData({
            active: 'options',
        });

        expect(wrapper.vm.shippingCosts).toBeNull();

        const optionsView = wrapper.find('sw-order-create-options-stub');
        optionsView.vm.$emit('shipping-cost-change', 100);

        expect(wrapper.vm.shippingCosts).toBe(100);
    });

    it('should able to preview order', async () => {
        Shopware.State.commit('swOrder/setCart', {
            token: cartToken,
            lineItems: [],
            deliveries: [{
                shippingCosts: {
                    totalPrice: 50,
                },
            }],
        });

        const wrapper = await createWrapper();

        await wrapper.find('.sw-tabs').setData({
            active: 'options',
        });

        const optionsView = wrapper.find('sw-order-create-options-stub');
        optionsView.vm.$emit('auto-promotion-toggle', true);
        optionsView.vm.$emit('promotions-change', ['DISCOUNT']);
        optionsView.vm.$emit('shipping-cost-change', 100);

        const buttonPreview = wrapper.find('.sw-order-create-initial-modal__button-preview');
        await buttonPreview.trigger('click');

        await flushPromises();

        expect(wrapper.emitted('order-preview')).toBeTruthy();
    });

    it('should update context when salesChannelContext change', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.context).toEqual({
            currencyId: '',
            paymentMethodId: '',
            shippingMethodId: '',
            languageId: '',
            billingAddressId: '',
            shippingAddressId: '',
        });

        Shopware.State.commit('swOrder/setContext', {
            context: {
                currencyId: 'euro',
                languageIdChain: [
                    'english',
                ],
            },
            shippingMethod: {
                id: 'standard',
            },
            paymentMethod: {
                id: 'cash',
            },
            customer: {
                activeBillingAddress: {
                    id: '1234',
                },
                activeShippingAddress: {
                    id: '5678',
                },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.context).toEqual({
            currencyId: 'euro',
            paymentMethodId: 'cash',
            shippingMethodId: 'standard',
            languageId: 'english',
            billingAddressId: '1234',
            shippingAddressId: '5678',
        });
    });
});
