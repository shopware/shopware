import { DOCUMENT_TYPES } from '../order.types';
import {
    CANCELLATION_INVOICE_DOCUMENT_TYPES,
    CREDIT_NOTE_DOCUMENT_TYPES,
    FILE_FORMAT_MIME_TYPES,
    FILE_FORMAT_PRIORITY,
    INVOICE_DOCUMENT_TYPES,
    getDocumentFamily,
    getDocumentNumberRangeType,
} from './document-type-selection.utils';

describe('document-type-selection.utils', () => {
    it.each([
        DOCUMENT_TYPES.INVOICE,
        DOCUMENT_TYPES.ZUGFERD_INVOICE,
        DOCUMENT_TYPES.ZUGFERD_EMBEDDED_INVOICE,
    ])('maps "%s" to the invoice document family', (technicalName) => {
        expect(getDocumentFamily(technicalName)).toBe(DOCUMENT_TYPES.INVOICE);
    });

    it.each([
        DOCUMENT_TYPES.CREDIT_NOTE,
        DOCUMENT_TYPES.ZUGFERD_CREDIT_NOTE,
        DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CREDIT_NOTE,
    ])('maps "%s" to the credit note document family', (technicalName) => {
        expect(getDocumentFamily(technicalName)).toBe(DOCUMENT_TYPES.CREDIT_NOTE);
    });

    it.each([
        DOCUMENT_TYPES.CANCELLATION_INVOICE,
        DOCUMENT_TYPES.ZUGFERD_CANCELLATION_INVOICE,
        DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CANCELLATION_INVOICE,
    ])('maps "%s" to the cancellation invoice document family', (technicalName) => {
        expect(getDocumentFamily(technicalName)).toBe(DOCUMENT_TYPES.CANCELLATION_INVOICE);
    });

    it('keeps unknown document types unchanged', () => {
        expect(getDocumentFamily('custom_document_type')).toBe('custom_document_type');
    });

    it('returns null when no technical name is available', () => {
        expect(getDocumentFamily()).toBeNull();
        expect(getDocumentFamily(null)).toBeNull();
    });

    it('uses the document family for number range reservations', () => {
        expect(getDocumentNumberRangeType(DOCUMENT_TYPES.ZUGFERD_INVOICE)).toBe(DOCUMENT_TYPES.INVOICE);
        expect(getDocumentNumberRangeType('custom_document_type')).toBe('custom_document_type');
    });

    it('exports the grouped document types used by the document modals', () => {
        expect(INVOICE_DOCUMENT_TYPES).toEqual([
            DOCUMENT_TYPES.INVOICE,
            DOCUMENT_TYPES.ZUGFERD_INVOICE,
            DOCUMENT_TYPES.ZUGFERD_EMBEDDED_INVOICE,
        ]);
        expect(CREDIT_NOTE_DOCUMENT_TYPES).toEqual([
            DOCUMENT_TYPES.CREDIT_NOTE,
            DOCUMENT_TYPES.ZUGFERD_CREDIT_NOTE,
            DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CREDIT_NOTE,
        ]);
        expect(CANCELLATION_INVOICE_DOCUMENT_TYPES).toEqual([
            DOCUMENT_TYPES.CANCELLATION_INVOICE,
            DOCUMENT_TYPES.ZUGFERD_CANCELLATION_INVOICE,
            DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CANCELLATION_INVOICE,
        ]);
    });

    it('exports the supported file format metadata used by the document modals', () => {
        expect(FILE_FORMAT_PRIORITY).toEqual([
            'pdf',
            'html',
            'zugferd_embedded_pdf',
            'zugferd_xml',
        ]);
        expect(FILE_FORMAT_MIME_TYPES).toEqual({
            html: 'text/html',
            pdf: 'application/pdf',
            zugferd_embedded_pdf: 'application/pdf',
            zugferd_xml: 'application/xml,text/xml',
        });
    });
});
