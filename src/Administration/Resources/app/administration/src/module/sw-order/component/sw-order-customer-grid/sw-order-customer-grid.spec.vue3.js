import { mount } from '@vue/test-utils_v3';
import orderState from 'src/module/sw-order/state/order.store';

let customerData = [];

function setCustomerData(customers) {
    customerData = [...customers];
    customerData.total = customers.length;
    customerData.criteria = {
        page: 1,
        limit: 5,
    };
}

const customers = generateCustomers();

function generateCustomers() {
    const items = [];

    for (let i = 1; i <= 10; i += 1) {
        items.push({
            id: i,
            firstName: `Quynh ${i}`,
            lastName: 'Nguyen',
            email: `quynh${i}@example.com`,
            salesChannelId: '1234',
            customerNumber: i,
            salesChannel: {
                translated: {
                    name: 'Storefront',
                },
            },
        });
    }

    return items;
}

async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-customer-grid', { sync: true }), {
        global: {
            stubs: {
                'sw-card': {
                    template: `
                        <div class="sw-card__content">
                            <slot name="toolbar"></slot>
                            <slot name="grid"></slot>
                        </div>
                    `,
                },
                'sw-number-field': {
                    template: '<input class="sw-number-field" type="number" v-model="value" />',
                    props: {
                        value: 0,
                    },
                },
                'sw-checkbox-field': {
                    template: '<input class="sw-checkbox-field" type="checkbox" v-model="value" />',
                    props: {
                        value: false,
                    },
                },
                'sw-entity-listing': await wrapTestComponent('sw-entity-listing', { sync: true }),
                'sw-data-grid': await wrapTestComponent('sw-data-grid', { sync: true }),
                'sw-pagination': await wrapTestComponent('sw-pagination', { sync: true }),
                'sw-product-variant-info': true,
                'sw-data-grid-settings': true,
                'sw-data-grid-skeleton': true,
                'sw-context-button': {
                    template: '<div class="sw-context-button"><slot></slot></div>',
                },
                'sw-context-menu-item': true,
                'sw-empty-state': true,
                'sw-card-filter': {
                    props: ['value'],
                    template: '<input class="sw-card-filter" :value="value" @input="$emit(\'sw-card-filter-term-change\', $event.target.value)">',
                },
                'sw-icon': true,
                'sw-field': true,
                'router-link': true,
                'sw-button': {
                    template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>',
                },
                'sw-order-new-customer-modal': true,
            },
            provide: {
                searchRankingService: () => {},
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(customerData),
                            get: () => Promise.resolve({ ...customers[0] }),
                        };
                    },
                },
            },
            mocks: {
                $tc: (key, number, value) => {
                    if (!value) {
                        return key;
                    }
                    return key + JSON.stringify(value);
                },
            },
        },

    });
}


