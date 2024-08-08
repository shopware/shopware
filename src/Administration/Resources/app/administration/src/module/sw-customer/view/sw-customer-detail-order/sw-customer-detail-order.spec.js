import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

/**
 * @package checkout
 * @group disabledCompat
 */

const orderFixture = [{
    orderNumber: '10062',
    id: '1234',
    taxStatus: 'net',
    amountNet: 80,
    amountTotal: 100,
    orderDate: '2022-05-17T00:00:00.000+00:00',
}];

function getOrderCollection(collection = []) {
    return new EntityCollection(
        '/order',
        'order',
        null,
        { isShopwareContext: true },
        collection,
        collection.length,
        null,
    );
}

async function createWrapper(orderData = []) {
    return mount(await wrapTestComponent('sw-customer-detail-order', { sync: true }), {
        global: {
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => {
                                const response = getOrderCollection(orderData);
                                response.total = orderData.length;
                                return Promise.resolve(response);
                            },
                        };
                    },
                },

            },

            stubs: {
                'sw-card': {
                    template: `<div class="sw-card">
                    <slot name="toolbar"></slot>
                    <slot name="grid"></slot>
                    <slot></slot>
                </div>`,
                },
                'sw-card-filter': {
                    template: '<div class="sw-card-filter"><slot name="filter"></slot></div>',
                },
                'sw-empty-state': true,
                'sw-entity-listing': true,
                'sw-button': true,
                'sw-icon': true,
                'router-link': true,
                'sw-time-ago': true,
                'sw-context-menu-item': true,
            },
        },

        props: {
            customerEditMode: false,
            customer: {
                id: '1234',
            },
        },
    });
}

describe('module/sw-customer/view/sw-customer-detail-order.spec.js', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show empty state', async () => {
        const emptyState = wrapper.find('sw-empty-state-stub');
        const cardFilter = wrapper.find('.sw-card-filter');

        expect(emptyState.exists()).toBeTruthy();
        expect(cardFilter.exists()).toBeFalsy();
    });

    it('should show order list', async () => {
        wrapper = await createWrapper(orderFixture);
        await wrapper.vm.$nextTick();

        const cardFilter = wrapper.find('.sw-card-filter');
        const orderList = wrapper.find('sw-entity-listing-stub');

        expect(cardFilter.exists()).toBeTruthy();
        expect(orderList.exists()).toBeTruthy();
    });
});
