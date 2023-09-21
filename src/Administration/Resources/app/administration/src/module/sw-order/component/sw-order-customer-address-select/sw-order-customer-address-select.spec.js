import { createLocalVue, shallowMount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import swOrderCustomerAddressSelect from 'src/module/sw-order/component/sw-order-customer-address-select';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/select/base/sw-select-result-list';

import Vuex from 'vuex';

/**
 * @package checkout
 */

Shopware.Component.register('sw-order-customer-address-select', swOrderCustomerAddressSelect);

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

async function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(await Shopware.Component.build('sw-order-customer-address-select'), {
        localVue,
        propsData: {
            customer: { ...customerData },
            value: '1',
            sameAddressLabel: 'Same address',
            sameAddressValue: '2',
        },
        stubs: {
            'sw-popover': {
                template: '<div class="sw-popover"><slot></slot></div>',
            },
            'sw-single-select': await Shopware.Component.build('sw-single-select'),
            'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
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
                    },
                },
            },
        },
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        search: (criteria) => {
                            const collection = new EntityCollection(
                                '/customer-address',
                                'customer-address',
                                null,
                                null,
                                criteria.term !== null ? [addresses[0]] : addresses,
                            );

                            return Promise.resolve(collection);
                        },
                        get: () => Promise.resolve(),
                    };
                },
            },
        },
    });
}


describe('src/module/sw-order/component/sw-order-customer-address-select', () => {
    beforeAll(() => {
    });

    it('should show address option correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const billingAddressSelect = wrapper.find('.sw-select__selection');
        // Click to open result list
        await billingAddressSelect.trigger('click');

        expect(wrapper.find('li[selected="selected"]').text()).toBe('Summerfield 27, 10332, San Francisco, California, USA');
        expect(wrapper.find('sw-highlight-text-stub').attributes().text).toBe('Ebbinghoff 10, 48624, London, Nottingham, United Kingdom');
    });

    it('should able to show same address label', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            sameAddressValue: '1',
            sameAddressLabel: 'Same as billing address',
        });

        const selectionLabel = wrapper.find('.sw-single-select__selection-text');
        expect(selectionLabel.text()).toBe('Same as billing address');
    });

    it('should filter entries correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.searchAddress('test');

        expect(wrapper.vm.addressCriteria.term).toBe('test');
        expect(addresses[0].hidden).toBe(false);
        expect(addresses[1].hidden).toBe(true);
    });
});
