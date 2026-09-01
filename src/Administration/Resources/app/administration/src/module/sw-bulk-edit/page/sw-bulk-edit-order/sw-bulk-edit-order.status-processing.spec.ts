/**
 * @sw-package checkout
 */
/* eslint-disable @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-unsafe-return */
import component from './index';

function deferredPromise() {
    let resolvePromise: () => void = () => {};
    const promise = new Promise<void>((resolve) => {
        resolvePromise = resolve;
    });

    return {
        promise,
        resolve: resolvePromise,
    };
}

function createViewModel({
    selectedIds = ['1'],
    statusData = [{ field: 'orders', value: 'cancel' }],
    syncData = [],
    bulkEditStatus = jest.fn().mockResolvedValue([]),
    bulkEdit = jest.fn().mockResolvedValue({}),
    getLatestOrderStatus = jest.fn().mockResolvedValue(undefined),
} = {}) {
    const viewModel = {
        isLoading: false,
        processStatus: '',
        statusTransitionFailures: [],
        selectedIds,
        itemsPerRequest: 100,
        onProcessData: jest.fn(() => ({ statusData, syncData })),
        bulkEditApiFactory: {
            getHandler: jest.fn(() => ({
                bulkEditStatus,
                bulkEdit,
            })),
        },
        getLatestOrderStatus,
        $t: (key: string) => key,
    };

    viewModel.processStatusChunks = (...args) => component.methods.processStatusChunks.call(viewModel, ...args);
    viewModel.getStatusTransitionFieldLabel = (field: string) =>
        component.methods.getStatusTransitionFieldLabel.call(viewModel, field);

    return viewModel;
}

describe('src/module/sw-bulk-edit/page/sw-bulk-edit-order status processing', () => {
    it('processes status chunks sequentially before starting sync requests', async () => {
        const firstStatus = deferredPromise();
        const secondStatus = deferredPromise();
        const latestStatus = deferredPromise();
        const bulkEditStatus = jest.fn().mockReturnValueOnce(firstStatus.promise).mockReturnValueOnce(secondStatus.promise);
        const bulkEdit = jest.fn().mockResolvedValue({});
        const viewModel = createViewModel({
            selectedIds: Array.from({ length: 150 }, (_, index) => String(index)),
            syncData: [{ field: 'customFields', value: {} }],
            bulkEditStatus,
            bulkEdit,
            getLatestOrderStatus: jest.fn(() => latestStatus.promise),
        });

        const savePromise = component.methods.onSave.call(viewModel);

        await flushPromises();

        expect(bulkEditStatus).toHaveBeenCalledTimes(1);
        expect(bulkEdit).not.toHaveBeenCalled();

        firstStatus.resolve();
        await flushPromises();

        expect(bulkEditStatus).toHaveBeenCalledTimes(2);
        expect(bulkEdit).not.toHaveBeenCalled();

        secondStatus.resolve();
        await flushPromises();

        expect(bulkEdit).toHaveBeenCalledTimes(2);
        expect(viewModel.processStatus).toBe('success');
        expect(viewModel.isLoading).toBe(true);

        latestStatus.resolve();
        await savePromise;

        expect(viewModel.isLoading).toBe(false);
    });

    it('aggregates exact failures from every chunk without retaining raw errors', async () => {
        const firstError = new Error('first failure');
        const secondError = new Error('second failure');
        const bulkEditStatus = jest
            .fn()
            .mockRejectedValueOnce({
                failures: [
                    {
                        orderId: '1',
                        orderNumber: '10001',
                        field: 'orders',
                        code: '1020',
                        error: firstError,
                    },
                ],
            })
            .mockRejectedValueOnce({
                failures: [
                    {
                        orderId: '101',
                        orderNumber: '10101',
                        field: 'orderTransactions',
                        code: '1213',
                        error: secondError,
                    },
                ],
            });
        const viewModel = createViewModel({
            selectedIds: Array.from({ length: 150 }, (_, index) => String(index)),
            bulkEditStatus,
        });

        await component.methods.onSave.call(viewModel);

        expect(viewModel.statusTransitionFailures).toEqual([
            {
                orderId: '1',
                orderNumber: '10001',
                field: 'orders',
                code: '1020',
                fieldLabel: 'sw-bulk-edit.order.status.failedFields.orders',
            },
            {
                orderId: '101',
                orderNumber: '10101',
                field: 'orderTransactions',
                code: '1213',
                fieldLabel: 'sw-bulk-edit.order.status.failedFields.orderTransactions',
            },
        ]);
        expect(viewModel.processStatus).toBe('fail');
        expect(bulkEditStatus).toHaveBeenCalledTimes(2);
    });

    it('reports every selected order in a chunk when the handler returns an unstructured error', async () => {
        const bulkEditStatus = jest.fn().mockRejectedValue({
            response: {
                data: {
                    errors: [
                        { code: '500' },
                    ],
                },
            },
        });
        const viewModel = createViewModel({
            selectedIds: [
                '1',
                '2',
            ],
            bulkEditStatus,
        });

        await component.methods.onSave.call(viewModel);

        expect(viewModel.statusTransitionFailures).toEqual([
            expect.objectContaining({
                orderId: '1',
                orderNumber: '1',
                field: 'orders',
                code: '500',
            }),
            expect.objectContaining({
                orderId: '2',
                orderNumber: '2',
                field: 'orders',
                code: '500',
            }),
        ]);
        expect(viewModel.processStatus).toBe('fail');
    });
});
