/**
 * @sw-package checkout
 */
import { collectStatusTransitionFailures, toFailedStatusItems } from './status-transition-failures';

const statusData = [
    { field: 'orders' },
    { field: 'orderTransactions' },
];

describe('src/module/sw-bulk-edit/page/sw-bulk-edit-order/status-transition-failures', () => {
    it('sends the status changes for all orders in one call', async () => {
        const bulkEditStatus = jest.fn().mockResolvedValueOnce([]);

        const failures = await collectStatusTransitionFailures({ bulkEditStatus }, ['1', '2'], statusData);

        expect(bulkEditStatus).toHaveBeenCalledTimes(1);
        expect(bulkEditStatus).toHaveBeenCalledWith(['1', '2'], statusData);
        expect(failures).toEqual([]);
    });

    it('returns the per-order failures the handler reports', async () => {
        const failure = { orderId: '1', orderNumber: '10001', field: 'orders', reason: 'transition', code: 'X' };
        const bulkEditStatus = jest.fn().mockRejectedValueOnce(Object.assign(new Error('failed'), { failures: [failure] }));

        const failures = await collectStatusTransitionFailures({ bulkEditStatus }, ['1', '2'], statusData);

        expect(failures).toEqual([failure]);
    });

    it('marks every order and field as failed when the error has no per-order failures', async () => {
        const error = { response: { data: { errors: [{ code: 'SYSTEM__STATE_MACHINE_TRANSITION_LOCKED' }] } } };
        const bulkEditStatus = jest.fn().mockRejectedValueOnce(error);

        const failures = await collectStatusTransitionFailures({ bulkEditStatus }, ['1', '2'], statusData);

        expect(failures).toEqual([
            {
                orderId: '1',
                orderNumber: '1',
                field: 'orders',
                reason: 'unknown',
                code: 'SYSTEM__STATE_MACHINE_TRANSITION_LOCKED',
                error,
            },
            {
                orderId: '1',
                orderNumber: '1',
                field: 'orderTransactions',
                reason: 'unknown',
                code: 'SYSTEM__STATE_MACHINE_TRANSITION_LOCKED',
                error,
            },
            {
                orderId: '2',
                orderNumber: '2',
                field: 'orders',
                reason: 'unknown',
                code: 'SYSTEM__STATE_MACHINE_TRANSITION_LOCKED',
                error,
            },
            {
                orderId: '2',
                orderNumber: '2',
                field: 'orderTransactions',
                reason: 'unknown',
                code: 'SYSTEM__STATE_MACHINE_TRANSITION_LOCKED',
                error,
            },
        ]);
    });

    it('labels known status fields and keeps the field name for others', () => {
        const items = toFailedStatusItems(
            [
                { orderId: '1', orderNumber: '10001', field: 'orderDeliveries', code: 'A' },
                { orderId: '2', orderNumber: '10002', field: 'customField', reason: 'load' },
            ],
            (key) => `translated:${key}`,
        );

        expect(items).toEqual([
            {
                orderId: '1',
                orderNumber: '10001',
                field: 'orderDeliveries',
                code: 'A',
                reason: 'transition',
                fieldLabel: 'translated:sw-bulk-edit.order.status.failedFields.orderDeliveries',
            },
            {
                orderId: '2',
                orderNumber: '10002',
                field: 'customField',
                code: undefined,
                reason: 'load',
                fieldLabel: 'customField',
            },
        ]);
    });
});
