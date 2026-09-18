/**
 * @sw-package checkout
 */
/* eslint-disable @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access */
import BulkEditOrderHandler from './bulk-edit-order.handler';

type BulkEditOrderHandlerTestDouble = Omit<BulkEditOrderHandler, 'orderRepository' | 'orderStateMachineService'> & {
    orderRepository: {
        search: jest.Mock;
    };
    orderStateMachineService: {
        transitionOrderState: jest.Mock;
        transitionOrderTransactionState: jest.Mock;
        transitionOrderDeliveryState: jest.Mock;
    };
};

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
    const handler = new BulkEditOrderHandler() as unknown as BulkEditOrderHandlerTestDouble;

    handler.orderRepository = {
        search: jest
            .fn()
            .mockImplementation((criteria: { ids: string[] }) =>
                Promise.resolve(orders.filter((order) => criteria.ids.includes(order.id))),
            ),
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

    it('bounds ID searches to 100 orders without skipping requested orders', async () => {
        const orders = Array.from({ length: 205 }, (_, index) => createOrder(String(index)));
        const handler = createHandler(orders);

        await handler.bulkEditStatus(
            orders.map((order) => order.id),
            [
                { field: 'orders', value: 'cancel' },
            ],
        );

        const requestedIds = handler.orderRepository.search.mock.calls.map(([criteria]) => criteria.ids as string[]);

        expect(requestedIds.map((ids) => ids.length)).toEqual([
            100,
            100,
            5,
        ]);
        expect(requestedIds.flat()).toEqual(orders.map((order) => order.id));
        expect(handler.orderStateMachineService.transitionOrderState).toHaveBeenCalledTimes(205);
    });

    it('runs at most five order transitions concurrently', async () => {
        const orders = Array.from({ length: 105 }, (_, index) => createOrder(String(index)));
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
        expect(handler.orderStateMachineService.transitionOrderState).toHaveBeenCalledTimes(105);
    });

    it('returns responses in repository order and then status-field order despite completion order', async () => {
        const handler = createHandler([
            createOrder('2'),
            createOrder('1'),
        ]);
        let finishFirst: (value: string) => void = () => {};

        handler.orderStateMachineService.transitionOrderTransactionState.mockImplementation((id: string) => {
            if (id === 'transaction-2') {
                return new Promise<string>((resolve) => {
                    finishFirst = resolve;
                });
            }

            return Promise.resolve(id);
        });
        handler.orderStateMachineService.transitionOrderState.mockImplementation((id: string) => Promise.resolve(id));

        const result = handler.bulkEditStatus(
            [
                '1',
                '2',
            ],
            [
                { field: 'orderTransactions', value: 'paid' },
                { field: 'orders', value: 'complete' },
            ],
        );

        await flushPromises();
        expect(handler.orderStateMachineService.transitionOrderState).toHaveBeenCalledWith(
            '1',
            'complete',
            expect.any(Object),
            {},
            expect.any(Object),
        );
        finishFirst('transaction-2');

        await expect(result).resolves.toEqual([
            'transaction-2',
            '2',
            'transaction-1',
            '1',
        ]);
    });

    it('omits failed response slots while preserving successful undefined and falsy responses', async () => {
        const responses = [
            new Error('First transition failed'),
            undefined,
            false,
            new Error('Middle transition failed'),
            null,
            0,
            '',
            new Error('Last transition failed'),
        ];
        const orders = responses.map((response, index) => createOrder(String(index)));
        const handler = createHandler(orders);
        const failures: unknown[] = [];

        handler.orderStateMachineService.transitionOrderState.mockImplementation((orderId: string) => {
            const response = responses[Number(orderId)];

            return response instanceof Error ? Promise.reject(response) : Promise.resolve(response);
        });

        const result = await handler.transitionOrderStatuses(orders, [{ field: 'orders', value: 'cancel' }], true, failures);

        expect(result).toStrictEqual([
            undefined,
            false,
            null,
            0,
            '',
        ]);
        expect(failures).toEqual([
            expect.objectContaining({ orderId: '0', reason: 'transition' }),
            expect.objectContaining({ orderId: '3', reason: 'transition' }),
            expect.objectContaining({ orderId: '7', reason: 'transition' }),
        ]);
    });

    it('waits for an earlier status field and reports its failure before completing', async () => {
        const handler = createHandler();
        let failFirst: (error: Error) => void = () => {};
        let settled = false;

        handler.orderStateMachineService.transitionOrderTransactionState.mockImplementation(
            () =>
                new Promise<void>((resolve, reject) => {
                    failFirst = reject;
                }),
        );

        const result = handler.bulkEditStatus(
            ['1'],
            [
                { field: 'orderTransactions', value: 'paid' },
                { field: 'orders', value: 'complete' },
            ],
        );
        void result.then(
            () => {
                settled = true;
            },
            () => {
                settled = true;
            },
        );

        await flushPromises();
        expect(settled).toBe(false);
        expect(handler.orderStateMachineService.transitionOrderState).not.toHaveBeenCalled();

        failFirst(new Error('Payment transition failed'));
        await expect(result).rejects.toMatchObject({
            failures: [expect.objectContaining({ field: 'orderTransactions', reason: 'transition' })],
        });
        expect(handler.orderStateMachineService.transitionOrderState).toHaveBeenCalledTimes(1);
    });

    it('reports failed loads separately and still processes subsequent ID batches', async () => {
        const orders = Array.from({ length: 101 }, (_, index) => createOrder(String(index)));
        const handler = createHandler(orders);
        const error = createApiError('500');
        handler.orderRepository.search.mockRejectedValueOnce(error);

        const result = handler.bulkEditStatus(
            orders.map((order) => order.id),
            [{ field: 'orders', value: 'cancel' }],
        );

        await expect(result).rejects.toMatchObject({
            failures: expect.arrayContaining([
                expect.objectContaining({ orderId: '0', reason: 'load', code: '500', error }),
                expect.objectContaining({ orderId: '99', reason: 'load' }),
            ]),
        });
        await expect(result).rejects.toHaveProperty('failures.length', 100);
        expect(handler.orderStateMachineService.transitionOrderState).toHaveBeenCalledTimes(1);
        expect(handler.orderStateMachineService.transitionOrderState).toHaveBeenCalledWith(
            '100',
            'cancel',
            expect.any(Object),
            {},
            expect.any(Object),
        );
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

    it('reports a lock failure without repeating the status transition', async () => {
        const handler = createHandler();
        const transition = handler.orderStateMachineService.transitionOrderState;
        const error = createApiError('SYSTEM__STATE_MACHINE_TRANSITION_LOCKED');

        transition.mockRejectedValueOnce(error).mockResolvedValueOnce({});

        await expect(handler.bulkEditStatus(['1'], [{ field: 'orders', value: 'cancel' }])).rejects.toMatchObject({
            failures: [
                expect.objectContaining({
                    orderId: '1',
                    field: 'orders',
                    code: 'SYSTEM__STATE_MACHINE_TRANSITION_LOCKED',
                    error,
                }),
            ],
        });

        expect(transition).toHaveBeenCalledTimes(1);
    });

    it.each([
        '1020',
        '1205',
        '1213',
        'SYSTEM__STATE_MACHINE_TRANSITION_LOCKED',
    ])('reports post-commit listener error %s without replaying the transition', async (code) => {
        const handler = createHandler();
        const transition = handler.orderStateMachineService.transitionOrderState;
        const error = createApiError(code);
        let state = 'open';

        transition.mockImplementation(() => {
            if (state === 'cancelled') {
                // Repeating the transition succeeds without rerunning the failed listener.
                return Promise.resolve({});
            }

            // A listener can fail after this commit, including when it starts another locked transition.
            state = 'cancelled';

            return Promise.reject(error);
        });

        await expect(handler.bulkEditStatus(['1'], [{ field: 'orders', value: 'cancel' }])).rejects.toMatchObject({
            failures: [
                expect.objectContaining({
                    orderId: '1',
                    orderNumber: 'order-1',
                    field: 'orders',
                    code,
                    error,
                }),
            ],
        });

        expect(state).toBe('cancelled');
        expect(transition).toHaveBeenCalledTimes(1);
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
                    reason: 'not-found',
                },
                expect.objectContaining({
                    orderId: '1',
                    orderNumber: 'order-1',
                    field: 'orders',
                    code: '1020',
                }),
            ],
        });

        expect(transition).toHaveBeenCalledTimes(2);
        expect(transition).toHaveBeenCalledWith('2', 'cancel', expect.any(Object), {}, { 'sw-skip-trigger-flow': false });
    });
});
