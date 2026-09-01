/**
 * @sw-package checkout
 */
/* eslint-disable @typescript-eslint/no-unsafe-assignment,
    @typescript-eslint/no-unsafe-call,
    @typescript-eslint/no-unsafe-member-access,
    @typescript-eslint/unbound-method
*/
import BulkEditOrderHandler from './bulk-edit-order.handler';

function createOrder(id: string) {
    return {
        id,
        orderNumber: `order-${id}`,
        transactions: {
            first: () => ({ id: `transaction-${id}` }),
        },
        deliveries: {
            first: () => ({ id: `delivery-${id}` }),
        },
    };
}

function createApiError(code: string) {
    const error = new Error(`API error ${code}`) as Error & {
        response: { data: { errors: Array<{ code: string }> } };
    };

    error.response = {
        data: {
            errors: [
                { code },
            ],
        },
    };

    return error;
}

function createHandler(orders = [createOrder('1')]) {
    const handler = new BulkEditOrderHandler();

    handler.statusTransitionRetryDelay = 0;
    handler.orderRepository = {
        search: jest.fn().mockResolvedValue(orders),
    };
    handler.orderStateMachineService = {
        transitionOrderState: jest.fn().mockResolvedValue({}),
        transitionOrderTransactionState: jest.fn().mockResolvedValue({}),
        transitionOrderDeliveryState: jest.fn().mockResolvedValue({}),
    };

    return handler;
}

describe('module/sw-bulk-edit/service/handler/bulk-edit-order.handler', () => {
    beforeEach(() => {
        Shopware.Store.get('swBulkEdit').isFlowTriggered = true;
    });

    it('loads and transitions every requested order', async () => {
        const orders = Array.from({ length: 30 }, (_, index) => createOrder(String(index)));
        const handler = createHandler(orders);

        await handler.bulkEditStatus(
            orders.map((order) => order.id),
            [
                { field: 'orders', value: 'cancel' },
            ],
        );

        const criteria = handler.orderRepository.search.mock.calls[0][0];

        expect(criteria.limit).toBe(30);
        expect(handler.orderStateMachineService.transitionOrderState).toHaveBeenCalledTimes(30);
    });

    it('runs at most five order transitions concurrently', async () => {
        const orders = Array.from({ length: 20 }, (_, index) => createOrder(String(index)));
        const handler = createHandler(orders);
        let activeTransitions = 0;
        let maxActiveTransitions = 0;

        handler.orderStateMachineService.transitionOrderState.mockImplementation(async () => {
            activeTransitions += 1;
            maxActiveTransitions = Math.max(maxActiveTransitions, activeTransitions);

            await new Promise((resolve) => {
                setTimeout(resolve, 0);
            });

            activeTransitions -= 1;
        });

        await handler.bulkEditStatus(
            orders.map((order) => order.id),
            [
                { field: 'orders', value: 'cancel' },
            ],
        );

        expect(maxActiveTransitions).toBe(5);
    });

    it('processes status fields sequentially for each order', async () => {
        const handler = createHandler();
        const calls: string[] = [];

        handler.orderStateMachineService.transitionOrderTransactionState.mockImplementation(async () => {
            calls.push('orderTransactions');
            await Promise.resolve();
        });
        handler.orderStateMachineService.transitionOrderDeliveryState.mockImplementation(async () => {
            calls.push('orderDeliveries');
            await Promise.resolve();
        });
        handler.orderStateMachineService.transitionOrderState.mockImplementation(async () => {
            calls.push('orders');
            await Promise.resolve();
        });

        await handler.bulkEditStatus(
            ['1'],
            [
                { field: 'orderTransactions', value: 'paid' },
                { field: 'orderDeliveries', value: 'ship' },
                { field: 'orders', value: 'complete' },
            ],
        );

        expect(calls).toEqual([
            'orderTransactions',
            'orderDeliveries',
            'orders',
        ]);
    });

    it.each([
        '1020',
        '1205',
        '1213',
        'SYSTEM__STATE_MACHINE_TRANSITION_LOCKED',
    ])('retries status transition error %s once', async (code) => {
        const handler = createHandler();
        const transition = handler.orderStateMachineService.transitionOrderState;

        transition.mockRejectedValueOnce(createApiError(code)).mockResolvedValueOnce({});

        await handler.bulkEditStatus(
            ['1'],
            [
                { field: 'orders', value: 'cancel' },
            ],
        );

        expect(transition).toHaveBeenCalledTimes(2);
    });

    it.each([
        '400',
        '500',
        'SYSTEM__ILLEGAL_STATE_TRANSITION',
    ])('does not retry status transition error %s', async (code) => {
        const handler = createHandler();
        const transition = handler.orderStateMachineService.transitionOrderState;

        transition.mockRejectedValue(createApiError(code));

        await expect(
            handler.bulkEditStatus(
                ['1'],
                [
                    { field: 'orders', value: 'cancel' },
                ],
            ),
        ).rejects.toMatchObject({
            failures: [
                expect.objectContaining({
                    orderId: '1',
                    orderNumber: 'order-1',
                    field: 'orders',
                    code,
                }),
            ],
        });

        expect(transition).toHaveBeenCalledTimes(1);
    });

    it('does not retry an ambiguous network error', async () => {
        const handler = createHandler();
        const transition = handler.orderStateMachineService.transitionOrderState;

        transition.mockRejectedValue(new Error('Network error'));

        await expect(
            handler.bulkEditStatus(
                ['1'],
                [
                    { field: 'orders', value: 'cancel' },
                ],
            ),
        ).rejects.toMatchObject({
            failures: [
                expect.objectContaining({
                    orderId: '1',
                    field: 'orders',
                    code: '',
                }),
            ],
        });

        expect(transition).toHaveBeenCalledTimes(1);
    });

    it('continues processing and reports failed and missing orders precisely', async () => {
        const orders = [
            createOrder('1'),
            createOrder('2'),
        ];
        const handler = createHandler(orders);
        const transition = handler.orderStateMachineService.transitionOrderState;

        transition.mockImplementation((orderId: string) => {
            if (orderId === '1') {
                return Promise.reject(createApiError('1020'));
            }

            return Promise.resolve({});
        });

        await expect(
            handler.bulkEditStatus(
                [
                    '1',
                    '2',
                    '3',
                ],
                [
                    { field: 'orders', value: 'cancel' },
                ],
            ),
        ).rejects.toMatchObject({
            failures: [
                {
                    orderId: '3',
                    orderNumber: '3',
                    field: 'orders',
                    code: '',
                },
                expect.objectContaining({
                    orderId: '1',
                    orderNumber: 'order-1',
                    field: 'orders',
                    code: '1020',
                }),
            ],
        });

        expect(transition).toHaveBeenCalledTimes(3);
        expect(transition).toHaveBeenCalledWith('2', 'cancel', expect.any(Object), {}, { 'sw-skip-trigger-flow': false });
    });
});
