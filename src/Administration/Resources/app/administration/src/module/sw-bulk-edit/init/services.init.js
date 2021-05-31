import BulkEditApiService from '../service/bulk-edit.api.service';

Shopware.Service().register('bulkEditService', () => {
    return new BulkEditApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});
