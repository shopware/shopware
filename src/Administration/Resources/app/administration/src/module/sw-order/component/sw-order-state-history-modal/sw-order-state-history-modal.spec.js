import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

/**
 * @sw-package checkout
 */

function getCollection(entity, collection, total = collection.length) {
    return new EntityCollection(`/${entity}`, entity, null, { isShopwareContext: true }, collection, total, null);
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
        internalComment: 'Order delivery internal comment',
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
        internalComment: 'Order transaction internal comment',
    },
];

const orderProp = {
    id: '1',
    orderDateTime: '2022-10-10T10:01:33.815+00:00',
    transactions: getCollection('order_transaction', [
        {
            id: '2',
            stateMachineState: {
                technicalName: 'open',
                translated: {
                    name: 'Open',
                },
            },
            getEntityName: () => 'order_transaction',
        },
        {
            id: '5',
            stateMachineState: {
                technicalName: 'open',
                translated: {
                    name: 'Open',
                },
            },
            getEntityName: () => 'order_transaction',
        },
    ]),
    deliveries: getCollection('order_delivery', [
        {
            id: '3',
            stateMachineState: {
                technicalName: 'open',
                translated: {
                    name: 'Open',
                },
            },
            getEntityName: () => 'order_delivery',
        },
    ]),
    stateMachineState: {
        technicalName: 'open',
        translated: {
            name: 'Open',
        },
    },
    getEntityName: () => 'order',
};

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
                    'sw-provide': { template: `<slot/>`, inheritAttrs: false },
                    'mt-icon': true,
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
                            search: (criteria) => {
                                if (options.error) {
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

                                // Paginate like the server does, so a component that asks for a
                                // single page cannot silently receive the whole history. `total`
                                // stays the full count, as the server reports it.
                                const entries =
                                    criteria?.limit == null
                                        ? stateHistory
                                        : stateHistory.slice(
                                              (criteria.page - 1) * criteria.limit,
                                              criteria.page * criteria.limit,
                                          );

                                return Promise.resolve(getCollection('state_machine_history', entries, stateHistory.length));
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

    it('should show state history grid correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const stateHistoryRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(stateHistoryRows).toHaveLength(4);

        const firstRow = stateHistoryRows.at(0);
        expect(firstRow.find('.sw-data-grid__cell--entity').text()).toBe('global.entities.order');
        expect(firstRow.find('.sw-data-grid__cell--user').text()).toBe('sw-order.stateHistoryModal.labelSystemUser');
        expect(firstRow.find('.sw-data-grid__cell--delivery').text()).toBe('Open');
        expect(firstRow.find('.sw-data-grid__cell--transaction').text()).toBe('Open');
        expect(firstRow.find('.sw-data-grid__cell--order').text()).toBe('Open');
        expect(firstRow.find('.sw-data-grid__cell--internalComment').find('mt-icon-stub').exists()).toBe(false);

        const secondRow = stateHistoryRows.at(1);
        expect(secondRow.find('.sw-data-grid__cell--entity').text()).toBe('global.entities.order_delivery');
        expect(secondRow.find('.sw-data-grid__cell--user').text()).toBe('admin');
        expect(secondRow.find('.sw-data-grid__cell--delivery').text()).toBe('Shipped');
        expect(secondRow.find('.sw-data-grid__cell--transaction').text()).toBe('Open');
        expect(secondRow.find('.sw-data-grid__cell--order').text()).toBe('Open');
        expect(secondRow.find('.sw-data-grid__cell--internalComment').find('mt-icon-stub').exists()).toBe(true);

        const thirdRow = stateHistoryRows.at(2);
        expect(thirdRow.find('.sw-data-grid__cell--entity').text()).toBe('global.entities.order_transaction 1');
        expect(thirdRow.find('.sw-data-grid__cell--user').text()).toBe('admin');
        expect(thirdRow.find('.sw-data-grid__cell--delivery').text()).toBe('Shipped');
        expect(thirdRow.find('.sw-data-grid__cell--transaction').text()).toBe('In progress');
        expect(thirdRow.find('.sw-data-grid__cell--order').text()).toBe('Open');
        expect(thirdRow.find('.sw-data-grid__cell--internalComment').find('mt-icon-stub').exists()).toBe(true);

        const fourthRow = stateHistoryRows.at(3);
        expect(fourthRow.find('.sw-data-grid__cell--entity').text()).toBe('global.entities.order_transaction 2');
        expect(fourthRow.find('.sw-data-grid__cell--user').text()).toBe('sw-order.stateHistoryModal.labelSystemUser');
        expect(fourthRow.find('.sw-data-grid__cell--delivery').text()).toBe('Shipped');
        expect(fourthRow.find('.sw-data-grid__cell--transaction').text()).toBe('Open');
        expect(fourthRow.find('.sw-data-grid__cell--order').text()).toBe('Open');
        expect(fourthRow.find('.sw-data-grid__cell--internalComment').find('mt-icon-stub').exists()).toBe(false);
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
        const closeButton = wrapper.findByText('button', 'global.default.close');

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

    describe('pagination beyond the first page', () => {
        const state = (technicalName) => ({ technicalName, translated: { name: technicalName } });

        const transaction = (id, currentState) => ({
            id,
            createdAt: '2022-10-10T10:00:00.000+00:00',
            stateMachineState: state(currentState),
            getEntityName: () => 'order_transaction',
        });

        const transition = (entityName, referencedId, from, to, createdAt) => ({
            entityName,
            referencedId,
            fromStateMachineState: state(from),
            toStateMachineState: state(to),
            createdAt,
        });

        // Every entity's *current* state is suffixed `-now`, so a row that leaks a current state
        // instead of the state at that point in the timeline is unmistakable.
        const order = {
            id: '1',
            orderDateTime: '2022-10-10T10:00:00.000+00:00',
            stateMachineState: state('order-now'),
            transactions: getCollection('order_transaction', [
                transaction('t1', 'tx1-now'),
                transaction('t2', 'tx2-now'),
            ]),
            deliveries: getCollection('order_delivery', [
                {
                    id: 'd1',
                    createdAt: '2022-10-10T10:00:00.000+00:00',
                    stateMachineState: state('delivery-now'),
                    getEntityName: () => 'order_delivery',
                },
            ]),
            getEntityName: () => 'order',
        };

        const history = [
            transition('order', '1', 'order-open', 'order-in_progress', '2022-10-10T11:00:00.000+00:00'),
            transition('order_transaction', 't1', 'tx1-open', 'tx1-cancelled', '2022-10-10T12:00:00.000+00:00'),
            transition('order_delivery', 'd1', 'delivery-open', 'delivery-shipped', '2022-10-11T10:00:00.000+00:00'),
            transition('order_transaction', 't2', 'tx2-open', 'tx2-paid', '2022-10-11T11:00:00.000+00:00'),
        ];

        it('should carry the running state across the page boundary', async () => {
            const wrapper = await createWrapper({ limit: 3 }, order, history);
            await flushPromises();

            wrapper.vm.onPageChange({ page: 2, limit: 3 });
            await flushPromises();

            const columns = wrapper.vm.stateHistory.map((entry) => ({
                entity: entry.entity,
                order: entry.order.technicalName,
                transaction: entry.transaction.technicalName,
                delivery: entry.delivery.technicalName,
            }));

            // The order and the first transaction do not transition on this page, so their columns
            // have to keep the states they reached on page 1 — not their current states.
            expect(columns[0]).toEqual({
                entity: 'order_delivery',
                order: 'order-in_progress',
                transaction: 'tx1-cancelled',
                delivery: 'delivery-shipped',
            });
        });

        it('should build the initial state of a transaction that first appears after the page boundary', async () => {
            const wrapper = await createWrapper({ limit: 3 }, order, history);
            await flushPromises();

            wrapper.vm.onPageChange({ page: 2, limit: 3 });
            await flushPromises();

            const transactionStates = wrapper.vm.stateHistory
                .filter((entry) => entry.referencedId === 't2')
                .map((entry) => entry.transaction.technicalName);

            expect(transactionStates).toEqual([
                'tx2-open',
                'tx2-paid',
            ]);
        });

        it('should count the built rows rather than the fetched history entries', async () => {
            const wrapper = await createWrapper({ limit: 3 }, order, history);
            await flushPromises();

            // 4 transitions + the prepended start state + the initial state built for `t2`.
            expect(wrapper.vm.dataSource).toHaveLength(6);

            // @deprecated tag:v6.8.0 - `total` is kept in sync until it is removed.
            expect(wrapper.vm.total).toBe(6);
        });

        it('should keep dataSource writable so extensions can still replace the rows', async () => {
            const wrapper = await createWrapper({ limit: 3 }, order, history);
            await flushPromises();

            wrapper.vm.dataSource = [
                ...wrapper.vm.dataSource,
                {
                    ...wrapper.vm.dataSource[0],
                    referencedId: 'injected-by-an-extension',
                },
            ];
            await flushPromises();

            wrapper.vm.onPageChange({ page: 3, limit: 3 });
            await flushPromises();

            expect(wrapper.vm.stateHistory.map((entry) => entry.referencedId)).toEqual([
                'injected-by-an-extension',
            ]);
        });

        it('should prepend the start state only to the first page', async () => {
            const wrapper = await createWrapper({ limit: 3 }, order, history);
            await flushPromises();

            expect(wrapper.vm.stateHistory[0].order.technicalName).toBe('order-open');

            wrapper.vm.onPageChange({ page: 2, limit: 3 });
            await flushPromises();

            expect(wrapper.vm.stateHistory.map((entry) => entry.order.technicalName)).not.toContain('order-open');
        });

        it('should append the trailing transaction row once, not on every page', async () => {
            // `t2` has no history entry at all, so it only shows up as a trailing row.
            const historyWithoutSecondTransaction = history.filter((entry) => entry.referencedId !== 't2');

            const wrapper = await createWrapper({ limit: 2 }, order, historyWithoutSecondTransaction);
            await flushPromises();

            const trailingRows = wrapper.vm.dataSource.filter((entry) => entry.referencedId === 't2');
            expect(trailingRows).toHaveLength(1);
        });
    });

    it('should have multiple transactions', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.hasMultipleTransactions).toBe(true);
    });

    it('should not enumerate single transaction', async () => {
        const wrapper = await createWrapper(
            {},
            {
                ...orderProp,
                transactions: getCollection('order_transaction', [
                    {
                        id: '2',
                        stateMachineState: {
                            technicalName: 'open',
                            translated: {
                                name: 'Open',
                            },
                        },
                        getEntityName: () => 'order_transaction',
                    },
                ]),
            },
        );

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

    it('should add last transaction entry when there are multiple transactions and last transaction is not in history', async () => {
        const multipleTransactionOrder = {
            ...orderProp,
            transactions: getCollection('order_transaction', [
                {
                    id: '2',
                    stateMachineState: {
                        technicalName: 'open',
                        translated: {
                            name: 'Open',
                        },
                    },
                    getEntityName: () => 'order_transaction',
                },
                {
                    id: '3',
                    stateMachineState: {
                        technicalName: 'paid',
                        translated: {
                            name: 'Paid',
                        },
                    },
                    getEntityName: () => 'order_transaction',
                },
            ]),
        };

        // State history that doesn't include the last transaction (id: '3')
        const historyWithoutLastTransaction = [
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
                referencedId: '2', // Only includes first transaction
            },
        ];

        const wrapper = await createWrapper({}, multipleTransactionOrder, historyWithoutLastTransaction);
        await flushPromises();

        const transactionEntries = wrapper.vm.dataSource.filter((entry) => entry.entity === 'order_transaction');
        // Should have multiple transaction entries including the last one
        expect(transactionEntries.length).toBeGreaterThan(1);
        expect(wrapper.vm.hasMultipleTransactions).toBe(true);

        // Verify that the last transaction was added
        const lastTransactionEntry = wrapper.vm.dataSource.find(
            (entry) => entry.entity === 'order_transaction' && entry.referencedId === '3',
        );
        expect(lastTransactionEntry).toBeDefined();
    });

    it('should date the initial state of a later transaction by its creation time, not its first transition', async () => {
        const orderWithSecondTransaction = {
            ...orderProp,
            transactions: getCollection('order_transaction', [
                {
                    id: '2',
                    createdAt: '2022-10-12T09:39:00.000+00:00',
                    stateMachineState: {
                        technicalName: 'cancelled',
                        translated: { name: 'Cancelled' },
                    },
                    getEntityName: () => 'order_transaction',
                },
                {
                    id: '3',
                    createdAt: '2022-10-12T09:39:12.000+00:00',
                    stateMachineState: {
                        technicalName: 'authorized',
                        translated: { name: 'Authorized' },
                    },
                    getEntityName: () => 'order_transaction',
                },
            ]),
        };

        // The second transaction has no history entry for its initial `open` state, only for the
        // delayed transition an hour later.
        const history = [
            {
                entityName: 'order_transaction',
                fromStateMachineState: {
                    technicalName: 'open',
                    translated: { name: 'Open' },
                },
                toStateMachineState: {
                    technicalName: 'cancelled',
                    translated: { name: 'Cancelled' },
                },
                createdAt: '2022-10-12T09:39:12.000+00:00',
                referencedId: '2',
            },
            {
                entityName: 'order_transaction',
                fromStateMachineState: {
                    technicalName: 'open',
                    translated: { name: 'Open' },
                },
                toStateMachineState: {
                    technicalName: 'authorized',
                    translated: { name: 'Authorized' },
                },
                user: {
                    username: 'admin',
                },
                createdAt: '2022-10-12T10:39:00.000+00:00',
                referencedId: '3',
                internalComment: 'Authorized by the delayed flow',
            },
        ];

        const wrapper = await createWrapper({}, orderWithSecondTransaction, history);
        await flushPromises();

        const [
            initialState,
        ] = wrapper.vm.dataSource.filter((entry) => entry.referencedId === '3');

        expect(initialState.transaction.technicalName).toBe('open');
        expect(initialState.createdAt).toBe('2022-10-12T09:39:12.000+00:00');
        // The synthetic entry describes the transaction's creation, so it must not borrow the
        // transition's metadata either.
        expect(initialState.internalComment).toBeUndefined();

        // The real transition keeps its own timestamp.
        const transition = wrapper.vm.dataSource.at(-1);
        expect(transition.transaction.technicalName).toBe('authorized');
        expect(transition.createdAt).toBe('2022-10-12T10:39:00.000+00:00');
    });

    it('should fall back to the transition time when the transaction is not in the order', async () => {
        const history = [
            {
                entityName: 'order_transaction',
                fromStateMachineState: {
                    technicalName: 'open',
                    translated: { name: 'Open' },
                },
                toStateMachineState: {
                    technicalName: 'cancelled',
                    translated: { name: 'Cancelled' },
                },
                createdAt: '2022-10-12T09:39:12.000+00:00',
                referencedId: '2',
            },
            {
                entityName: 'order_transaction',
                fromStateMachineState: {
                    technicalName: 'open',
                    translated: { name: 'Open' },
                },
                toStateMachineState: {
                    technicalName: 'authorized',
                    translated: { name: 'Authorized' },
                },
                createdAt: '2022-10-12T10:39:00.000+00:00',
                referencedId: 'unknown-transaction',
            },
        ];

        const wrapper = await createWrapper({}, orderProp, history);
        await flushPromises();

        const [
            initialState,
        ] = wrapper.vm.dataSource.filter((entry) => entry.referencedId === 'unknown-transaction');

        expect(initialState.transaction.technicalName).toBe('open');
        expect(initialState.createdAt).toBe('2022-10-12T10:39:00.000+00:00');
    });

    it('should display username or fallback to email in user column', async () => {
        const stateHistoryWithEmailFallback = [
            {
                entityName: 'order_delivery',
                fromStateMachineState: {
                    technicalName: 'open',
                    translated: { name: 'Open' },
                },
                toStateMachineState: {
                    technicalName: 'shipped',
                    translated: { name: 'Shipped' },
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
                    translated: { name: 'Open' },
                },
                toStateMachineState: {
                    technicalName: 'in_progress',
                    translated: { name: 'In progress' },
                },
                user: {
                    email: 'user@example.com',
                },
                createdAt: '2022-10-12T10:01:33.815+00:00',
                referencedId: '2',
            },
        ];

        const wrapper = await createWrapper({}, orderProp, stateHistoryWithEmailFallback);
        await flushPromises();

        const stateHistoryRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');

        // First row should show username
        const firstRow = stateHistoryRows.at(0);
        expect(firstRow.find('.sw-data-grid__cell--user').text()).toBe('sw-order.stateHistoryModal.labelSystemUser');

        // Second row should show username
        const secondRow = stateHistoryRows.at(1);
        expect(secondRow.find('.sw-data-grid__cell--user').text()).toBe('admin');

        // Third row should show email (fallback)
        const thirdRow = stateHistoryRows.at(2);
        expect(thirdRow.find('.sw-data-grid__cell--user').text()).toBe('user@example.com');
    });

    it('should display the customer label for state changes coming from the storefront', async () => {
        const stateHistoryFromStorefront = [
            {
                entityName: 'order',
                fromStateMachineState: {
                    technicalName: 'open',
                    translated: { name: 'Open' },
                },
                toStateMachineState: {
                    technicalName: 'cancelled',
                    translated: { name: 'Cancelled' },
                },
                sourceType: 'sales-channel',
                createdAt: '2022-10-12T10:01:28.535+00:00',
            },
        ];

        const wrapper = await createWrapper({}, orderProp, stateHistoryFromStorefront);
        await flushPromises();

        const stateHistoryRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');

        // The prepended initial state has no source
        expect(stateHistoryRows.at(0).find('.sw-data-grid__cell--user').text()).toBe(
            'sw-order.stateHistoryModal.labelSystemUser',
        );
        expect(stateHistoryRows.at(1).find('.sw-data-grid__cell--user').text()).toBe(
            'sw-order.stateHistoryModal.labelCustomer',
        );
    });
});
