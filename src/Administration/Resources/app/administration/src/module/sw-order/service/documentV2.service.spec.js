/**
 * @sw-package after-sales
 */

import DocumentV2Service, { DOCUMENT_TYPES, FILE_FORMATS, INVOICE_DOCUMENT_TYPES } from './documentV2.service';

const createDocument = (documentType) => {
    return {
        documentType: {
            technicalName: documentType,
        },
        documentNumber: `1000-${documentType}`,
    };
};

describe('core/service/documentV2.service.ts', () => {
    it.each([
        [
            DOCUMENT_TYPES.INVOICE,
            DOCUMENT_TYPES.INVOICE,
        ],
        [
            DOCUMENT_TYPES.DELIVERY_NOTE,
            DOCUMENT_TYPES.DELIVERY_NOTE,
        ],
        [
            DOCUMENT_TYPES.CREDIT_NOTE,
            DOCUMENT_TYPES.CREDIT_NOTE,
        ],
        [
            DOCUMENT_TYPES.CANCELLATION_INVOICE,
            DOCUMENT_TYPES.CANCELLATION_INVOICE,
        ],
        [
            DOCUMENT_TYPES.ZUGFERD_INVOICE,
            DOCUMENT_TYPES.INVOICE,
        ],
        [
            DOCUMENT_TYPES.ZUGFERD_EMBEDDED_INVOICE,
            DOCUMENT_TYPES.INVOICE,
        ],
        [
            DOCUMENT_TYPES.ZUGFERD_CANCELLATION_INVOICE,
            DOCUMENT_TYPES.CANCELLATION_INVOICE,
        ],
        [
            DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CANCELLATION_INVOICE,
            DOCUMENT_TYPES.CANCELLATION_INVOICE,
        ],
        [
            DOCUMENT_TYPES.ZUGFERD_CREDIT_NOTE,
            DOCUMENT_TYPES.CREDIT_NOTE,
        ],
        [
            DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CREDIT_NOTE,
            DOCUMENT_TYPES.CREDIT_NOTE,
        ],
        [
            null,
            null,
        ],
        [
            'foo',
            'foo',
        ],
    ])('should get the correct document family', async (documentType, expected) => {
        const documentV2Service = new DocumentV2Service();

        expect(documentV2Service.getDocumentFamily(documentType)).toBe(expected);
    });

    it.each([
        [
            DOCUMENT_TYPES.INVOICE,
            DOCUMENT_TYPES.INVOICE,
        ],
        [
            DOCUMENT_TYPES.DELIVERY_NOTE,
            DOCUMENT_TYPES.DELIVERY_NOTE,
        ],
        [
            DOCUMENT_TYPES.CREDIT_NOTE,
            DOCUMENT_TYPES.CREDIT_NOTE,
        ],
        [
            DOCUMENT_TYPES.CANCELLATION_INVOICE,
            DOCUMENT_TYPES.CANCELLATION_INVOICE,
        ],
        [
            DOCUMENT_TYPES.ZUGFERD_INVOICE,
            DOCUMENT_TYPES.INVOICE,
        ],
        [
            DOCUMENT_TYPES.ZUGFERD_EMBEDDED_INVOICE,
            DOCUMENT_TYPES.INVOICE,
        ],
        [
            DOCUMENT_TYPES.ZUGFERD_CANCELLATION_INVOICE,
            DOCUMENT_TYPES.CANCELLATION_INVOICE,
        ],
        [
            DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CANCELLATION_INVOICE,
            DOCUMENT_TYPES.CANCELLATION_INVOICE,
        ],
        [
            DOCUMENT_TYPES.ZUGFERD_CREDIT_NOTE,
            DOCUMENT_TYPES.CREDIT_NOTE,
        ],
        [
            DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CREDIT_NOTE,
            DOCUMENT_TYPES.CREDIT_NOTE,
        ],
        [
            'foo',
            'foo',
        ],
        [
            null,
            null,
        ],
    ])('should get the correct document number range type', (documentType, expected) => {
        const documentV2Service = new DocumentV2Service();

        expect(documentV2Service.getDocumentNumberRangeType(documentType)).toBe(expected);
    });

    it.each([
        [
            [
                FILE_FORMATS.ZUGFERD_XML,
                FILE_FORMATS.ZUGFERD_EMBEDDED_PDF,
                FILE_FORMATS.PDF,
                FILE_FORMATS.HTML,
            ],
            [
                FILE_FORMATS.PDF,
                FILE_FORMATS.HTML,
                FILE_FORMATS.ZUGFERD_EMBEDDED_PDF,
                FILE_FORMATS.ZUGFERD_XML,
            ],
        ],
        [
            [],
            [],
        ],
        [
            [
                'foo',
                FILE_FORMATS.ZUGFERD_XML,
                FILE_FORMATS.ZUGFERD_EMBEDDED_PDF,
                'bar',
                FILE_FORMATS.PDF,
                FILE_FORMATS.HTML,
            ],
            [
                FILE_FORMATS.PDF,
                FILE_FORMATS.HTML,
                FILE_FORMATS.ZUGFERD_EMBEDDED_PDF,
                FILE_FORMATS.ZUGFERD_XML,
                'foo',
                'bar',
            ],
        ],
        [
            [
                'bar',
                'foo',
            ],
            [
                'bar',
                'foo',
            ],
        ],
    ])('should bring formats in the correct order', (formats, expectedOrder) => {
        const documentV2Service = new DocumentV2Service();

        expect(documentV2Service.sortFileFormats(formats)).toStrictEqual(expectedOrder);
    });

    it.each([
        [
            [],
            undefined,
            null,
        ],
        [
            [],
            'foo',
            'foo',
        ],
        [
            [
                FILE_FORMATS.ZUGFERD_XML,
                FILE_FORMATS.ZUGFERD_EMBEDDED_PDF,
                FILE_FORMATS.PDF,
                FILE_FORMATS.HTML,
            ],
            undefined,
            FILE_FORMATS.PDF,
        ],
        [
            [
                FILE_FORMATS.ZUGFERD_XML,
                FILE_FORMATS.ZUGFERD_EMBEDDED_PDF,
                FILE_FORMATS.PDF,
                FILE_FORMATS.HTML,
            ],
            FILE_FORMATS.HTML,
            FILE_FORMATS.PDF,
        ],
        [
            [
                'foo',
                'bar',
            ],
            FILE_FORMATS.HTML,
            'foo',
        ],
    ])('should get the preferred file format', (fileFormats, defaultFormat, expectedFormat) => {
        const documentV2Service = new DocumentV2Service();

        expect(documentV2Service.getPreferredFileFormat(fileFormats, defaultFormat)).toStrictEqual(expectedFormat);
    });

    it.each([
        [
            undefined,
            { documentComment: '', documentDate: expect.any(String), documentNumber: '', requestedFileFormats: [] },
        ],
        [
            null,
            { documentComment: '', documentDate: expect.anything(), documentNumber: '', requestedFileFormats: [] },
        ],
        [
            DOCUMENT_TYPES.INVOICE,
            { documentComment: '', documentDate: expect.anything(), documentNumber: '', requestedFileFormats: [] },
        ],
        [
            DOCUMENT_TYPES.DELIVERY_NOTE,
            {
                deliveryDate: expect.any(String),
                documentComment: '',
                documentDate: expect.any(String),
                documentNumber: '',
                requestedFileFormats: [],
            },
        ],
    ])('should create an empty document configuration', (documentType, expectedConfiguration) => {
        const documentV2Service = new DocumentV2Service();

        expect(documentV2Service.createEmptyDocumentConfig(documentType)).toStrictEqual(expectedConfiguration);
    });

    it.each([
        [
            [],
            [],
            [],
        ],
        [
            [],
            INVOICE_DOCUMENT_TYPES,
            [],
        ],
        [
            [
                createDocument(DOCUMENT_TYPES.DELIVERY_NOTE),
                createDocument('foo'),
                createDocument(DOCUMENT_TYPES.INVOICE),
                createDocument(DOCUMENT_TYPES.CREDIT_NOTE),
                createDocument(DOCUMENT_TYPES.CANCELLATION_INVOICE),
                createDocument(DOCUMENT_TYPES.ZUGFERD_INVOICE),
            ],
            INVOICE_DOCUMENT_TYPES,
            [
                createDocument(DOCUMENT_TYPES.INVOICE),
                createDocument(DOCUMENT_TYPES.ZUGFERD_INVOICE),
            ],
        ],
    ])('should filter given documents by provided document types', (documents, documentTypes, expectedDocuments) => {
        const documentV2Service = new DocumentV2Service();

        expect(documentV2Service.filterDocumentsByTypes(documents, documentTypes)).toStrictEqual(expectedDocuments);
    });

    it.each([
        [
            [],
            [],
            [],
        ],
        [
            [],
            INVOICE_DOCUMENT_TYPES,
            [],
        ],
        [
            [
                createDocument(DOCUMENT_TYPES.DELIVERY_NOTE),
                createDocument('foo'),
                createDocument(DOCUMENT_TYPES.INVOICE),
                createDocument(DOCUMENT_TYPES.CREDIT_NOTE),
                createDocument(DOCUMENT_TYPES.CANCELLATION_INVOICE),
                createDocument(DOCUMENT_TYPES.ZUGFERD_INVOICE),
            ],
            INVOICE_DOCUMENT_TYPES,
            [
                `1000-${DOCUMENT_TYPES.INVOICE}`,
                `1000-${DOCUMENT_TYPES.ZUGFERD_INVOICE}`,
            ],
        ],
    ])('should filter given documents by provided document types', (documents, documentTypes, expectedDocuments) => {
        const documentV2Service = new DocumentV2Service();

        expect(documentV2Service.getDocumentNumbersByTypes(documents, documentTypes)).toStrictEqual(expectedDocuments);
    });

    it.each([
        [
            FILE_FORMATS.HTML,
            'sw-order.components.createDocumentModal.fileFormats.html',
        ],
        [
            FILE_FORMATS.PDF,
            'sw-order.components.createDocumentModal.fileFormats.pdf',
        ],
        [
            FILE_FORMATS.ZUGFERD_XML,
            'sw-order.components.createDocumentModal.fileFormats.zugferdXml',
        ],
        [
            FILE_FORMATS.ZUGFERD_EMBEDDED_PDF,
            'sw-order.components.createDocumentModal.fileFormats.zugferdEmbeddedPdf',
        ],
        [
            'foo',
            'foo',
        ],
    ])('should get correct file format snippet', (fileFormat, expectedSnippet) => {
        const documentV2Service = new DocumentV2Service();

        expect(documentV2Service.getFileFormatSnippet(fileFormat)).toStrictEqual(expectedSnippet);
    });

    it.each([
        [
            DOCUMENT_TYPES.INVOICE,
            'sw-order.components.createDocumentModal.documentTypes.invoice',
        ],
        [
            DOCUMENT_TYPES.CREDIT_NOTE,
            'sw-order.components.createDocumentModal.documentTypes.creditNote',
        ],
        [
            DOCUMENT_TYPES.CANCELLATION_INVOICE,
            'sw-order.components.createDocumentModal.documentTypes.cancellationInvoice',
        ],
        [
            DOCUMENT_TYPES.DELIVERY_NOTE,
            'sw-order.components.createDocumentModal.documentTypes.deliveryNote',
        ],
        [
            'foo',
            'foo',
        ],
    ])('should get correct document type snippet', (documentType, expectedSnippet) => {
        const documentV2Service = new DocumentV2Service();

        expect(documentV2Service.getDocumentTypeSnippet(documentType)).toStrictEqual(expectedSnippet);
    });

    it('should request the available document types only once and share the result', async () => {
        const getAvailableTypes = jest.fn().mockResolvedValue({
            documentTypes: { invoice: { formats: ['pdf'] } },
        });
        const documentV2Service = new DocumentV2Service({ getAvailableTypes });

        const [
            first,
            second,
        ] = await Promise.all([
            documentV2Service.getAvailableDocumentTypes(),
            documentV2Service.getAvailableDocumentTypes(),
        ]);
        const third = await documentV2Service.getAvailableDocumentTypes();

        expect(getAvailableTypes).toHaveBeenCalledTimes(1);
        expect(first).toEqual({ invoice: { formats: ['pdf'] } });
        expect(second).toBe(first);
        expect(third).toBe(first);
    });

    it('should not cache a failed available document types request', async () => {
        const getAvailableTypes = jest
            .fn()
            .mockRejectedValueOnce(new Error('nope'))
            .mockResolvedValue({ documentTypes: { invoice: { formats: ['pdf'] } } });
        const documentV2Service = new DocumentV2Service({ getAvailableTypes });

        await expect(documentV2Service.getAvailableDocumentTypes()).rejects.toThrow('nope');
        await expect(documentV2Service.getAvailableDocumentTypes()).resolves.toEqual({
            invoice: { formats: ['pdf'] },
        });
        expect(getAvailableTypes).toHaveBeenCalledTimes(2);
    });
});
