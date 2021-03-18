import DocumentApiService from 'src/core/service/api/document.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function getDocumentApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);

    const documentApiService = new DocumentApiService(client, loginService);
    return { documentApiService, clientMock };
}

function expectCreateDocumentFinished({ action }) {
    expect(action).toEqual('create-document-finished');
}

function expectCreateDocumentFailed({ action }) {
    expect(action).toEqual('create-document-fail');
}

describe('documentService', () => {
    it('is registered correctly', () => {
        const { documentApiService } = getDocumentApiService();
        expect(documentApiService).toBeInstanceOf(DocumentApiService);
    });

    it('is send create document request correctly', () => {
        const { documentApiService, clientMock } = getDocumentApiService();

        documentApiService.setListener(expectCreateDocumentFinished);

        const orderId = '4a4a687257644d52bf481b4c20e59213';
        clientMock.onPost('/_action/order/4a4a687257644d52bf481b4c20e59213/document/invoice')
            .reply(
                200,
                {
                    documentId: '4d03324edcd0490b9180df8161c9167f',
                    documentDeepLink: 'COp6DlWc2JgUn3XOb7QzKXWcWIVrH8XN'
                }
            );

        const params = {
            custom: {
                invoiceNumber: '1000'
            },
            documentNumber: '1000',
            documentComment: '',
            documentDate: '2021-02-22T04:34:56.441Z'
        };

        documentApiService.createDocument(
            orderId,
            'invoice',
            params,
            null,
            null,
            {}
        );
    });

    it('is send create document request return error', async () => {
        const { documentApiService, clientMock } = getDocumentApiService();

        documentApiService.setListener(expectCreateDocumentFailed);

        const dispatchSpy = jest.fn();
        Object.defineProperty(Shopware.State, 'dispatch', {
            value: dispatchSpy
        });

        const mockCreateDocumentEvent = jest.fn();
        documentApiService.setListener(mockCreateDocumentEvent);

        const orderId = '4a4a687257644d52bf481b4c20e59213';
        clientMock.onPost('/_action/order/4a4a687257644d52bf481b4c20e59213/document/invoice')
            .reply(400, {
                errors: [{
                    status: '400',
                    code: 'DOCUMENT__NUMBER_ALREADY_EXISTS',
                    title: 'Bad Request',
                    detail: 'Document number 1000 has already been allocated.',
                    meta: {
                        parameters: {
                            number: '1000'
                        }
                    }
                }]
            });

        const params = {
            custom: {
                invoiceNumber: '1000'
            },
            documentNumber: '1000',
            documentComment: '',
            documentDate: '2021-02-22T04:34:56.441Z'
        };

        await documentApiService.createDocument(
            orderId,
            'invoice',
            params,
            null,
            null,
            {}
        );
    });
});
