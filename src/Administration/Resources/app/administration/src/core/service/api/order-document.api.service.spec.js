/**
 * @package checkout
 * @group disabledCompat
 */
import OrderDocumentApiService from 'src/core/service/api/order-document.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createOrderDocumentApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const orderDocumentApiService = new OrderDocumentApiService(client, loginService);

    clientMock.onAny().reply(
        200,
        {
            data: null,
        },
    );

    return { orderDocumentApiService, clientMock };
}

describe('orderDocumentApiService', () => {
    it('is registered correctly', async () => {
        const { orderDocumentApiService } = createOrderDocumentApiService();

        expect(orderDocumentApiService).toBeInstanceOf(OrderDocumentApiService);
    });

    it('has the correct name', async () => {
        const { orderDocumentApiService } = createOrderDocumentApiService();

        expect(orderDocumentApiService.name).toBe('orderDocumentApiService');
    });

    describe('generate', () => {
        it('is defined', async () => {
            const { orderDocumentApiService } = createOrderDocumentApiService();

            expect(orderDocumentApiService.generate).toBeDefined();
        });

        it('calls the correct endpoint', async () => {
            const { orderDocumentApiService, clientMock } = createOrderDocumentApiService();

            const documentType = 'invoice';
            const payload = {};
            const additionalParams = {};

            orderDocumentApiService.generate(documentType, payload, additionalParams);

            expect(clientMock.history.post[0].url).toBe(`/_action/order/document/${documentType}/create`);
        });
    });


    describe('download', () => {
        it('is defined', async () => {
            const { orderDocumentApiService } = createOrderDocumentApiService();

            expect(orderDocumentApiService.download).toBeDefined();
        });

        it('calls the correct endpoint', async () => {
            const { orderDocumentApiService, clientMock } = createOrderDocumentApiService();

            const documentIds = [1, 2, 3];
            const additionalParams = {};

            orderDocumentApiService.download(documentIds, additionalParams);

            expect(clientMock.history.post[0].url).toBe('/_action/order/document/download');
        });
    });
});
