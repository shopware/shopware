import { mount } from '@vue/test-utils';
import 'src/module/sw-order/mixin/cart-notification.mixin';

/**
 * @sw-package checkout
 */

const customerData = {
    id: '123',
    salesChannel: {
        languageId: 'english',
    },
    billingAddressId: '1',
    shippingAddressId: '2',
};

const context = {
    salesChannel: {
        id: '1',
    },
    shippingMethod: {
        id: 'shipping-method-1',
    },
    customer: {
        ...customerData,
    },
    context: {
        currencyId: '1',
    },
    currency: {
        isoCode: 'EUR',
        symbol: '€',
        totalRounding: {
            decimals: 2,
        },
    },
};

const cart = {
    token: 'is-exactly-32-chars-as-required-',
    deliveries: [],
    lineItems: [],
};

const cartResponse = {
    data: cart,
};

const contextResponse = {
    data: {
        ...context,
        currency: {
            id: '1',
            isoCode: 'USD',
            symbol: '$',
            totalRounding: {
                decimals: 2,
            },
        },
    },
};

const contextStore = {
    id: 'context',
    state: () => ({
        api: {
            languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
            systemLanguageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
        },
    }),
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-create-options', { sync: true }), {
        props: {
            promotionCodes: [],
            disabledAutoPromotion: false,
            sendOrderConfirmationMail: true,
            context: {
                languageId: 'english',
                billingAddressId: '1',
                shippingAddressId: '2',
            },
        },
        global: {
            stubs: {
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'sw-order-customer-address-select': {
                    props: [
                        'value',
                        'sameAddressValue',
                        'sameAddressLabel',
                        'disabled',
                    ],
                    emits: ['update:value'],
                    template: `
                        <button
                            class="sw-order-customer-address-select"
                            :disabled="disabled"
                            @click="$emit('update:value', sameAddressValue)"
                        ></button>`,
                },

                'sw-text-field': true,
                'sw-entity-single-select': {
                    props: ['value'],
                    template:
                        '<input class="sw-entity-single-select" :value="value" @input="$emit(\'input\', $event.target.value)">',
                },
                'sw-multi-tag-select': {
                    props: ['value', 'validate'],
                    template: `
                        <div class="sw-multi-tag-select">
                            <ul>
                                <li v-for="item in value">{{ item }}</li>
                            </ul>
                            <input @input="updateTags">
                        </div>
                    `,
                    methods: {
                        updateTags(event) {
                            if (!this.validate(event.target.value)) {
                                return;
                            }

                            this.$emit('change', [...this.value, event.target.value]);
                        },
                    },
                },
            },
        },
    });
}

function getShippingAddressSelect(wrapper) {
    return wrapper.findComponent('.sw-order-create-options__shipping-address');
}

