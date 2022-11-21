import { shallowMount, createLocalVue } from '@vue/test-utils';
import swOrderCustomerGrid from 'src/module/sw-order/component/sw-order-customer-grid';
import 'src/app/component/entity/sw-entity-listing';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/grid/sw-pagination';
import orderState from 'src/module/sw-order/state/order.store';

Shopware.Component.register('sw-order-customer-grid', swOrderCustomerGrid);

let customerData = [];

function setCustomerData(customers) {
    customerData = [...customers];
    customerData.total = customers.length;
    customerData.criteria = {
        page: 1,
        limit: 5
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
            email: `quynh${i}@example.com`
        });
    }

    return items;
}

async function createWrapper() {
    const localVue = createLocalVue();
    localVue.filter('currency', v => v);

    return shallowMount(await Shopware.Component.build('sw-order-customer-grid'), {
        localVue,
        propsData: {
            taxStatus: 'gross',
            currency: {
                shortName: 'EUR',
                symbol: '€'
            },
            salesChannelId: '1'
        },
        stubs: {
            'sw-card': {
                template: `
                    <div class="sw-card__content">
                         <slot name="toolbar"></slot>
                        <slot name="grid"></slot>
                    </div>
                `
            },
            'sw-number-field': {
                template: '<input class="sw-number-field" type="number" v-model="value" />',
                props: {
                    value: 0
                }
            },
            'sw-checkbox-field': {
                template: '<input class="sw-checkbox-field" type="checkbox" v-model="value" />',
                props: {
                    value: false
                }
            },
            'sw-entity-listing': await Shopware.Component.build('sw-entity-listing'),
            'sw-data-grid': await Shopware.Component.build('sw-data-grid'),
            'sw-pagination': await Shopware.Component.build('sw-pagination'),
            'sw-product-variant-info': true,
            'sw-data-grid-settings': true,
            'sw-data-grid-skeleton': true,
            'sw-context-button': {
                template: '<div class="sw-context-button"><slot></slot></div>'
            },
            'sw-context-menu-item': true,
            'sw-empty-state': true,
            'sw-card-filter': true,
            'sw-icon': true,
            'sw-field': true,
            'router-link': true,
            'sw-button': {
                template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>'
            },
            'sw-order-new-customer-modal': true,
        },
        provide: {
            searchRankingService: () => {},
            repositoryFactory: {
                create: () => {
                    return {
                        search: () => Promise.resolve(customerData)
                    };
                }
            }
        }
    });
}


describe('src/module/sw-order/view/sw-order-customer-grid', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swOrder', {
            ...orderState,
            state: {
                flow: {
                    id: '1234'
                },
                context: {
                    customer: {},
                },
            }
        });
    });

    it('should show empty state view when there is no customer', async () => {
        setCustomerData([]);

        const wrapper = await createWrapper();
        await flushPromises();

        const emptyState = wrapper.find('sw-empty-state-stub');
        expect(emptyState.exists()).toBeTruthy();
    });

    it('should show customer grid', async () => {
        setCustomerData(customers);

        const wrapper = await createWrapper();
        await flushPromises();

        const emptyState = wrapper.find('sw-empty-state-stub');
        expect(emptyState.exists()).toBeFalsy();

        const gridBody = wrapper.find('.sw-data-grid__body');
        expect(gridBody.findAll('.sw-data-grid__row').length).toEqual(customers.length);
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
});
