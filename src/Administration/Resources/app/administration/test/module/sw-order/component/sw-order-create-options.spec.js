import { createLocalVue, shallowMount, enableAutoDestroy } from '@vue/test-utils';
import flushPromises from 'flush-promises';
import EntityCollection from 'src/core/data/entity-collection.data';

import 'src/module/sw-order/mixin/cart-notification.mixin';
import 'src/module/sw-order/component/sw-order-create-options';

import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/select/base/sw-select-result-list';

import Vuex from 'vuex';
import orderStore from 'src/module/sw-order/state/order.store';

const addresses = [
    {
        id: '1',
        city: 'San Francisco',
        zipcode: '10332',
        street: 'Summerfield 27',
        country: {
            translated: {
                name: 'USA'
            }
        },
        countryState: {
            translated: {
                name: 'California'
            }
        }
    },
    {
        id: '2',
        city: 'London',
        zipcode: '48624',
        street: 'Ebbinghoff 10',
        country: {
            translated: {
                name: 'United Kingdom'
            }
        },
        countryState: {
            translated: {
                name: 'Nottingham'
            }
        }
    },
];

const customerData = {
    id: '123',
    salesChannel: {
        languageId: 'english'
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

function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Shopware.Component.build('sw-order-create-options'), {
        localVue,
        propsData: {
            promotionCodes: [],
            disabledAutoPromotions: false,
        },
        stubs: {
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-popover': {
                template: '<div class="sw-popover"><slot></slot></div>'
            },
            'sw-single-select': Shopware.Component.build('sw-single-select'),
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-switch-field': true,
            'sw-text-field': true,
            'sw-entity-single-select': true,
            'sw-multi-tag-select': true,
            'sw-highlight-text': true,
            'sw-loader': true,
            'sw-icon': true,
            'sw-field-error': true,
            'sw-select-result': {
                props: ['item', 'index'],
                template: `<li class="sw-select-result" @click.stop="onClickResult">
                                <slot></slot>
                           </li>`,
                methods: {
                    onClickResult() {
                        this.$parent.$parent.$emit('item-select', this.item);
                    }
                }
            },
        },
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        search: () => Promise.resolve(addresses),
                        get: () => Promise.resolve({})
                    };
                }
            }
        }
    });
}

enableAutoDestroy(afterEach);

describe('src/module/sw-order/view/sw-order-create-options', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swOrder', {
            ...orderStore,
            state: {
                ...orderStore.state,
                customer: {
                    ...customerData
                }
            },
        });
    });

    it('should show address option correctly', async () => {
        const wrapper = createWrapper();
        await flushPromises();

        const billingAddressSelect = wrapper.find('.sw-order-create-options__billing-address .sw-select__selection');
        // Click to open result list
        await billingAddressSelect.trigger('click');

        expect(wrapper.find('li[selected="selected"]').text()).toEqual('Summerfield 27, 10332, San Francisco, California, USA');
        expect(wrapper.find('sw-highlight-text-stub').attributes().text).toEqual('Ebbinghoff 10, 48624, London, Nottingham, United Kingdom');
    });

    it('should able to set billing address same as shipping address', async () => {
        const wrapper = createWrapper();
        await flushPromises();

        const billingAddressSelect = wrapper.find('.sw-order-create-options__billing-address .sw-select__selection');
        // Click to open result list
        await billingAddressSelect.trigger('click');

        const sameShippingAddressOption = wrapper.find('.sw-select-result__option-same-address');
        await sameShippingAddressOption.trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-order-create-options__billing-address .sw-single-select__selection-text').text())
            .toContain('Ebbinghoff 10, 48624, London, Nottingham, United Kingdom');
    });

    it('should able to set shipping address same as billing address', async () => {
        Shopware.State.commit('swOrder/setCustomer', { ...customerData });

        const wrapper = createWrapper();
        await flushPromises();

        const shippingAddressSelect = wrapper.find('.sw-order-create-options__shipping-address .sw-select__selection');
        // Click to open result list
        await shippingAddressSelect.trigger('click');

        const sameBillingAddressOption = wrapper.find('.sw-select-result__option-same-address');
        await sameBillingAddressOption.trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-order-create-options__shipping-address .sw-single-select__selection-text').text())
            .toContain('Summerfield 27, 10332, San Francisco, California, USA');
    });
});