describe('src/module/sw-order/view/sw-order-create-options', () => {
    beforeAll(() => {
        Shopware.Service().register('contextStoreService', () => {
            return {
                updateContext: () => Promise.resolve({}),
                getSalesChannelContext: () => Promise.resolve(contextResponse),
            };
        });

        Shopware.Service().register('cartStoreService', () => {
            return {
                getCart: () => Promise.resolve(cartResponse),
            };
        });

        Shopware.Store.get('swOrder').setCart(cart);
        Shopware.Store.get('swOrder').setContext(context);
        Shopware.Store.get('swOrder').setCustomer(customerData);

        if (Shopware.Store.get('context')) {
            Shopware.Store.unregister('context');
        }

        Shopware.Store.register(contextStore);
    });

    it('should pass the addresses to the address selects', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.findComponent('.sw-order-create-options__billing-address').props()).toEqual(
            expect.objectContaining({ value: '1', sameAddressValue: '2' }),
        );
        expect(wrapper.findComponent('.sw-order-create-options__shipping-address').props()).toEqual(
            expect.objectContaining({
                value: '2',
                sameAddressValue: '1',
                sameAddressLabel: 'sw-order.initialModal.options.textSameAsBillingAddress',
            }),
        );
    });

    it('should able to set shipping address same as billing address', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(getShippingAddressSelect(wrapper).props('value')).toBe('2');

        const switchSameAddress = wrapper.find('.mt-switch input[name="sw-field--isSameAsBillingAddress"]');
        await switchSameAddress.setChecked(true);

        expect(wrapper.vm.context.shippingAddressId).toBe('1');

        expect(getShippingAddressSelect(wrapper).props()).toEqual(
            expect.objectContaining({
                value: '1',
                sameAddressValue: '1',
                sameAddressLabel: 'sw-order.initialModal.options.textSameAsBillingAddress',
            }),
        );

        expect(wrapper.findComponent('.sw-order-create-options__shipping-address').vm.disabled).toBe(true);
    });

    it('should disable shipping address when toggle on same as billing address switch', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const switchSameAddress = wrapper.find('.mt-switch input[name="sw-field--isSameAsBillingAddress"]');
        expect(switchSameAddress.element.checked).toBeFalsy();

        await switchSameAddress.setChecked(true);

        expect(wrapper.findComponent('.sw-order-create-options__shipping-address').vm.disabled).toBe(true);
    });

    it('should enable shipping address when toggle on same as billing address switch', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            context: {
                languageId: 'english',
                billingAddressId: '1',
                shippingAddressId: '1',
            },
        });

        const switchSameAddress = wrapper.find('.mt-switch input[name="sw-field--isSameAsBillingAddress"]');
        expect(switchSameAddress.element.checked).toBeTruthy();

        await switchSameAddress.setChecked(false);

        expect(wrapper.find('.sw-order-create-options__shipping-address').attributes('disabled')).toBeUndefined();
    });

    it('should switch on same as billing toogle when selecting billing address the same as shipping address', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(getShippingAddressSelect(wrapper).props('value')).toBe('2');

        await wrapper.find('.sw-order-create-options__billing-address').trigger('click');

        expect(getShippingAddressSelect(wrapper).props()).toEqual(
            expect.objectContaining({
                value: '2',
                sameAddressValue: '2',
                sameAddressLabel: 'sw-order.initialModal.options.textSameAsBillingAddress',
            }),
        );

        expect(wrapper.vm.context.billingAddressId).toBe('2');
    });

    it('should emit auto-promotion-toggle when toggling disable auto promotion', async () => {
        const wrapper = await createWrapper();

        const disableAutoPromotionSwitch = wrapper.find('.sw-order-create-options__disable-auto-promotion input');
        await disableAutoPromotionSwitch.setChecked(true);

        expect(wrapper.emitted('auto-promotion-toggle')).toBeTruthy();
        expect(wrapper.emitted('auto-promotion-toggle')[0][0]).toBeTruthy();
    });

    it('should emit send-order-confirmation-mail-toggle when toggling order confirmation mail', async () => {
        const wrapper = await createWrapper();

        const sendOrderConfirmationMailSwitch = wrapper.find('.sw-order-create-options__send-order-confirmation-mail input');
        expect(sendOrderConfirmationMailSwitch.element.checked).toBeTruthy();

        await sendOrderConfirmationMailSwitch.setChecked(false);

        expect(wrapper.emitted('send-order-confirmation-mail-toggle')).toBeTruthy();
        expect(wrapper.emitted('send-order-confirmation-mail-toggle')[0][0]).toBeFalsy();
    });

    it('should able to select currency', async () => {
        const wrapper = await createWrapper();

        let shippingCostField = wrapper.find('.sw-order-create-options__shipping-cost .mt-field__addition:not(.is--prefix)');
        expect(shippingCostField.text()).toBe('€');

        const currencyInput = wrapper.findComponent('.sw-order-create-options__currency-select');
        await currencyInput.vm.$emit('update:value', 'USD');
        await flushPromises();

        shippingCostField = wrapper.find('.sw-order-create-options__shipping-cost .mt-field__addition:not(.is--prefix)');
        expect(shippingCostField.text()).toBe('$');
    });

    it('should not update cart context while hydrating existing sales channel values', async () => {
        const wrapper = await createWrapper();
        const updateOrderContextSpy = jest.spyOn(Shopware.Store.get('swOrder'), 'updateOrderContext');

        await wrapper.setProps({
            context: {
                ...wrapper.vm.context,
                currencyId: '1',
                shippingMethodId: 'shipping-method-1',
            },
        });
        await flushPromises();

        expect(updateOrderContextSpy).not.toHaveBeenCalled();
    });

    it('should emit shipping-cost-change event when edit shipping cost field', async () => {
        const wrapper = await createWrapper();

        const shippingCostField = wrapper.findComponent('.sw-order-create-options__shipping-cost');
        await shippingCostField.vm.$emit('update:modelValue', 100);

        expect(wrapper.emitted('shipping-cost-change')).toBeTruthy();
        expect(wrapper.emitted('shipping-cost-change')[0][0]).toBe(100);
    });

    it('should emit promotions-change event when adding a promotion code', async () => {
        const wrapper = await createWrapper();

        const promotionField = wrapper.findComponent('.sw-order-create-options__promotion-code');
        await promotionField.vm.$emit('update:value', ['DISCOUNT']);

        expect(wrapper.emitted('promotions-change')).toBeTruthy();
        expect(wrapper.emitted('promotions-change')[0][0]).toEqual(['DISCOUNT']);
    });

    it('should not emit promotions-change event when entering duplicated promotion code', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            promotionCodes: ['DISCOUNT'],
        });

        const promotionField = wrapper.find('.sw-order-create-options__promotion-code input');
        await promotionField.setValue('DISCOUNT');
        await promotionField.trigger('input');

        expect(wrapper.emitted('promotions-change')).toBeFalsy();
    });

    it('should not emit promotions-change event when entering empty promotion code', async () => {
        const wrapper = await createWrapper();

        const promotionField = wrapper.find('.sw-order-create-options__promotion-code input');
        await promotionField.setValue('     ');
        await promotionField.trigger('input');

        expect(wrapper.emitted('promotions-change')).toBeFalsy();
    });

    it('should update context when selecting shipping method', async () => {
        const wrapper = await createWrapper();

        const shippingCostField = wrapper.find('.sw-order-create-options__shipping-cost input');
        expect(shippingCostField.element.value).toBe('0');

        Shopware.Service('cartStoreService').getCart = jest.fn(() =>
            Promise.resolve({
                data: {
                    lineItems: [],
                    deliveries: [
                        {
                            shippingCosts: {
                                totalPrice: 100,
                            },
                        },
                    ],
                },
            }),
        );

        const shippingMethodSelect = wrapper.findComponent('.sw-order-create-options__shipping-method');
        await shippingMethodSelect.vm.$emit('update:value', 100);
        await flushPromises();

        expect(shippingCostField.element.value).toBe('100');
    });

    it('should be set context language when language selected', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            context: {
                ...wrapper.vm.context,
                languageId: null,
            },
        });

        expect(Shopware.Store.get('context').api.languageId).toBe(contextStore.state().api.languageId);

        await wrapper.setProps({
            context: {
                ...wrapper.vm.context,
                languageId: '1234',
            },
        });

        expect(Shopware.Store.get('context').api.languageId).toBe('1234');
    });
});
