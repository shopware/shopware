import BulkEditBaseHandler from './bulk-edit-base.handler';
import RetryHelper from '../../../../core/helper/retry.helper';

const { Criteria } = Shopware.Data;
const { types } = Shopware.Utils;

const MAX_CONCURRENT_STATUS_TRANSITIONS = 5;
const STATUS_TRANSITION_RETRY_DELAY = 500;
const RETRYABLE_STATUS_TRANSITION_ERROR_CODES = new Set([
    '1020',
    '1205',
    '1213',
    'SYSTEM__STATE_MACHINE_TRANSITION_LOCKED',
]);

class BulkEditOrderStatusError extends Error {
    constructor(failures) {
        super('One or more order status transitions failed.');

        this.name = 'BulkEditOrderStatusError';
        this.failures = failures;
    }
}

/**
 * @class
 * @extends BulkEditBaseHandler
 * @sw-package checkout
 */
class BulkEditOrderHandler extends BulkEditBaseHandler {
    constructor() {
        super();
        this.name = 'BulkEditOrderHandler';
        this.entityIds = [];
        this.orderStateMachineService = Shopware.Service('orderStateMachineService');
        this.orderRepository = Shopware.Service('repositoryFactory').create('order');
        this.entityName = 'order';
        this.maxConcurrentStatusTransitions = MAX_CONCURRENT_STATUS_TRANSITIONS;
        this.statusTransitionRetryDelay = STATUS_TRANSITION_RETRY_DELAY;
    }

    async bulkEditStatus(entityIds, payload) {
        this.entityIds = entityIds;

        const changes = payload.filter((change) => change.value);

        if (entityIds.length === 0 || changes.length === 0) {
            return [];
        }

        const shouldTriggerFlows = Shopware.Store.get('swBulkEdit').isFlowTriggered;
        const orders = await this.orderRepository.search(this.getCriteria());
        const iterator = orders[Symbol.iterator]();
        const failures = [];
        const responses = [];
        const foundOrderIds = new Set(orders.map((order) => order.id));

        entityIds.forEach((orderId) => {
            if (foundOrderIds.has(orderId)) {
                return;
            }

            changes.forEach((change) => {
                failures.push({
                    orderId,
                    orderNumber: orderId,
                    field: change.field,
                    code: '',
                });
            });
        });

        const runWorker = async () => {
            let entry = iterator.next();

            while (!entry.done) {
                const order = entry.value;

                for (const change of changes) {
                    try {
                        responses.push(await this.transitionOrderStatus(order, change, shouldTriggerFlows));
                    } catch (error) {
                        failures.push({
                            orderId: order.id,
                            orderNumber: order.orderNumber ?? order.id,
                            field: change.field,
                            code: this.getStatusTransitionErrorCode(error),
                            error,
                        });
                    }
                }

                entry = iterator.next();
            }
        };

        const workerCount = Math.min(this.maxConcurrentStatusTransitions, orders.length);
        const workers = Array.from({ length: workerCount }, () => runWorker());

        await Promise.all(workers);

        if (failures.length > 0) {
            throw new BulkEditOrderStatusError(failures);
        }

        return responses;
    }

    transitionOrderStatus(order, change, shouldTriggerFlows) {
        const transition = () => {
            const options = {
                documentTypes: change.documentTypes,
                skipSentDocuments: change.skipSentDocuments,
                sendMail: change.sendMail,
                internalComment: change.internalComment,
            };

            switch (change.field) {
                case 'orderTransactions':
                    return this.orderStateMachineService.transitionOrderTransactionState(
                        order.transactions.first()?.id,
                        change.value,
                        options,
                        {},
                        {
                            'sw-skip-trigger-flow': !shouldTriggerFlows,
                        },
                    );
                case 'orderDeliveries':
                    return this.orderStateMachineService.transitionOrderDeliveryState(
                        order.deliveries.first()?.id,
                        change.value,
                        options,
                        {},
                        {
                            'sw-skip-trigger-flow': !shouldTriggerFlows,
                        },
                    );
                default:
                    return this.orderStateMachineService.transitionOrderState(
                        order.id,
                        change.value,
                        options,
                        {},
                        {
                            'sw-skip-trigger-flow': !shouldTriggerFlows,
                        },
                    );
            }
        };

        return this.retryOrderStatusTransition(transition);
    }

    async retryOrderStatusTransition(transition) {
        try {
            return await transition();
        } catch (error) {
            if (!this.isRetryableStatusTransitionError(error)) {
                throw error;
            }

            await new Promise((resolve) => {
                setTimeout(resolve, this.statusTransitionRetryDelay);
            });

            return transition();
        }
    }

    isRetryableStatusTransitionError(error) {
        return RETRYABLE_STATUS_TRANSITION_ERROR_CODES.has(this.getStatusTransitionErrorCode(error));
    }

    getStatusTransitionErrorCode(error) {
        return String(error?.response?.data?.errors?.[0]?.code ?? '');
    }

    async bulkEdit(entityIds, payload) {
        this.entityIds = entityIds;

        const syncPayload = await this.buildBulkSyncPayload(payload);

        if (types.isEmpty(syncPayload)) {
            return Promise.resolve({ data: [] });
        }

        return RetryHelper.retry(() => {
            return this.syncService.sync(
                syncPayload,
                {},
                {
                    'single-operation': 1,
                    'sw-language-id': Shopware.Context.api.languageId,
                },
            );
        });
    }

    getCriteria() {
        const criteria = new Criteria(1, this.entityIds.length);
        criteria.setIds(this.entityIds);
        criteria.getAssociation('deliveries');
        criteria.getAssociation('transactions');

        return criteria;
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default BulkEditOrderHandler;
