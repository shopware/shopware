import BulkEditBaseHandler from './bulk-edit-base.handler';

const { Criteria } = Shopware.Data;
const { types } = Shopware.Utils;

/**
 * @class
 * @extends BulkEditBaseHandler
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

        try {
            const orders = await this.orderRepository.search(this.getCriteria());

            payload.forEach((change) => {
                if (!change.value) {
                    return;
                }

                promises = orders.map((order) => {
                    const optionsMail = {
                        documentIds: [
                            // TODO: NEXT-15616 - allow sending email for status changes including document attachments
                        ],
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
                                    'sw-skip-trigger-flow': !Shopware.State.get('swBulkEdit').isFlowTriggered,
                                },
                            );
                        case 'orderDeliveries':
                            return this.orderStateMachineService.transitionOrderDeliveryState(
                                order.deliveries.first()?.id,
                                change.value,
                                optionsMail,
                                {},
                                {
                                    'sw-skip-trigger-flow': !Shopware.State.get('swBulkEdit').isFlowTriggered,
                                },
                            );
                        default:
                            return this.orderStateMachineService.transitionOrderState(
                                order.id,
                                change.value,
                                optionsMail,
                                {},
                                {
                                    'sw-skip-trigger-flow': !Shopware.State.get('swBulkEdit').isFlowTriggered,
                                },
                            );
                    }
                });
            });
        } catch (e) {
            throw e;
        }

        return Promise.all(promises);
    }

    async bulkEdit(entityIds, payload) {
        this.entityIds = entityIds;

        const syncPayload = await this.buildBulkSyncPayload(payload);

        if (types.isEmpty(syncPayload)) {
            return Promise.resolve({ success: true });
        }

        return this.syncService.sync(syncPayload, {}, {
            'single-operation': 1,
            'sw-language-id': Shopware.Context.api.languageId,
        });
    }

    getCriteria() {
        const criteria = new Criteria();
        criteria.setIds(this.entityIds);
        criteria.getAssociation('deliveries');
        criteria.getAssociation('transactions');

        return criteria;
    }
}

export default BulkEditOrderHandler;
