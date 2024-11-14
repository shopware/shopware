import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

/**
 * @package customer-order
 */

function getCollection(entity, collection) {
    return new EntityCollection(
        `/${entity}`,
        entity,
        null,
        { isShopwareContext: true },
        collection,
        collection.length,
        null,
    );
}

const stateHistoryFixture = [
    {
        entityName: 'order_delivery',
        fromStateMachineState: {
            technicalName: 'open',
            translated: {
                name: 'Open',
            },
        },
        toStateMachineState: {
            technicalName: 'shipped',
            translated: {
                name: 'Shipped',
            },
        },
        user: {
            username: 'admin',
        },
        createdAt: '2022-10-12T10:01:28.535+00:00',
    },
    {
        entityName: 'order_transaction',
        fromStateMachineState: {
            technicalName: 'open',
            translated: {
                name: 'Open',
            },
        },
        toStateMachineState: {
            technicalName: 'in_progress',
            translated: {
                name: 'In progress',
            },
        },
        user: {
            username: 'admin',
        },
        createdAt: '2022-10-12T10:01:33.815+00:00',
        referencedId: '2',
    },
];

const orderProp = {
    id: '1',
    orderDateTime: '2022-10-10T10:01:33.815+00:00',
    transactions: [
        {
            id: '2',
            stateMachineState: {
                technicalName: 'open',
                translated: {
                    name: 'Open',
                },
            },
        },
    ],
    deliveries: [
        {
            id: '3',
            stateMachineState: {
                technicalName: 'open',
                translated: {
                    name: 'Open',
                },
            },
        },
    ],
    stateMachineState: {
        technicalName: 'open',
        translated: {
            name: 'Open',
        },
    },
};

orderProp.transactions.last = () => ({
    stateMachineState: {
        technicalName: 'open',
        translated: {
            name: 'Open',
        },
    },
});

orderProp.deliveries.first = () => ({
    stateMachineState: {
        technicalName: 'open',
        translated: {
            name: 'Open',
        },
    },
});

