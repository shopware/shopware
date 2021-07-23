import BulkEditBaseHandler from './bulk-edit-base.handler';

const types = Shopware.Utils.types;

/**
 * @class
 * @extends BulkEditBaseHandler
 */
class BulkEditProductHandler extends BulkEditBaseHandler {
    constructor() {
        super();
        this.name = 'bulkEditProductHandler';

        this.entityName = 'product';
        this.entityIds = [];
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
}

export default BulkEditProductHandler;
