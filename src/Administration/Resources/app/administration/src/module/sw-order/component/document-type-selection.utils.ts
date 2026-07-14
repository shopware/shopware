/**
 * @sw-package after-sales
 */
import { DOCUMENT_TYPES } from '../order.types';

type DocumentWithDocumentType = {
    documentType?: {
        technicalName?: string | null;
    } | null;
};

const REQUIRES_INVOICE: string[] = [
    DOCUMENT_TYPES.CANCELLATION_INVOICE,
    DOCUMENT_TYPES.CREDIT_NOTE,
    DOCUMENT_TYPES.ZUGFERD_CANCELLATION_INVOICE,
    DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CANCELLATION_INVOICE,
    DOCUMENT_TYPES.ZUGFERD_CREDIT_NOTE,
    DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CREDIT_NOTE,
];

const REQUIRES_CREDIT_ITEMS: string[] = [
    DOCUMENT_TYPES.CREDIT_NOTE,
    DOCUMENT_TYPES.ZUGFERD_CREDIT_NOTE,
    DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CREDIT_NOTE,
];

const INVOICE_DOCUMENT_TYPES: string[] = [
    DOCUMENT_TYPES.INVOICE,
    DOCUMENT_TYPES.ZUGFERD_INVOICE,
    DOCUMENT_TYPES.ZUGFERD_EMBEDDED_INVOICE,
];

function isDocumentTypeAvailable(technicalName: string, invoiceExists: boolean, creditItemCount: number): boolean {
    if (REQUIRES_INVOICE.includes(technicalName) && !invoiceExists) {
        return false;
    }

    if (REQUIRES_CREDIT_ITEMS.includes(technicalName) && creditItemCount === 0) {
        return false;
    }

    return true;
}

function getDocumentNumberRangeType(technicalName: string): string {
    if (
        [
            DOCUMENT_TYPES.INVOICE,
            DOCUMENT_TYPES.ZUGFERD_INVOICE,
            DOCUMENT_TYPES.ZUGFERD_EMBEDDED_INVOICE,
        ].includes(technicalName)
    ) {
        return DOCUMENT_TYPES.INVOICE;
    }

    if (
        [
            DOCUMENT_TYPES.CREDIT_NOTE,
            DOCUMENT_TYPES.ZUGFERD_CREDIT_NOTE,
            DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CREDIT_NOTE,
        ].includes(technicalName)
    ) {
        return DOCUMENT_TYPES.CREDIT_NOTE;
    }

    if (
        [
            DOCUMENT_TYPES.CANCELLATION_INVOICE,
            DOCUMENT_TYPES.ZUGFERD_CANCELLATION_INVOICE,
            DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CANCELLATION_INVOICE,
        ].includes(technicalName)
    ) {
        return DOCUMENT_TYPES.CANCELLATION_INVOICE;
    }

    return technicalName;
}

function getInvoiceDocuments<DocumentType extends DocumentWithDocumentType>(documents: DocumentType[]): DocumentType[] {
    return documents.filter((document) => {
        const technicalName = document.documentType?.technicalName;

        return typeof technicalName === 'string' && INVOICE_DOCUMENT_TYPES.includes(technicalName);
    });
}

/**
 * @private
 */
export { getDocumentNumberRangeType, getInvoiceDocuments, isDocumentTypeAvailable, REQUIRES_CREDIT_ITEMS, REQUIRES_INVOICE };