describe('src/module/sw-order/component/sw-order-state-history-modal', () => {
    let SwOrderStateHistoryModal;

    async function createWrapper(options = {}, order = orderProp, stateHistory = stateHistoryFixture) {
        return mount(SwOrderStateHistoryModal, {
            global: {
                stubs: {
                    'sw-modal': {
                        template: '<div><slot></slot><slot name="modal-footer"></slot></div>',
                    },
                    'sw-data-grid': await wrapTestComponent('sw-data-grid', {
                        sync: true,
                    }),
                    'sw-data-grid-skeleton': true,
                    'sw-pagination': await wrapTestComponent('sw-pagination', {
                        sync: true,
                    }),
                    'sw-button': await wrapTestComponent('sw-button', {
                        sync: true,
                    }),
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                    'sw-icon': true,
                    'sw-time-ago': true,
                    'sw-label': {
                        template: '<div class="sw-label"><slot></slot></div>',
                    },
                    'sw-checkbox-field': true,
                    'sw-context-menu-item': true,
                    'sw-context-button': true,
                    'sw-data-grid-settings': true,
                    'sw-data-grid-column-boolean': true,
                    'sw-data-grid-inline-edit': true,
                    'router-link': true,
                    'sw-select-field': true,
                    'sw-loader': true,
                },
                provide: {
                    stateStyleDataProviderService: {
                        getStyle: () => {
                            return {
                                variant: '',
                            };
                        },
                    },
                    repositoryFactory: {
                        create: () => ({
                            search: () => {
                                if (options.error) {
                                    // eslint-disable-next-line prefer-promise-reject-errors
                                    return Promise.reject({
                                        response: {
                                            data: {
                                                errors: [
                                                    {
                                                        code: 'This is an error code',
                                                        detail: 'This is an detailed error message',
                                                    },
                                                ],
                                            },
                                        },
                                    });
                                }

                                return Promise.resolve(getCollection('state_machine_history', stateHistory));
                            },
                        }),
                    },
                },
            },
            data() {
                return {
                    ...options,
                };
            },
            props: {
                isLoading: false,
                order,
            },
        });
    }

    beforeAll(async () => {
        SwOrderStateHistoryModal = await wrapTestComponent('sw-order-state-history-modal', { sync: true });
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show state history grid correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const stateHistoryRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(stateHistoryRows).toHaveLength(3);

        const firstRow = stateHistoryRows.at(0);
        expect(firstRow.find('.sw-data-grid__cell--entity').text()).toBe('global.entities.order');
        expect(firstRow.find('.sw-data-grid__cell--user').text()).toBe('sw-order.stateHistoryModal.labelSystemUser');
        expect(firstRow.find('.sw-data-grid__cell--delivery').text()).toBe('Open');
        expect(firstRow.find('.sw-data-grid__cell--transaction').text()).toBe('Open');
        expect(firstRow.find('.sw-data-grid__cell--order').text()).toBe('Open');

        const secondRow = stateHistoryRows.at(1);
        expect(secondRow.find('.sw-data-grid__cell--entity').text()).toBe('global.entities.order_delivery');
        expect(secondRow.find('.sw-data-grid__cell--user').text()).toBe('admin');
        expect(secondRow.find('.sw-data-grid__cell--delivery').text()).toBe('Shipped');
        expect(secondRow.find('.sw-data-grid__cell--transaction').text()).toBe('Open');
        expect(secondRow.find('.sw-data-grid__cell--order').text()).toBe('Open');

        const thirdRow = stateHistoryRows.at(2);
        expect(thirdRow.find('.sw-data-grid__cell--entity').text()).toBe('global.entities.order_transaction');
        expect(thirdRow.find('.sw-data-grid__cell--user').text()).toBe('admin');
        expect(thirdRow.find('.sw-data-grid__cell--delivery').text()).toBe('Shipped');
        expect(thirdRow.find('.sw-data-grid__cell--transaction').text()).toBe('In progress');
        expect(thirdRow.find('.sw-data-grid__cell--order').text()).toBe('Open');
    });

    it('should error notification if loading state history failed', async () => {
        const wrapper = await createWrapper({
            error: true,
        });

        wrapper.vm.createNotificationError = jest.fn();
        const notificationMock = wrapper.vm.createNotificationError;

        await flushPromises();

        expect(notificationMock).toHaveBeenCalled();
        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should emit modal-close event when clicking on Close button', async () => {
        const wrapper = await createWrapper();
        const closeButton = wrapper.find('.sw-button');

        await closeButton.trigger('click');
        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('should able to change page', async () => {
        const wrapper = await createWrapper({
            steps: [1],
            limit: 1,
        });

        await flushPromises();

        const pageButtons = wrapper.findAll('.sw-pagination__list-button');
        await pageButtons.at(1).trigger('click');

        expect(pageButtons.at(1).classes()).toContain('is-active');
        expect(wrapper.vm.page).toBe(2);
    });

    it('should have multiple transactions', async () => {
        orderProp.transactions.push(); // add transaction twice

        const wrapper = await createWrapper(
            {},
            {
                ...orderProp,
                transactions: [
                    ...orderProp.transactions,
                    { ...orderProp.transactions[0], id: '4' },
                ],
            },
        );

        expect(wrapper.vm.hasMultipleTransactions).toBe(true);
    });

    it('should enumerate multiple transactions', async () => {
        // add second transaction
        const wrapper = await createWrapper(
            {},
            {
                ...orderProp,
                transactions: [
                    ...orderProp.transactions,
                    { ...orderProp.transactions[0], id: '4' },
                ],
            },
            [
                ...stateHistoryFixture,
                { ...stateHistoryFixture[1], referencedId: '4' },
            ],
        );

        const spy = jest.spyOn(wrapper.vm, 'enumerateTransaction');

        await flushPromises();

        expect(wrapper.vm.hasMultipleTransactions).toBe(true);
        expect(spy).toHaveBeenCalledTimes(5);

        const allEntityColumns = await wrapper.findAll('.sw-data-grid__cell--entity > .sw-data-grid__cell-content');
        expect(allEntityColumns.map((c) => c.text())).toEqual([
            'global.entities.order',
            'global.entities.order_delivery',
            'global.entities.order_transaction 1',
            'global.entities.order_transaction 2', // New-transaction-started entry
            'global.entities.order_transaction 2',
        ]);

        const allUserColumns = await wrapper.findAll('.sw-data-grid__cell--user > .sw-data-grid__cell-content');
        expect(allUserColumns.map((c) => c.text())).toEqual([
            'sw-order.stateHistoryModal.labelSystemUser',
            'admin',
            'admin',
            'sw-order.stateHistoryModal.labelSystemUser', // New-transaction-started entry
            'admin',
        ]);
    });

    it('should not enumerate single transaction', async () => {
        const wrapper = await createWrapper();

        const spy = jest.spyOn(wrapper.vm, 'enumerateTransaction');

        await flushPromises();

        expect(wrapper.vm.hasMultipleTransactions).toBe(false);
        expect(spy).toHaveBeenCalledTimes(3);

        const allEntityColumns = await wrapper.findAll('.sw-data-grid__cell--entity > .sw-data-grid__cell-content');
        expect(allEntityColumns.map((c) => c.text())).toEqual([
            'global.entities.order',
            'global.entities.order_delivery',
            'global.entities.order_transaction',
        ]);
    });
});
