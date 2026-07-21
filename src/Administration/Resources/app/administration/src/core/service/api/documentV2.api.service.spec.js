import DocumentV2ApiService from 'src/core/service/api/documentV2.api.service';
import { DocumentEvents } from 'src/core/service/api/document.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

/**
 * @sw-package after-sales
 */

function createDocumentV2ApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const documentV2ApiService = new DocumentV2ApiService(client, loginService);

    return { documentV2ApiService, clientMock };
}

describe('documentV2Service', () => {
    it('is registered correctly', async () => {
        const { documentV2ApiService } = createDocumentV2ApiService();

        expect(documentV2ApiService).toBeInstanceOf(DocumentV2ApiService);
        expect(documentV2ApiService.name).toBe('documentV2Service');
    });

    it('loads the support metadata', async () => {
        const { documentV2ApiService, clientMock } = createDocumentV2ApiService();

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

        const response = await documentV2ApiService.getAvailableTypes();

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

    it('creates a document with the selected formats', async () => {
        const { documentV2ApiService, clientMock } = createDocumentV2ApiService();
        const listener = jest.fn();

        documentV2ApiService.setListener(listener);

        const orderId = '4a4a687257644d52bf481b4c20e59213';
        const orderVersionId = '4d03324edcd0490b9180df8161c9167f';

        clientMock.onPost('/_action/order/document-v2/create').reply(200, {
            documentId: '4d03324edcd0490b9180df8161c9167f',
            deepLinkCode: 'COp6DlWc2JgUn3XOb7QzKXWcWIVrH8XN',
            formats: [
                'html',
                'zugferd_xml',
            ],
        });

        await documentV2ApiService.createDocument(
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
            formats: [
                'html',
                'zugferd_xml',
            ],
            documentNumber: '1000',
            documentDate: '2021-02-22T04:34:56.441Z',
            documentComment: '',
        });
        expect(listener).not.toHaveBeenCalled();
    });

    it('uploads a document from an existing media file', async () => {
        const { documentV2ApiService, clientMock } = createDocumentV2ApiService();

        clientMock.onPost('/_action/order/document-v2/upload').reply(200, {
            documentId: '4d03324edcd0490b9180df8161c9167f',
            deepLinkCode: 'COp6DlWc2JgUn3XOb7QzKXWcWIVrH8XN',
            formats: ['pdf'],
        });

        await documentV2ApiService.uploadDocument(
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
            format: 'pdf',
            documentNumber: '1000',
            documentDate: '2021-02-22T04:34:56.441Z',
            documentComment: '',
            mediaId: 'media-id',
            referencedDocumentId: 'referenced-document-id',
        });
    });

    it('uploads a document from a binary file body', async () => {
        const { documentV2ApiService, clientMock } = createDocumentV2ApiService();
        const file = new File(['test document'], 'invoice.final.pdf', {
            type: 'application/pdf',
        });

        clientMock.onPost('/_action/order/document-v2/upload').reply(200, {
            documentId: '4d03324edcd0490b9180df8161c9167f',
            deepLinkCode: 'COp6DlWc2JgUn3XOb7QzKXWcWIVrH8XN',
            formats: ['pdf'],
        });

        await documentV2ApiService.uploadDocument(
            '4a4a687257644d52bf481b4c20e59213',
            '4d03324edcd0490b9180df8161c9167f',
            'invoice',
            'pdf',
            '1000',
            '2021-02-22T04:34:56.441Z',
            '',
            null,
            file,
            'referenced-document-id',
        );

        expect(clientMock.history.post[0].url).toBe('/_action/order/document-v2/upload');
        expect(clientMock.history.post[0].data).toBe(file);
        expect(clientMock.history.post[0].headers['Content-Type']).toBe('application/pdf');
        expect(clientMock.history.post[0].params).toEqual({
            orderId: '4a4a687257644d52bf481b4c20e59213',
            orderVersionId: '4d03324edcd0490b9180df8161c9167f',
            documentType: 'invoice',
            format: 'pdf',
            documentNumber: '1000',
            documentDate: '2021-02-22T04:34:56.441Z',
            documentComment: '',
            mediaId: null,
            referencedDocumentId: 'referenced-document-id',
            extension: 'pdf',
            fileName: 'invoice.final',
        });
    });

    it('previews a document with the selected format', async () => {
        const { documentV2ApiService, clientMock } = createDocumentV2ApiService();

        clientMock.onPost('/_action/order/document-v2/preview').reply(200, {
            content: '<html></html>',
        });

        await documentV2ApiService.previewDocument(
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
            format: 'html',
            documentNumber: '1000',
            documentDate: '2021-02-22T04:34:56.441Z',
            documentComment: '',
        });
    });

    it('emits a document failed event when previewing fails', async () => {
        const { documentV2ApiService, clientMock } = createDocumentV2ApiService();
        const listener = jest.fn();

        documentV2ApiService.setListener(listener);

        const errorBody = {
            errors: [
                {
                    code: 'DOCUMENT__UNSUPPORTED_DOCUMENT_FORMAT',
                    detail: 'Unsupported document format.',
                },
            ],
        };

        clientMock.onPost('/_action/order/document-v2/preview').reply(() => {
            return [
                400,
                new Blob([JSON.stringify(errorBody)], {
                    type: 'application/json',
                }),
            ];
        });

        const response = await documentV2ApiService.previewDocument(
            '4a4a687257644d52bf481b4c20e59213',
            '4d03324edcd0490b9180df8161c9167f',
            'invoice',
            'html',
            '1000',
            '2021-02-22T04:34:56.441Z',
            '',
        );

        expect(response).toBeUndefined();
        expect(listener).toHaveBeenCalledWith({
            action: DocumentEvents.DOCUMENT_FAILED,
            payload: {
                code: 'DOCUMENT__UNSUPPORTED_DOCUMENT_FORMAT',
                detail: 'Unsupported document format.',
            },
        });
    });

    it('downloads a document file', async () => {
        const { documentV2ApiService, clientMock } = createDocumentV2ApiService();
        const documentId = '4a4a687257644d52bf481b4c20e59213';
        const format = 'pdf';

        clientMock.onGet(`/_action/order/document-v2/${documentId}/download/${format}`).reply(200, '');

        await documentV2ApiService.getDocument(documentId, format);

        expect(clientMock.history.get[0].url).toBe(`/_action/order/document-v2/${documentId}/download/${format}`);
    });

    it('downloads all document files as archive', async () => {
        const { documentV2ApiService, clientMock } = createDocumentV2ApiService();
        const documentId = '4a4a687257644d52bf481b4c20e59213';

        clientMock.onGet(`/_action/order/document-v2/${documentId}/download-archive`).reply(200, '');

        await documentV2ApiService.getDocumentArchive(documentId);

        expect(clientMock.history.get[0].url).toBe(`/_action/order/document-v2/${documentId}/download-archive`);
    });
});
