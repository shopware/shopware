import BulkEditBaseHandler from './bulk-edit-base.handler';

const { Criteria } = Shopware.Data;
const { types } = Shopware.Utils;

/**
 * @class
 * @extends BulkEditBaseHandler
 * @package system-settings
 */
class BulkEditOrderHandler extends BulkEditBaseHandler {
    constructor() {
        super();
        this.name = 'BulkEditOrderHandler';
        this.entityIds = [];
        this.orderStateMachineService = Shopware.Service('orderStateMachineService');
        this.orderRepository = Shopware.Service('repositoryFactory').create('order');
        this.entityName = 'order';
    }

    async bulkEditStatus(entityIds, payload) {
        this.entityIds = entityIds;

        let promises = [];
        const shouldTriggerFlows = Shopware.State.get('swBulkEdit').isFlowTriggered;

        const orders = await this.orderRepository.search(this.getCriteria());

        payload.forEach((change) => {
            if (!change.value) {
                return;
            }

            promises = orders.map((order) => {
                const optionsMail = {
                    documentTypes: change.documentTypes,
                    skipSentDocuments: change.skipSentDocuments,
                    sendMail: change.sendMail,
                };

                switch (change.field) {
                    case 'orderTransactions':
                        return this.orderStateMachineService.transitionOrderTransactionState(
                            order.transactions.first()?.id,
                            change.value,
                            optionsMail,
                            {},
                            {
                                'sw-skip-trigger-flow': !shouldTriggerFlows,
                            },
                        );
                    case 'orderDeliveries':
                        return this.orderStateMachineService.transitionOrderDeliveryState(
                            order.deliveries.first()?.id,
                            change.value,
                            optionsMail,
                            {},
                            {
                                'sw-skip-trigger-flow': !shouldTriggerFlows,
                            },
                        );
                    default:
                        return this.orderStateMachineService.transitionOrderState(
                            order.id,
                            change.value,
                            optionsMail,
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

        return this.syncService.sync(syncPayload, {}, {
            'single-operation': 1,
            'sw-language-id': Shopware.Context.api.languageId,
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

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default BulkEditOrderHandler;
