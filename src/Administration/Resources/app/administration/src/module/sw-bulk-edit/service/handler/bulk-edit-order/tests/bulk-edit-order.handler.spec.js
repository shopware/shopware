/**
 * @sw-package checkout
 */
import BulkEditApiFactory from 'src/module/sw-bulk-edit/service/bulk-edit.api.factory';
import EntityCollection from 'src/core/data/entity-collection.data';

function getBulkEditOrderHandler(orders) {
    const handler = new BulkEditApiFactory().getHandler('order');

    handler.orderRepository = {
        search: jest.fn(() => Promise.resolve(orders)),
    };
    handler.orderStateMachineService = {
        transitionOrderDeliveryState: jest.fn(() => Promise.resolve()),
        transitionOrderTransactionState: jest.fn(() => Promise.resolve()),
    };

    return handler;
}

function createOrderWithTransactions(primaryOrderTransactionId, transactionStates) {
    return {
        id: 'order-id',
        primaryOrderTransactionId,
        transactions: new EntityCollection(
            '/order-transaction',
            'order_transaction',
            null,
            null,
            Object.entries(transactionStates).map(([id, technicalName]) => ({
                id,
                stateMachineState: { technicalName },
            })),
        ),
    };
}

describe('module/sw-bulk-edit/service/handler/bulk-edit-order.handler', () => {
    it('should sort transactions like the order list', () => {
        const handler = getBulkEditOrderHandler([]);
        const transactionCriteria = handler.getCriteria().getAssociation('transactions');

        expect(transactionCriteria.hasAssociation('stateMachineState')).toBe(true);
        expect(transactionCriteria.sortings).toEqual([
            expect.objectContaining({ field: 'createdAt', order: 'ASC' }),
        ]);
    });

    it('should transition the primary order transaction', async () => {
        const handler = getBulkEditOrderHandler([
            createOrderWithTransactions('primary-transaction', {
                'failed-transaction': 'failed',
                'primary-transaction': 'open',
            }),
        ]);

        await handler.bulkEditStatus(['order-id'], [{ field: 'orderTransactions', value: 'paid' }]);

        expect(handler.orderStateMachineService.transitionOrderTransactionState).toHaveBeenCalledWith(
            'primary-transaction',
            'paid',
            expect.any(Object),
            {},
            expect.any(Object),
        );
    });

    it('should transition the first transaction that is not cancelled or failed when the order has no primary order transaction', async () => {
        const handler = getBulkEditOrderHandler([
            createOrderWithTransactions(null, {
                'cancelled-transaction': 'cancelled',
                'failed-transaction': 'failed',
                'open-transaction': 'open',
                'newer-open-transaction': 'open',
            }),
        ]);

        await handler.bulkEditStatus(['order-id'], [{ field: 'orderTransactions', value: 'paid' }]);

        expect(handler.orderStateMachineService.transitionOrderTransactionState).toHaveBeenCalledWith(
            'open-transaction',
            'paid',
            expect.any(Object),
            {},
            expect.any(Object),
        );
    });

    it('should transition the last transaction when all transactions are cancelled or failed and the order has no primary order transaction', async () => {
        const handler = getBulkEditOrderHandler([
            createOrderWithTransactions(null, {
                'failed-transaction': 'failed',
                'cancelled-transaction': 'cancelled',
            }),
        ]);

        await handler.bulkEditStatus(['order-id'], [{ field: 'orderTransactions', value: 'reopen' }]);

        expect(handler.orderStateMachineService.transitionOrderTransactionState).toHaveBeenCalledWith(
            'cancelled-transaction',
            'reopen',
            expect.any(Object),
            {},
            expect.any(Object),
        );
    });
});
