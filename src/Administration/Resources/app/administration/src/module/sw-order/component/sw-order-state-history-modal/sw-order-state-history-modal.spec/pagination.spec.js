import { createWrapper, getCollection } from './fixtures';

/**
 * @sw-package checkout
 */

describe('src/module/sw-order/component/sw-order-state-history-modal: pagination', () => {
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

    describe('beyond the first page', () => {
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
});
