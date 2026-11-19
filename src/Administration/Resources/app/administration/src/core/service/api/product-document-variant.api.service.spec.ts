/**
 * @sw-package inventory
 */
import ProductDocumentVariantApiService from 'src/core/service/api/product-document-variant.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createService() {
    const context = Shopware.Context.api;
    const client = createHTTPClient(context);
    const clientMock = new MockAdapter(client);
    const service = new ProductDocumentVariantApiService(client, createLoginService(client, context));

    clientMock.onPost().reply(200, { variantIds: ['variant-a'] });

    return { service, clientMock };
}

describe('src/core/service/api/product-document-variant.api.service', () => {
    it('posts the operations to the variant action of the product', async () => {
        const { service, clientMock } = createService();
        const operations = [{ mediaId: 'media-id', title: 'Manual', changes: { title: 'Guide' } }];

        const variantIds = await service.sync('product-id', operations, true, 'language-id' as EntityKey<'language'>);

        const request = clientMock.history.post[0];
        expect(request.url).toBe('_action/product/product-id/document-variants');
        expect(JSON.parse(request.data as string)).toEqual({ operations, dryRun: true });
        expect(request.headers?.['sw-language-id']).toBe('language-id');
        expect(variantIds).toEqual(['variant-a']);
    });
});
