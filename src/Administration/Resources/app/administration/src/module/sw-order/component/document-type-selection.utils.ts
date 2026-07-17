/**
 * @private
 * @sw-package after-sales
 */
import { DOCUMENT_TYPES } from '../order.types';

const INVOICE_DOCUMENT_TYPES: string[] = [
    DOCUMENT_TYPES.INVOICE,
    DOCUMENT_TYPES.ZUGFERD_INVOICE,
    DOCUMENT_TYPES.ZUGFERD_EMBEDDED_INVOICE,
];

const CREDIT_NOTE_DOCUMENT_TYPES: string[] = [
    DOCUMENT_TYPES.CREDIT_NOTE,
    DOCUMENT_TYPES.ZUGFERD_CREDIT_NOTE,
    DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CREDIT_NOTE,
];

const CANCELLATION_INVOICE_DOCUMENT_TYPES: string[] = [
    DOCUMENT_TYPES.CANCELLATION_INVOICE,
    DOCUMENT_TYPES.ZUGFERD_CANCELLATION_INVOICE,
    DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CANCELLATION_INVOICE,
];

const FILE_FORMAT_PRIORITY: string[] = [
    'pdf',
    'html',
    'zugferd_embedded_pdf',
    'zugferd_xml',
];

const FILE_FORMAT_MIME_TYPES: Record<string, string> = {
    html: 'text/html',
    pdf: 'application/pdf',
    zugferd_embedded_pdf: 'application/pdf',
    zugferd_xml: 'application/xml,text/xml',
};

function getDocumentFamily(technicalName?: string | null): string | null {
    if (!technicalName) {
        return null;
    }

    if (INVOICE_DOCUMENT_TYPES.includes(technicalName)) {
        return DOCUMENT_TYPES.INVOICE;
    }

    if (CREDIT_NOTE_DOCUMENT_TYPES.includes(technicalName)) {
        return DOCUMENT_TYPES.CREDIT_NOTE;
    }

    if (CANCELLATION_INVOICE_DOCUMENT_TYPES.includes(technicalName)) {
        return DOCUMENT_TYPES.CANCELLATION_INVOICE;
    }

    return technicalName;
}

function getDocumentNumberRangeType(technicalName: string): string {
    return getDocumentFamily(technicalName) ?? technicalName;
}

/**
 * @private
 */
export {
    getDocumentFamily,
    getDocumentNumberRangeType,
    INVOICE_DOCUMENT_TYPES,
    CREDIT_NOTE_DOCUMENT_TYPES,
    CANCELLATION_INVOICE_DOCUMENT_TYPES,
    FILE_FORMAT_PRIORITY,
    FILE_FORMAT_MIME_TYPES,
};
