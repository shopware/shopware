import BulkEditApiFactory from '../service/bulk-edit.api.factory';

/**
 * @sw-package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Service().register('bulkEditApiFactory', () => {
    return new BulkEditApiFactory();
});
