interface DocumentConfig {
    documentComment: string;
    documentDate: string;
    documentNumber: string;
    requestedFileFormats: string[];
    documentMediaFileId?: string | null;
}

interface DeliveryNoteConfig extends DocumentConfig {
    deliveryDate: string;
}

interface DocumentEntityConfig {
    custom?: {
        invoiceNumber?: string;
    };
}

/**
 * @sw-package after-sales
 * @private
 */
export type  {
    DocumentConfig,
    DeliveryNoteConfig,
    DocumentEntityConfig,
}

const DOCUMENT_TYPES = {
    INVOICE: 'invoice',
    DELIVERY_NOTE: 'delivery_note',
    CREDIT_NOTE: 'credit_note',
    CANCELLATION_INVOICE: 'storno',
    ZUGFERD_INVOICE: 'zugferd_invoice',
    ZUGFERD_EMBEDDED_INVOICE: 'zugferd_embedded_invoice',
    ZUGFERD_CANCELLATION_INVOICE: 'zugferd_cancellation_invoice',
    ZUGFERD_EMBEDDED_CANCELLATION_INVOICE: 'zugferd_embedded_cancellation_invoice',
    ZUGFERD_CREDIT_NOTE: 'zugferd_credit_note',
    ZUGFERD_EMBEDDED_CREDIT_NOTE: 'zugferd_embedded_credit_note',
} as const;

const ZUGFERD_DOCUMENT_TYPES = [
    DOCUMENT_TYPES.ZUGFERD_INVOICE,
    DOCUMENT_TYPES.ZUGFERD_EMBEDDED_INVOICE,
    DOCUMENT_TYPES.ZUGFERD_CANCELLATION_INVOICE,
    DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CANCELLATION_INVOICE,
    DOCUMENT_TYPES.ZUGFERD_CREDIT_NOTE,
    DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CREDIT_NOTE,
] as const;

const INVOICE_DOCUMENT_TYPES: string[] = [
    DOCUMENT_TYPES.INVOICE,
    DOCUMENT_TYPES.ZUGFERD_INVOICE,
    DOCUMENT_TYPES.ZUGFERD_EMBEDDED_INVOICE,
] as const;

const CREDIT_NOTE_DOCUMENT_TYPES: string[] = [
    DOCUMENT_TYPES.CREDIT_NOTE,
    DOCUMENT_TYPES.ZUGFERD_CREDIT_NOTE,
    DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CREDIT_NOTE,
] as const;

const CANCELLATION_INVOICE_DOCUMENT_TYPES: string[] = [
    DOCUMENT_TYPES.CANCELLATION_INVOICE,
    DOCUMENT_TYPES.ZUGFERD_CANCELLATION_INVOICE,
    DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CANCELLATION_INVOICE,
] as const;

const FILE_FORMAT_PRIORITY: string[] = [
    'pdf',
    'html',
    'zugferd_embedded_pdf',
    'zugferd_xml',
] as const;

const FILE_FORMAT_MIME_TYPES: Record<string, string> = {
    html: 'text/html',
    pdf: 'application/pdf',
    zugferd_embedded_pdf: 'application/pdf',
    zugferd_xml: 'application/xml,text/xml',
} as const;

/**
 * @sw-package after-sales
 * @private
 */
export {
    DOCUMENT_TYPES,
    ZUGFERD_DOCUMENT_TYPES,
    INVOICE_DOCUMENT_TYPES,
    CREDIT_NOTE_DOCUMENT_TYPES,
    CANCELLATION_INVOICE_DOCUMENT_TYPES,
    FILE_FORMAT_MIME_TYPES,
};

/**
 * @sw-package after-sales
 * @private
 * @class
 */
export default class DocumentV2Service {
    getDocumentFamily(technicalName: string | null): string | null {
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

    getDocumentNumberRangeType(technicalName: string): string {
        return this.getDocumentFamily(technicalName) ?? technicalName;
    }

    private getFileFormatPriority(fileFormat: string): number {
        const priority = FILE_FORMAT_PRIORITY.indexOf(fileFormat);

        return priority === -1 ? Number.MAX_SAFE_INTEGER : priority;
    }

    sortFileFormats(fileFormats: string[]): string[] {
        return [...fileFormats].sort((left, right) => {
            return this.getFileFormatPriority(left) - this.getFileFormatPriority(right);
        });
    }

    getPreferredFileFormat(fileFormats: string[], defaultFormat: string | null = null): string | null {
        return this.sortFileFormats(fileFormats)[0] ?? defaultFormat;
    }

    public createEmptyDocumentConfig(technicalName: string | null = null): DocumentConfig {
        const now = new Date().toISOString();
        const documentFamily = this.getDocumentFamily(technicalName);

        if (documentFamily === DOCUMENT_TYPES.DELIVERY_NOTE) {
            return {
                documentComment: '',
                documentDate: now,
                documentNumber: '',
                deliveryDate: now,
                requestedFileFormats: [],
            } as DeliveryNoteConfig;
        }

        return {
            documentComment: '',
            documentDate: now,
            documentNumber: '',
            requestedFileFormats: [],
        };
    }

    filterDocumentsByTypes(documents: Entity<'document'>[], documentTypes: string[]): Entity<'document'>[] {
        return documents.filter((document) => {
            const technicalName = document.documentType?.technicalName;

            return typeof technicalName === 'string' && documentTypes.includes(technicalName);
        });
    }

    getDocumentNumbersByTypes(documents: Entity<'document'>[], documentTypes: string[]): string[] {
        return this.filterDocumentsByTypes(documents, documentTypes).map((document) => document.documentNumber).filter((documentNumber): documentNumber is string => !!documentNumber);
    }

    getFileFormatSnippet(format: string): string {
        const translationKey = (
            {
                html: 'sw-order.components.createDocumentModal.fileFormats.html',
                pdf: 'sw-order.components.createDocumentModal.fileFormats.pdf',
                zugferd_embedded_pdf: 'sw-order.components.createDocumentModal.fileFormats.zugferdEmbeddedPdf',
                zugferd_xml: 'sw-order.components.createDocumentModal.fileFormats.zugferdXml',
            } as Record<string, string>
        )[format];

        return translationKey ?? format;
    }
}
