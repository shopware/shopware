import OrderDocumentApiService from 'src/core/service/api/order-document.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createOrderDocumentApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const orderDocumentApiService = new OrderDocumentApiService(client, loginService);

    return { orderDocumentApiService, clientMock };
}

describe('orderDocumentApiService', () => {
    it('is registered correctly', () => {
        const { orderDocumentApiService } = createOrderDocumentApiService();

        expect(orderDocumentApiService).toBeInstanceOf(OrderDocumentApiService);
    });

    it('has the correct name', () => {
        const { orderDocumentApiService } = createOrderDocumentApiService();

        expect(orderDocumentApiService.name).toBe('orderDocumentApiService');
    });

    /**
     * @deprecated tag:v6.5.0 - Will be removed
     */
    describe('create', () => {
        it('is defined', () => {
            const { orderDocumentApiService } = createOrderDocumentApiService();

            expect(orderDocumentApiService.create).toBeDefined();
        });

        it('calls the correct endpoint', () => {
            const { orderDocumentApiService, clientMock } = createOrderDocumentApiService();

            const payload = {};
            const additionalParams = {};

            orderDocumentApiService.create(payload, additionalParams);

            expect(clientMock.history.post[0].url).toBe('/_admin/order/document/create');
        });
    });

    describe('generate', () => {
        it('is defined', () => {
            const { orderDocumentApiService } = createOrderDocumentApiService();

            expect(orderDocumentApiService.generate).toBeDefined();
        });

        it('calls the correct endpoint', () => {
            const { orderDocumentApiService, clientMock } = createOrderDocumentApiService();

            const documentType = 'invoice';
            const payload = {};
            const additionalParams = {};

            orderDocumentApiService.generate(documentType, payload, additionalParams);

            expect(clientMock.history.post[0].url).toBe(`/_action/order/document/${documentType}/create`);
        });
    });


    describe('download', () => {
        it('is defined', () => {
            const { orderDocumentApiService } = createOrderDocumentApiService();

            expect(orderDocumentApiService.download).toBeDefined();
        });

        it('calls the correct endpoint', () => {
            const { orderDocumentApiService, clientMock } = createOrderDocumentApiService();

            const documentIds = [1, 2, 3];
            const additionalParams = {};

            orderDocumentApiService.download(documentIds, additionalParams);

            expect(clientMock.history.post[0].url).toBe('/_action/order/document/download');
        });
    });

    /**
     * @deprecated tag:v6.5.0 - Will be removed
     */
    describe('extendingDeprecatedService', () => {
        it('is defined', () => {
            const { orderDocumentApiService } = createOrderDocumentApiService();

            expect(orderDocumentApiService.extendingDeprecatedService).toBeDefined();
        });

        it('calls the correct endpoint', () => {
            const { orderDocumentApiService, clientMock } = createOrderDocumentApiService();

            orderDocumentApiService.extendingDeprecatedService();

            expect(clientMock.history.get[0].url).toBe('/_action/document/extending-deprecated-service');
        });
    });
});
