import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

/**
 * @sw-package checkout
 */

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

function addressMatchesSearch(address, searchTerm) {
    const searchableValues = [
        address.street,
        address.zipcode,
        address.city,
        address.country?.translated?.name,
        address.countryState?.translated?.name,
    ].filter(Boolean);

    return searchableValues.some((value) => value.toLowerCase().includes(searchTerm.toLowerCase()));
}

const customerData = {
    id: '123',
    salesChannel: {
        languageId: 'english',
    },
    billingAddressId: '1',
    shippingAddressId: '2',
    addresses: new EntityCollection('/customer-address', 'customer-address', null, null, addresses),
};

async function createWrapper({ availableAddresses = addresses, props = {} } = {}) {
    const wrapper = mount(
        await wrapTestComponent('sw-order-customer-address-select', {
            sync: true,
        }),
        {
            props: {
                customer: {
                    ...customerData,
                    addresses: new EntityCollection('/customer-address', 'customer-address', null, null, availableAddresses),
                },
                value: '1',
                sameAddressLabel: 'Same address',
                sameAddressValue: '2',
                ...props,
            },
            global: {
                stubs: {
                    'sw-popover': {
                        template: '<div class="sw-popover"><slot></slot></div>',
                    },
                    'sw-single-select': await wrapTestComponent('sw-single-select', { sync: true }),
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list', { sync: true }),
                    'sw-select-base': await wrapTestComponent('sw-select-base', { sync: true }),
                    'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                    'sw-base-field': await wrapTestComponent('sw-base-field', {
                        sync: true,
                    }),
                    'sw-highlight-text': true,
                    'sw-loader': true,
                    'sw-field-error': true,
                    'sw-select-result': {
                        props: [
                            'item',
                            'index',
                        ],
                        template: `<li class="sw-select-result" @click.stop="onClickResult">
                                    <slot></slot>
                            </li>`,
                        methods: {
                            onClickResult() {
                                this.$parent.$parent.$emit('item-select', this.item);
                            },
                        },
                    },
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: (criteria) => {
                                    const filteredAddresses = criteria.term
                                        ? availableAddresses.filter((address) =>
                                              addressMatchesSearch(address, criteria.term),
                                          )
                                        : availableAddresses;

                                    const collection = new EntityCollection(
                                        '/customer-address',
                                        'customer-address',
                                        null,
                                        null,
                                        filteredAddresses.slice(0, criteria.limit ?? filteredAddresses.length),
                                    );

                                    return Promise.resolve(collection);
                                },
                                get: () => Promise.resolve(),
                            };
                        },
                    },
                },
            },
        },
    );

    return wrapper;
}

describe('src/module/sw-order/component/sw-order-customer-address-select', () => {
    it('should show address option correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const billingAddressSelect = wrapper.find('.sw-select__selection');
        // Click to open result list
        await billingAddressSelect.trigger('click');

        expect(wrapper.find('li[selected="true"]').text()).toBe('Summerfield 27, 10332, San Francisco, California, USA');
        expect(wrapper.find('sw-highlight-text-stub').attributes().text).toBe(
            'Ebbinghoff 10, 48624, London, Nottingham, United Kingdom',
        );
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

    it('should replace the options with matching search results', async () => {
        jest.useFakeTimers();

        const wrapper = await createWrapper();
        await flushPromises();

        const singleSelect = wrapper.findComponent('.sw-single-select');
        await singleSelect.find('.sw-select__selection').trigger('click');

        const initialResultItems = singleSelect.findAll('.sw-select-result');
        expect(initialResultItems).toHaveLength(2);

        const input = wrapper.find('.sw-single-select__selection-input');
        await input.setValue('Eb');
        await input.trigger('input');

        jest.advanceTimersByTime(100);

        await flushPromises();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.customerAddresses.map((address) => address.id)).toEqual([
            '1',
            '2',
        ]);
    });

    it('should find customer addresses beyond the first 25 entries', async () => {
        jest.useFakeTimers();

        const manyAddresses = Array.from({ length: 30 }, (_, index) => ({
            id: `${index + 1}`,
            city: index === 25 ? 'Needle City' : `City ${index + 1}`,
            zipcode: `${10000 + index}`,
            street: `Street ${index + 1}`,
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
        }));

        const wrapper = await createWrapper({ availableAddresses: manyAddresses });
        await flushPromises();

        const singleSelect = wrapper.findComponent('.sw-single-select');
        await singleSelect.find('.sw-select__selection').trigger('click');

        expect(singleSelect.findAll('.sw-select-result')).toHaveLength(30);

        const input = wrapper.find('.sw-single-select__selection-input');
        await input.setValue('Needle');
        await input.trigger('input');

        jest.advanceTimersByTime(100);

        await flushPromises();
        await wrapper.vm.$nextTick();

        const resultItems = singleSelect.findAll('.sw-select-result');

        expect(resultItems).toHaveLength(2);
        expect(wrapper.vm.customerAddresses.map((address) => address.id)).toEqual([
            '1',
            '26',
        ]);
        expect(wrapper.vm.customerAddresses[1].city).toBe('Needle City');
    });

    it('should not keep unrelated addresses when the search has no matches', async () => {
        jest.useFakeTimers();

        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-select__selection').trigger('click');

        const input = wrapper.find('.sw-single-select__selection-input');
        await input.setValue('Does not exist');
        await input.trigger('input');

        jest.advanceTimersByTime(100);

        await flushPromises();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.customerAddresses.map((address) => address.id)).toEqual(['1']);
    });

    it('should keep the selected address available when it is outside the filtered result set', async () => {
        jest.useFakeTimers();

        const manyAddresses = Array.from({ length: 30 }, (_, index) => ({
            id: `${index + 1}`,
            city: index === 25 ? 'Needle City' : `City ${index + 1}`,
            zipcode: `${10000 + index}`,
            street: index === 25 ? 'Search Result Street' : `Street ${index + 1}`,
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
        }));

        const wrapper = await createWrapper({
            availableAddresses: manyAddresses,
            props: {
                value: '30',
                sameAddressValue: '1',
            },
        });
        await flushPromises();

        await wrapper.find('.sw-select__selection').trigger('click');

        const input = wrapper.find('.sw-single-select__selection-input');
        await input.setValue('Needle');
        await input.trigger('input');

        jest.advanceTimersByTime(100);

        await flushPromises();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.customerAddresses.map((address) => address.id)).toEqual([
            '30',
            '26',
        ]);

        const selectionLabel = wrapper.find('.sw-single-select__selection-text');
        expect(selectionLabel.text()).toBe('Street 30, 10029, City 30, California, USA');
    });

    it('should reload addresses on customer change', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const spyGetCustomerAddresses = jest.spyOn(wrapper.vm, 'getCustomerAddresses');

        await wrapper.setProps({
            customer: {
                ...customerData,
                id: '456',
            },
        });

        expect(spyGetCustomerAddresses).toHaveBeenCalledTimes(1);
    });
});
