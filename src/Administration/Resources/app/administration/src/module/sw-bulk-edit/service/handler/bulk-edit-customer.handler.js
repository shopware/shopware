import BulkEditBaseHandler from './bulk-edit-base.handler';

const types = Shopware.Utils.types;

/**
 * @class
 * @extends BulkEditBaseHandler
 * @package system-settings
 */
class BulkEditCustomerHandler extends BulkEditBaseHandler {
    constructor() {
        super();
        this.name = 'bulkEditCustomerHandler';
        this.entityName = 'customer';
        this.entityIds = [];
        this.customerGroupRegistrationService = Shopware.Service('customerGroupRegistrationService');
        this.customerRepository = Shopware.Service('repositoryFactory').create('customer');
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

    async bulkEditRequestedGroup(entityIds, payload) {
        const promises = [];
        const shouldTriggerFlows = Shopware.State.get('swBulkEdit').isFlowTriggered;

        payload.forEach((change) => {
            if (!change.value) {
                return;
            }

            switch (change.value) {
                case 'decline':
                    promises.push(this.customerGroupRegistrationService.decline(
                        entityIds,
                        {},
                        {
                            'sw-skip-trigger-flow': !shouldTriggerFlows,
                        },
                        {
                            silentError: true,
                        },
                    ));
                    break;
                case 'accept':
                    promises.push(this.customerGroupRegistrationService.accept(
                        entityIds,
                        {},
                        {
                            'sw-skip-trigger-flow': !shouldTriggerFlows,
                        },
                        {
                            silentError: true,
                        },
                    ));
                    break;
                default:
                    throw new Error();
            }
        });

        return Promise.all(promises);
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default BulkEditCustomerHandler;
