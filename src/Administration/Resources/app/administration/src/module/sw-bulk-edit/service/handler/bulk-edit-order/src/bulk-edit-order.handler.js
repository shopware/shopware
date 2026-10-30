import {
    BulkEditBaseHandler,
    Criteria,
    RetryHelper,
    getApiContext,
    getOrderStateMachineService,
    getPrimaryDelivery,
    getRepositoryFactory,
    types,
    useBulkEditStore,
} from '../dependencies';

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
        this.orderStateMachineService = getOrderStateMachineService();
        this.orderRepository = getRepositoryFactory().create('order');
        this.entityName = 'order';
    }

    async bulkEditStatus(entityIds, payload) {
        this.entityIds = entityIds;

        let promises = [];
        const shouldTriggerFlows = useBulkEditStore().isFlowTriggered;

        const orders = await this.orderRepository.search(this.getCriteria());

        payload.forEach((change) => {
            if (!change.value) {
                return;
            }

            promises = orders.map((order) => {
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
                            getPrimaryDelivery(order)?.id,
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
            });
        });

        return Promise.all(promises);
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
                    'sw-language-id': getApiContext().languageId,
                },
            );
        });
    }

    getCriteria() {
        const criteria = new Criteria(1, 25);
        criteria.setIds(this.entityIds);
        criteria.getAssociation('deliveries');
        criteria.getAssociation('transactions');

        return criteria;
    }
}

/** @public */
export default BulkEditOrderHandler;
