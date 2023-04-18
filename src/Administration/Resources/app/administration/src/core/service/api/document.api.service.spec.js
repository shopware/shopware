import DocumentApiService from 'src/core/service/api/document.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

/**
 * @package customer-order
 */

function getDocumentApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);

    const documentApiService = new DocumentApiService(client, loginService);
    return { documentApiService, clientMock };
}

function expectCreateDocumentFinished({ action }) {
    expect(action).toBe('create-document-finished');
}

function expectCreateDocumentFailed({ action }) {
    expect(action).toBe('create-document-fail');
}

describe('documentService', () => {
    it('is registered correctly', async () => {
        const { documentApiService } = getDocumentApiService();
        expect(documentApiService).toBeInstanceOf(DocumentApiService);
    });

    it('is sending create document request correctly', async () => {
        const { documentApiService, clientMock } = getDocumentApiService();

        documentApiService.setListener(expectCreateDocumentFinished);

        const orderId = '4a4a687257644d52bf481b4c20e59213';
        let createRequestSent = false;

        clientMock.onPost('/_action/order/document/invoice/create', [
            {
                orderId,
                config: {
                    custom: { invoiceNumber: '1000' },
                    documentNumber: '1000',
                    documentComment: '',
                    documentDate: '2021-02-22T04:34:56.441Z',
                },
                referencedDocumentId: null,
            },
        ]).reply(() => {
            createRequestSent = true;
            return [
                200,
                {
                    data: [{
                        documentId: '4d03324edcd0490b9180df8161c9167f',
                        documentDeepLink: 'COp6DlWc2JgUn3XOb7QzKXWcWIVrH8XN',
                    }],
                },
            ];
        });

        const params = {
            custom: {
                invoiceNumber: '1000',
            },
            documentNumber: '1000',
            documentComment: '',
            documentDate: '2021-02-22T04:34:56.441Z',
        };

        await documentApiService.createDocument(
            orderId,
            'invoice',
            params,
            null,
            {},
            {},
        );

        await flushPromises();
        expect(createRequestSent).toBeTruthy();
    });

    it('is sending create document request correctly with file', async () => {
        const { documentApiService, clientMock } = getDocumentApiService();

        documentApiService.setListener(expectCreateDocumentFinished);

        const orderId = '4a4a687257644d52bf481b4c20e59213';

        const config = {
            custom: {
                invoiceNumber: '1000',
            },
            documentNumber: '1000',
            documentComment: '',
            documentDate: '2021-02-22T04:34:56.441Z',
        };

        const documentId = '4d03324edcd0490b9180df8161c9167f';
        let createRequestSent = false;
        let uploadRequestSent = false;

        clientMock.onPost('/_action/order/document/invoice/create', [
            {
                orderId,
                config: {
                    custom: { invoiceNumber: '1000' },
                    documentNumber: '1000',
                    documentComment: '',
                    documentDate: '2021-02-22T04:34:56.441Z',
                },
                referencedDocumentId: null,
                static: true,
            },
        ]).reply(() => {
            createRequestSent = true;
            return [
                200,
                {
                    data: [{
                        documentId,
                        documentDeepLink: 'COp6DlWc2JgUn3XOb7QzKXWcWIVrH8XN',
                    }],
                },
            ];
        });

        const file = new File(['test document'], 'upload_file.pdf', { type: 'application/pdf' });

        clientMock.onPost(`/_action/document/${documentId}/upload?fileName=${config.documentNumber}_upload_file&extension=pdf`).reply(() => {
            uploadRequestSent = true;

            return [
                200,
                {
                    documentId: '4d03324edcd0490b9180df8161c9167f',
                    documentDeepLink: 'COp6DlWc2JgUn3XOb7QzKXWcWIVrH8XN',
                },
            ];
        });

        await documentApiService.createDocument(
            orderId,
            'invoice',
            config,
            null,
            null,
            {},
            file,
        );

        await flushPromises();

        expect(createRequestSent).toBeTruthy();
        expect(uploadRequestSent).toBeTruthy();
    });

    it('is sending create document request return error', async () => {
        const { documentApiService, clientMock } = getDocumentApiService();

        documentApiService.setListener(expectCreateDocumentFailed);

        const dispatchSpy = jest.fn();
        Object.defineProperty(Shopware.State, 'dispatch', {
            value: dispatchSpy,
        });

        const mockCreateDocumentEvent = jest.fn();
        documentApiService.setListener(mockCreateDocumentEvent);

        const orderId = '4a4a687257644d52bf481b4c20e59213';
        let requestSentCount = 0;

        clientMock.onPost('/_action/order/document/invoice/create')
            .reply(() => {
                requestSentCount += 1;

                return [
                    400,
                    {
                        errors: [{
                            status: '400',
                            code: 'DOCUMENT__NUMBER_ALREADY_EXISTS',
                            title: 'Bad Request',
                            detail: 'Document number 1000 has already been allocated.',
                            meta: {
                                parameters: {
                                    number: '1000',
                                },
                            },
                        }],
                    },
                ];
            });

        const config = {
            custom: {
                invoiceNumber: '1000',
            },
            documentNumber: '1000',
            documentComment: '',
            documentDate: '2021-02-22T04:34:56.441Z',
        };

        await documentApiService.createDocument(
            orderId,
            'invoice',
            config,
        );

        clientMock.onPost('/_action/order/document/invoice/create')
            .reply(() => {
                requestSentCount += 1;

                return [
                    200,
                    {
                        errors: {
                            [orderId]: {
                                status: '400',
                                code: 'DOCUMENT__NUMBER_ALREADY_EXISTS',
                                title: 'Bad Request',
                                detail: 'Document number 1000 has already been allocated.',
                                meta: {
                                    parameters: {
                                        number: '1000',
                                    },
                                },
                            },
                        },
                    },
                ];
            });

        await documentApiService.createDocument(
            orderId,
            'invoice',
            config,
        );

        await flushPromises();

        expect(requestSentCount).toBe(2);
    });

    it('calls getDocumentPreview with correct endpoint', async () => {
        const { documentApiService, clientMock } = getDocumentApiService();

        let didRequest = false;
        const orderId = '4a4a687257644d52bf481b4c20e59213';
        const orderDeepLink = 'DEEP_LINK';
        const type = 'invoice';

        clientMock.onGet(`/_action/order/${orderId}/${orderDeepLink}/document/${type}/preview`)
            .reply(() => {
                didRequest = true;

                return [
                    200,
                    {
                        documentId: '4d03324edcd0490b9180df8161c9167f',
                        documentDeepLink: 'COp6DlWc2JgUn3XOb7QzKXWcWIVrH8XN',
                    },
                ];
            });

        documentApiService.getDocumentPreview(orderId, orderDeepLink, type, {});
        expect(didRequest).toBeTruthy();
    });

    it('calls getDocument with correct endpoint', async () => {
        const { documentApiService, clientMock } = getDocumentApiService();

        let didRequest = false;
        const documentId = '4a4a687257644d52bf481b4c20e59213';
        const deepLink = 'DEEP_LINK';

        clientMock.onGet(`/_action/document/${documentId}/${deepLink}`)
            .reply(() => {
                didRequest = true;

                return [200, ''];
            });

        documentApiService.getDocument(documentId, deepLink, {});
        expect(didRequest).toBeTruthy();
    });
});
