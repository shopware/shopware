import BulkEditApiFactory from 'src/module/sw-bulk-edit/service/bulk-edit.api.factory';
import BulkEditBaseHandler from 'src/module/sw-bulk-edit/service/handler/bulk-edit-product.handler';

describe('module/sw-bulk-edit/service/bulk-edit.api.factory', () => {
    it('is registered correctly', () => {
        expect(new BulkEditApiFactory()).toBeInstanceOf(BulkEditApiFactory);
    });

    it('should find correct product handler', () => {
        const factory = new BulkEditApiFactory();

        const handler = factory.getHandler('product');

        expect(handler).toBeInstanceOf(BulkEditBaseHandler);
        expect(handler.name).toBe('bulkEditProductHandler');
    });

    it('should throw error when no handler found', async () => {
        const factory = new BulkEditApiFactory();

        expect(() => factory.getHandler('custom-module')).toThrow(Error('Bulk Edit Handler not found for custom-module module'));
    });
});
