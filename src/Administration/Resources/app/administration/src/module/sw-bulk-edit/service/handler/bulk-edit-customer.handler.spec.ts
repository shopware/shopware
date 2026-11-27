/**
 * @sw-package checkout
 */
import BulkEditCustomerHandler from './bulk-edit-customer.handler';

type BulkEditCustomerHandlerTestDouble = Omit<BulkEditCustomerHandler, 'customerGroupRegistrationService'> & {
    customerGroupRegistrationService: {
        accept: jest.Mock;
        decline: jest.Mock;
    };
};

const replies = ['accept', 'decline'] as const;

function createHandler() {
    const handler = new BulkEditCustomerHandler() as unknown as BulkEditCustomerHandlerTestDouble;

    handler.customerGroupRegistrationService = {
        accept: jest.fn().mockResolvedValue({}),
        decline: jest.fn().mockResolvedValue({}),
    };

    return handler;
}

describe('module/sw-bulk-edit/service/handler/bulk-edit-customer.handler', () => {
    beforeEach(() => {
        Shopware.Store.get('swBulkEdit').isFlowTriggered = true;
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    it.each(replies)('sends the %s reply for all selected customers', async (reply) => {
        const handler = createHandler();

        await handler.bulkEditRequestedGroup(['1', '2'], [{ value: reply }]);

        expect(handler.customerGroupRegistrationService[reply]).toHaveBeenCalledTimes(1);
        expect(handler.customerGroupRegistrationService[reply]).toHaveBeenCalledWith(
            ['1', '2'],
            {},
            { 'sw-skip-trigger-flow': false },
            { silentError: true },
        );
    });

    it('skips the flows when they are not triggered', async () => {
        Shopware.Store.get('swBulkEdit').isFlowTriggered = false;
        const handler = createHandler();

        await handler.bulkEditRequestedGroup(['1'], [{ value: 'accept' }]);

        expect(handler.customerGroupRegistrationService.accept).toHaveBeenCalledWith(
            ['1'],
            {},
            { 'sw-skip-trigger-flow': true },
            { silentError: true },
        );
    });

    it('sends no reply for a change without a value', async () => {
        const handler = createHandler();

        await handler.bulkEditRequestedGroup(['1'], [{ value: null }]);

        expect(handler.customerGroupRegistrationService.accept).not.toHaveBeenCalled();
        expect(handler.customerGroupRegistrationService.decline).not.toHaveBeenCalled();
    });

    /**
     * The customer bulk edit did not wait for replies to customer group requests.
     * @regression
     * @see https://github.com/shopware/shopware/issues/23154
     */
    describe('customer group replies', () => {
        /**
         * The bulk edit reported success before the customer group reply finished.
         * @regression
         */
        it.each(replies)('waits until the %s reply finishes', async (reply) => {
            const handler = createHandler();
            let finishReply: (value: unknown) => void = () => {};
            handler.customerGroupRegistrationService[reply].mockReturnValue(
                new Promise((resolve) => {
                    finishReply = resolve;
                }),
            );

            let finished = false;
            const result = handler.bulkEditRequestedGroup(['1'], [{ value: reply }]).then(() => {
                finished = true;
            });
            await flushPromises();

            expect(finished).toBe(false);

            finishReply({});
            await result;

            expect(finished).toBe(true);
        });

        /**
         * A failed customer group reply was not retried.
         * @regression
         */
        it.each(replies)('retries a failed %s reply', async (reply) => {
            jest.useFakeTimers();
            const handler = createHandler();
            handler.customerGroupRegistrationService[reply]
                .mockRejectedValueOnce(new Error('Network error'))
                .mockResolvedValueOnce({});

            const result = handler.bulkEditRequestedGroup(['1'], [{ value: reply }]);
            await jest.advanceTimersByTimeAsync(5000);
            await result;

            expect(handler.customerGroupRegistrationService[reply]).toHaveBeenCalledTimes(2);
        });

        /**
         * A failed customer group reply was swallowed and the bulk edit reported success.
         * @regression
         */
        it.each(replies)('fails when every %s reply fails', async (reply) => {
            jest.useFakeTimers();
            const handler = createHandler();
            const error = new Error('Network error');
            handler.customerGroupRegistrationService[reply].mockRejectedValue(error);

            await Promise.all([
                expect(handler.bulkEditRequestedGroup(['1'], [{ value: reply }])).rejects.toBe(error),
                jest.advanceTimersByTimeAsync(15000),
            ]);

            expect(handler.customerGroupRegistrationService[reply]).toHaveBeenCalledTimes(4);
        });
    });
});