describe('src/module/sw-order/view/sw-order-customer-grid', () => {
    beforeAll(() => {
        Shopware.Service().register('contextStoreService', () => {
            return {
                updateCustomerContext: () => Promise.resolve({
                    status: 200,
                }),
            };
        });

        Shopware.Service().register('cartStoreService', () => {
            return {
                getCart: () => Promise.resolve({
                    data: {
                        token: 'token',
                        lineItems: [],
                    },
                }),
                createCart: () => Promise.resolve({
                    data: {
                        token: 'token',
                    },
                }),
            };
        });

        Shopware.State.registerModule('swOrder', {
            ...orderState,
            state: {
                cart: {
                    token: '',
                    lineItems: [],
                },
                context: {
                    customer: {},
                },
            },
        });
    });

    it('should show empty state view when there is no customer', async () => {
        setCustomerData([]);

        const wrapper = await createWrapper();
        await flushPromises();

        const emptyState = wrapper.find('sw-empty-state-stub');
        expect(emptyState.exists()).toBeTruthy();
    });

    it('should show empty title correctly', async () => {
        setCustomerData([]);

        const wrapper = await createWrapper();
        await flushPromises();

        let emptyState = wrapper.find('sw-empty-state-stub');
        expect(emptyState.attributes('title')).toBe('sw-customer.list.messageEmpty');

        const searchField = wrapper.find('.sw-card-filter');

        await searchField.setValue('Hello World');
        await searchField.trigger('input');

        emptyState = wrapper.find('sw-empty-state-stub');
        expect(emptyState.attributes('title'))
            .toBe('sw-order.initialModal.customerGrid.textEmptySearch{"name":"Hello World"}');
    });

    it('should show customer grid', async () => {
        setCustomerData(customers);

        const wrapper = await createWrapper();
        await flushPromises();

        const emptyState = wrapper.find('sw-empty-state-stub');
        expect(emptyState.exists()).toBeFalsy();

        const gridBody = wrapper.find('.sw-data-grid__body');
        expect(gridBody.findAll('.sw-data-grid__row')).toHaveLength(customers.length);
    });

    it('should able to search customer', async () => {
        setCustomerData(customers);

        const wrapper = await createWrapper();
        await flushPromises();

        let gridBody = wrapper.find('.sw-data-grid__body');
        expect(gridBody.findAll('.sw-data-grid__row')).toHaveLength(customers.length);

        setCustomerData([{ ...customers[1] }]);
        const searchField = wrapper.find('.sw-card-filter');

        await searchField.setValue('Quynh 2');
        await searchField.trigger('input');

        gridBody = wrapper.find('.sw-data-grid__body');
        expect(gridBody.findAll('.sw-data-grid__row')).toHaveLength(1);
    });

    it('should open add new customer modal', async () => {
        setCustomerData([]);

        const wrapper = await createWrapper();
        await flushPromises();

        const buttonAddCustomer = wrapper.find('.sw-order-customer-grid__add-customer');
        let modalAddCustomer = wrapper.find('sw-order-new-customer-modal-stub');

        expect(modalAddCustomer.exists()).toBeFalsy();

        await buttonAddCustomer.trigger('click');

        modalAddCustomer = wrapper.find('sw-order-new-customer-modal-stub');
        expect(modalAddCustomer.exists()).toBeTruthy();
    });

    it('should refresh grid list after adding new customer successfully', async () => {
        setCustomerData([]);

        const wrapper = await createWrapper();
        await flushPromises();

        const spyGetList = jest.spyOn(wrapper.vm, 'getList');
        const buttonAddCustomer = wrapper.find('.sw-order-customer-grid__add-customer');
        await buttonAddCustomer.trigger('click');

        const modalAddCustomer = wrapper.findComponent('sw-order-new-customer-modal-stub');
        modalAddCustomer.vm.$emit('on-select-existing-customer', 'customer1');

        expect(spyGetList).toHaveBeenCalled();
    });

    it('should create cart after selecting a customer if there is no cart token', async () => {
        setCustomerData(customers);

        const wrapper = await createWrapper();
        await flushPromises();

        const spyCreateCart = jest.spyOn(wrapper.vm, 'createCart');

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);

        await flushPromises();

        expect(spyCreateCart).toHaveBeenCalled();
    });

    it('should update customer context and cart after selecting a customer', async () => {
        setCustomerData(customers);
        Shopware.State.commit('swOrder/setCartToken', 'token');

        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.customerRepository.get = jest.fn(() => Promise.resolve(customers[0]));
        const spyUpdateCustomerContext = jest.spyOn(wrapper.vm, 'updateCustomerContext');
        const spyGetCart = jest.spyOn(wrapper.vm, 'getCart');

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);

        expect(spyUpdateCustomerContext).toHaveBeenCalled();

        await flushPromises();

        expect(spyGetCart).toHaveBeenCalled();
    });

    it('should check customer initially if customer exists', async () => {
        setCustomerData(customers);

        Shopware.State.commit('swOrder/setCustomer', {
            ...customers[0],
        });

        const wrapper = await createWrapper();

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        const firstRowRadioField = firstRow.find('.sw-field__radio-input input');

        await flushPromises();

        expect(firstRowRadioField.element.checked).toBeTruthy();
    });
});
