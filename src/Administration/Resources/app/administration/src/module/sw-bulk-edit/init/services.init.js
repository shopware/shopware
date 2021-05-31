import BulkEditApiFactory from '../service/bulk-edit.api.factory';

Shopware.Service().register('bulkEditApiFactory', () => {
    return new BulkEditApiFactory();
});
