import { createWrapper, getCollection, orderProp } from './fixtures';

/**
 * @sw-package checkout
 */

describe('src/module/sw-order/component/sw-order-state-history-modal: transaction rows', () => {
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
});
