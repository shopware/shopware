import { mount } from '@vue/test-utils_v3';
import EntityCollection from 'src/core/data/entity-collection.data';
import orderStore from 'src/module/sw-order/state/order.store';
import 'src/module/sw-order/mixin/cart-notification.mixin';

const addresses = [
    {
        id: '1',
        city: 'San Francisco',
        zipcode: '10332',
        street: 'Summerfield 27',
        country: {
            translated: {
                name: 'USA',
            },
        },
        countryState: {
            translated: {
                name: 'California',
            },
        },
    },
    {
        id: '2',
        city: 'London',
        zipcode: '48624',
        street: 'Ebbinghoff 10',
        country: {
            translated: {
                name: 'United Kingdom',
            },
        },
        countryState: {
            translated: {
                name: 'Nottingham',
            },
        },
    },
];

const customerData = {
    id: '123',
    salesChannel: {
        languageId: 'english',
    },
    billingAddressId: '1',
    shippingAddressId: '2',
    addresses: new EntityCollection(
        '/customer-address',
        'customer-address',
        null,
        null,
        [],
    ),
};

const context = {
    salesChannel: {
        id: '1',
    },
    customer: {
        ...customerData,
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

async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-create-options', { sync: true }), {
        props: {
            promotionCodes: [],
            disabledAutoPromotion: false,
            context: {
                languageId: 'english',
                billingAddressId: '1',
                shippingAddressId: '2',
            },
        },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(addresses),
                        };
                    },
                },
            },
            stubs: {
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'sw-popover': {
                    template: '<div class="sw-popover"><slot></slot></div>',
                },
                'sw-single-select': await wrapTestComponent('sw-single-select', { sync: true }),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list', { sync: true }),
                'sw-select-base': await wrapTestComponent('sw-select-base', { sync: true }),
                'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
                'sw-order-customer-address-select': await wrapTestComponent('sw-order-customer-address-select', { sync: true }),
                'sw-switch-field': await wrapTestComponent('sw-switch-field', { sync: true }),
                'sw-text-field': true,
                'sw-entity-single-select': {
                    props: ['value'],
                    template: '<input class="sw-entity-single-select" :value="value" @input="$emit(\'input\', $event.target.value)">',
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
                'sw-highlight-text': true,
                'sw-loader': true,
                'sw-icon': true,
                'sw-field-error': true,
                'sw-number-field': {
                    template: `
                        <div class="sw-number-field">
                            <input type="number" :value="value" @input="$emit('change', Number($event.target.value))" />
                            <slot name="suffix"></slot>
                        </div>
                    `,
                    props: {
                        value: 0,
                    },
                },
                'sw-select-result': {
                    props: ['item', 'index'],
                    template: `<li class="sw-select-result" @click.stop="onClickResult">
                                    <slot></slot>
                            </li>`,
                    methods: {
                        onClickResult() {
                            this.$parent.$parent.$emit('item-select', this.item);
                        },
                    },
                },
            },
        },
    });
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

        Shopware.State.registerModule('swOrder', {
            ...orderStore,
            state: {
                ...orderStore.state,
                customer: {
                    ...customerData,
                },
                cart,
                context,
            },
        });
    });

    it('should show address option correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const billingAddressSelect = wrapper.find('.sw-order-create-options__billing-address .sw-select__selection');
        // Click to open result list
        await billingAddressSelect.trigger('click');

        expect(wrapper.find('li[selected="true"]').text()).toBe('Summerfield 27, 10332, San Francisco, California, USA');
        expect(wrapper.find('sw-highlight-text-stub').attributes().text).toBe('Ebbinghoff 10, 48624, London, Nottingham, United Kingdom');
    });


    it('should able to set shipping address same as billing address', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        let shippingSelectionText = wrapper.find('.sw-order-create-options__shipping-address .sw-single-select__selection-text');
        expect(shippingSelectionText.text()).toBe('Ebbinghoff 10, 48624, London, Nottingham, United Kingdom');

        const switchSameAddress = wrapper.find('.sw-field--switch__input input[name="sw-field--isSameAsBillingAddress"]');
        await switchSameAddress.setChecked(true);

        expect(wrapper.vm.context.shippingAddressId).toBe('1');

        shippingSelectionText = wrapper.find('.sw-order-create-options__shipping-address .sw-single-select__selection-text');
        expect(shippingSelectionText.text())
            .toBe('sw-order.initialModal.options.textSameAsBillingAddress');

        expect(wrapper.findComponent('.sw-order-create-options__shipping-address').vm.disabled).toBe(true);
    });

    it('should disable shipping address when toggle on same as billing address switch', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const switchSameAddress = wrapper.find('.sw-field--switch__input input[name="sw-field--isSameAsBillingAddress"]');
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

        const switchSameAddress = wrapper.find('.sw-field--switch__input input[name="sw-field--isSameAsBillingAddress"]');
        expect(switchSameAddress.element.checked).toBeTruthy();


        await switchSameAddress.setChecked(false);

        expect(wrapper.find('.sw-order-create-options__shipping-address')
            .attributes('disabled')).toBeUndefined();
    });

    it('should switch on same as billing toogle when selecting billing address the same as shipping address', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        let shippingSelectionText = wrapper.find('.sw-order-create-options__shipping-address .sw-single-select__selection-text');
        expect(shippingSelectionText.text()).toBe('Ebbinghoff 10, 48624, London, Nottingham, United Kingdom');

        const billingAddressSelect = wrapper.find('.sw-order-create-options__billing-address .sw-select__selection');
        // Click to open result list
        await billingAddressSelect.trigger('click');

        const addressOptions = wrapper.findAll('.sw-select-result');
        await addressOptions.at(1).trigger('click');

        shippingSelectionText = wrapper.find('.sw-order-create-options__shipping-address .sw-single-select__selection-text');
        expect(shippingSelectionText.text()).toBe('sw-order.initialModal.options.textSameAsBillingAddress');

        expect(wrapper.vm.context.billingAddressId).toBe('2');
    });

    it('should emit auto-promotion-toggle when toggling disable auto promotion', async () => {
        const wrapper = await createWrapper();

        const disableAutoPromotionSwitch = wrapper.find('.sw-order-create-options__disable-auto-promotion input');
        await disableAutoPromotionSwitch.setChecked(true);

        expect(wrapper.emitted('auto-promotion-toggle')).toBeTruthy();
        expect(wrapper.emitted('auto-promotion-toggle')[0][0]).toBeTruthy();
    });

    it('should able to select currency', async () => {
        const wrapper = await createWrapper();

        let shippingCostField = wrapper.find('.sw-order-create-options__shipping-cost');
        expect(shippingCostField.text()).toBe('€');

        const currencyInput = wrapper.findComponent('.sw-order-create-options__currency-select');
        await currencyInput.vm.$emit('update:value', 'USD');
        await flushPromises();

        shippingCostField = wrapper.find('.sw-order-create-options__shipping-cost');
        expect(shippingCostField.text()).toBe('$');
    });

    it('should emit shipping-cost-change event when edit shipping cost field', async () => {
        const wrapper = await createWrapper();

        const shippingCostField = wrapper.findComponent('.sw-order-create-options__shipping-cost');
        await shippingCostField.vm.$emit('update:value', 100);

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

        Shopware.Service('cartStoreService').getCart = jest.fn(() => Promise.resolve({
            data: {
                lineItems: [],
                deliveries: [{
                    shippingCosts: {
                        totalPrice: 100,
                    },
                }],
            },
        }));

        const shippingMethodSelect = wrapper.findComponent('.sw-order-create-options__shipping-method');
        await shippingMethodSelect.vm.$emit('update:value', 100);
        await flushPromises();

        expect(shippingCostField.element.value).toBe('100');
    });
});
