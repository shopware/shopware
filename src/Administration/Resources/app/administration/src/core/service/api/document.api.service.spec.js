import DocumentApiService from 'src/core/service/api/document.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

/**
 * @sw-package checkout
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

        clientMock
            .onPost('/_action/order/document/invoice/create', [
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
            ])
            .reply(() => {
                createRequestSent = true;
                return [
                    200,
                    {
                        data: [
                            {
                                documentId: '4d03324edcd0490b9180df8161c9167f',
                                documentDeepLink: 'COp6DlWc2JgUn3XOb7QzKXWcWIVrH8XN',
                            },
                        ],
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

        await documentApiService.createDocument(orderId, 'invoice', params, null, {}, {});

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

        clientMock
            .onPost('/_action/order/document/invoice/create', [
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
            ])
            .reply(() => {
                createRequestSent = true;
                return [
                    200,
                    {
                        data: [
                            {
                                documentId,
                                documentDeepLink: 'COp6DlWc2JgUn3XOb7QzKXWcWIVrH8XN',
                            },
                        ],
                    },
                ];
            });

        const file = new File(['test document'], 'upload_file.pdf', {
            type: 'application/pdf',
        });

        clientMock
            .onPost(`/_action/document/${documentId}/upload?fileName=${config.documentNumber}_upload_file&extension=pdf`)
            .reply(() => {
                uploadRequestSent = true;

                return [
                    200,
                    {
                        documentId: '4d03324edcd0490b9180df8161c9167f',
                        documentDeepLink: 'COp6DlWc2JgUn3XOb7QzKXWcWIVrH8XN',
                    },
                ];
            });

        await documentApiService.createDocument(orderId, 'invoice', config, null, null, {}, file);

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

        clientMock.onPost('/_action/order/document/invoice/create').reply(() => {
            requestSentCount += 1;

            return [
                400,
                {
                    errors: [
                        {
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
                    ],
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

        await documentApiService.createDocument(orderId, 'invoice', config);

        clientMock.onPost('/_action/order/document/invoice/create').reply(() => {
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

        await documentApiService.createDocument(orderId, 'invoice', config);

        await flushPromises();

        expect(requestSentCount).toBe(2);
    });

    it('calls getDocumentPreview with correct endpoint', async () => {
        const { documentApiService, clientMock } = getDocumentApiService();

        let didRequest = false;
        const orderId = '4a4a687257644d52bf481b4c20e59213';
        const orderDeepLink = 'DEEP_LINK';
        const type = 'invoice';

        clientMock.onGet(`/_action/order/${orderId}/${orderDeepLink}/document/${type}/preview`).reply(() => {
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

    it('handles an error when getDocumentPreview is being called', async () => {
        const { documentApiService, clientMock } = getDocumentApiService();

        documentApiService.setListener(expectCreateDocumentFailed);

        let didRequest = false;
        const orderId = '4a4a687257644d52bf481b4c20e59213';
        const orderDeepLink = 'DEEP_LINK';
        const type = 'invoice';
        const errorBody = {
            errors: [
                {
                    detail: 'some-error-detail',
                },
            ],
        };

        clientMock.onGet(`/_action/order/${orderId}/${orderDeepLink}/document/${type}/preview`).reply(() => {
            didRequest = true;

            return [
                500,
                new Blob([JSON.stringify(errorBody)], {
                    type: 'application/json',
                }),
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
        const fileType = 'pdf';

        clientMock.onGet(`/_action/document/${documentId}/${deepLink}?fileType=${fileType}`).reply(() => {
            didRequest = true;

            return [
                200,
                '',
            ];
        });

        documentApiService.getDocument(documentId, deepLink, {});
        expect(didRequest).toBeTruthy();
    });

    it('calls getDocumentV2 with correct endpoint', async () => {
        const { documentApiService, clientMock } = getDocumentApiService();

        const documentId = '4a4a687257644d52bf481b4c20e59213';
        const deepLink = 'DEEP_LINK';
        const fileType = 'pdf';

        clientMock.onGet(`/_action/order/document-v2/${documentId}/${deepLink}/download/${fileType}`).reply(200, '');

        await documentApiService.getDocumentV2(documentId, deepLink, fileType);

        expect(clientMock.history.get[0].url).toBe(
            `/_action/order/document-v2/${documentId}/${deepLink}/download/${fileType}`,
        );
    });

    it('loads the V2 support metadata', async () => {
        const { documentApiService, clientMock } = getDocumentApiService();

        clientMock.onGet('/_action/order/document-v2/available-types').reply(200, {
            documentTypes: {
                invoice: {
                    formats: [
                        'html',
                        'zugferd_xml',
                    ],
                },
            },
        });

        const response = await documentApiService.getDocumentV2AvailableTypes();

        expect(clientMock.history.get[0].url).toBe('/_action/order/document-v2/available-types');
        expect(response.data).toEqual({
            documentTypes: {
                invoice: {
                    formats: [
                        'html',
                        'zugferd_xml',
                    ],
                },
            },
        });
    });

    it('creates a V2 document with the selected formats', async () => {
        const { documentApiService, clientMock } = getDocumentApiService();

        documentApiService.setListener(expectCreateDocumentFinished);

        const orderId = '4a4a687257644d52bf481b4c20e59213';
        const orderVersionId = '4d03324edcd0490b9180df8161c9167f';

        clientMock.onPost('/_action/order/document-v2/create').reply(200, {
            documentId: '4d03324edcd0490b9180df8161c9167f',
            deepLinkCode: 'COp6DlWc2JgUn3XOb7QzKXWcWIVrH8XN',
            fileTypes: [
                'html',
                'zugferd_xml',
            ],
        });

        await documentApiService.createDocumentV2(
            orderId,
            orderVersionId,
            'invoice',
            [
                'html',
                'zugferd_xml',
            ],
            '1000',
            '2021-02-22T04:34:56.441Z',
            '',
        );

        expect(clientMock.history.post[0].url).toBe('/_action/order/document-v2/create');
        expect(JSON.parse(clientMock.history.post[0].data)).toEqual({
            orderId,
            orderVersionId,
            documentType: 'invoice',
            fileTypes: [
                'html',
                'zugferd_xml',
            ],
            documentNumber: '1000',
            documentDate: '2021-02-22T04:34:56.441Z',
            documentComment: '',
        });
    });

    it('uploads a V2 document with the selected format', async () => {
        const { documentApiService, clientMock } = getDocumentApiService();

        documentApiService.setListener(expectCreateDocumentFinished);

        clientMock.onPost('/_action/order/document-v2/upload').reply(200, {
            documentId: '4d03324edcd0490b9180df8161c9167f',
            deepLinkCode: 'COp6DlWc2JgUn3XOb7QzKXWcWIVrH8XN',
            fileTypes: ['pdf'],
        });

        await documentApiService.uploadDocumentV2(
            '4a4a687257644d52bf481b4c20e59213',
            '4d03324edcd0490b9180df8161c9167f',
            'invoice',
            'pdf',
            '1000',
            '2021-02-22T04:34:56.441Z',
            '',
            'media-id',
            null,
            'referenced-document-id',
        );

        expect(clientMock.history.post[0].url).toBe('/_action/order/document-v2/upload');
        expect(JSON.parse(clientMock.history.post[0].data)).toEqual({
            orderId: '4a4a687257644d52bf481b4c20e59213',
            orderVersionId: '4d03324edcd0490b9180df8161c9167f',
            documentType: 'invoice',
            fileType: 'pdf',
            documentNumber: '1000',
            documentDate: '2021-02-22T04:34:56.441Z',
            documentComment: '',
            mediaId: 'media-id',
            referencedDocumentId: 'referenced-document-id',
        });
    });

    it('previews a V2 document with the selected format', async () => {
        const { documentApiService, clientMock } = getDocumentApiService();

        clientMock.onPost('/_action/order/document-v2/preview').reply(200, {
            content: '<html></html>',
        });

        await documentApiService.getDocumentPreviewV2(
            '4a4a687257644d52bf481b4c20e59213',
            '4d03324edcd0490b9180df8161c9167f',
            'invoice',
            'html',
            '1000',
            '2021-02-22T04:34:56.441Z',
            '',
            {
                'sw-language-id': 'language-id',
            },
        );

        expect(clientMock.history.post[0].url).toBe('/_action/order/document-v2/preview');
        expect(clientMock.history.post[0].headers['sw-language-id']).toBe('language-id');
        expect(JSON.parse(clientMock.history.post[0].data)).toEqual({
            orderId: '4a4a687257644d52bf481b4c20e59213',
            orderVersionId: '4d03324edcd0490b9180df8161c9167f',
            documentType: 'invoice',
            fileType: 'html',
            documentNumber: '1000',
            documentDate: '2021-02-22T04:34:56.441Z',
            documentComment: '',
        });
    });
});
