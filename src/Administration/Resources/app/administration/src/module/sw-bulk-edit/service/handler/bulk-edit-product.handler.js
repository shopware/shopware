import BulkEditBaseHandler from './bulk-edit-base.handler';

/**
 * @class
 * @extends BulkEditBaseHandler
 */
class BulkEditProductHandler extends BulkEditBaseHandler {
    constructor() {
        super();
        this.name = 'bulkEditProductHandler';

        this.entityName = null;
        this.entityIds = [];
    }

    async bulkEdit(entityIds, payload) {
        this.entityName = 'product';
        this.entityIds = entityIds;

        const syncPayload = await this.buildBulkSyncPayload(payload);

        return this.syncService.sync(syncPayload, {}, { 'single-operation': 1 });
    }
}

export default BulkEditProductHandler;
