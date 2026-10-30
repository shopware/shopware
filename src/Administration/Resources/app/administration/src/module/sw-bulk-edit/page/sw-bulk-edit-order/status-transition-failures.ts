/**
 * @sw-package checkout
 */

type StatusChange = {
    field: string;
};

type StatusTransitionFailure = {
    orderId: string;
    orderNumber: string;
    field: string;
    code?: string;
    reason?: string;
    error?: unknown;
};

type FailedStatusItem = {
    orderId: string;
    orderNumber: string;
    field: string;
    code?: string;
    reason: string;
    fieldLabel: string;
};

type BulkEditStatusHandler = {
    bulkEditStatus(orderIds: string[], changes: StatusChange[]): Promise<unknown>;
};

type ApiError = {
    response?: {
        data?: {
            errors?: { code?: unknown }[];
        };
    };
};

const FIELD_LABELS: Record<string, string> = {
    orderTransactions: 'sw-bulk-edit.order.status.failedFields.orderTransactions',
    orderDeliveries: 'sw-bulk-edit.order.status.failedFields.orderDeliveries',
    orders: 'sw-bulk-edit.order.status.failedFields.orders',
};

function hasFailures(error: unknown): error is { failures: StatusTransitionFailure[] } {
    return typeof error === 'object' && error !== null && Array.isArray((error as { failures?: unknown }).failures);
}

function errorCode(error: unknown): string {
    const code = (error as ApiError | undefined)?.response?.data?.errors?.[0]?.code;

    return typeof code === 'string' || typeof code === 'number' ? String(code) : '';
}

/**
 * Runs the status changes chunk by chunk and collects every failure, so a failed chunk doesn't stop the others.
 * An error without per-order failures marks every order and field of its chunk as failed for an unknown reason.
 *
 * @private
 */
export async function collectStatusTransitionFailures(
    handler: BulkEditStatusHandler,
    payloadChunks: string[][],
    statusData: StatusChange[],
): Promise<StatusTransitionFailure[]> {
    const failures: StatusTransitionFailure[] = [];

    for (const payload of payloadChunks) {
        try {
            // Chunks run one after the other to keep the number of concurrent transitions bounded
            await handler.bulkEditStatus(payload, statusData);
        } catch (error) {
            if (hasFailures(error)) {
                failures.push(...error.failures);

                continue;
            }

            payload.forEach((orderId) => {
                statusData.forEach((change) => {
                    failures.push({
                        orderId,
                        orderNumber: orderId,
                        field: change.field,
                        reason: 'unknown',
                        code: errorCode(error),
                        error,
                    });
                });
            });
        }
    }

    return failures;
}

/**
 * Maps the failures to the items the error step lists, with a translated label per status field.
 *
 * @private
 */
export function toFailedStatusItems(
    failures: StatusTransitionFailure[],
    translate: (key: string) => string,
): FailedStatusItem[] {
    return failures.map((failure) => ({
        orderId: failure.orderId,
        orderNumber: failure.orderNumber,
        field: failure.field,
        code: failure.code,
        reason: failure.reason ?? 'transition',
        fieldLabel: FIELD_LABELS[failure.field] ? translate(FIELD_LABELS[failure.field]) : failure.field,
    }));
}
