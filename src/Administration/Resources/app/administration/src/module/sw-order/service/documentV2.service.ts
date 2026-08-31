import type DocumentV2ApiService from 'src/core/service/api/documentV2.api.service';
import type { DocumentTypeFormats } from 'src/core/service/api/documentV2.api.service';

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

/**
 * @sw-package after-sales
 * @private
 */
export type { DocumentConfig, DeliveryNoteConfig };

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

const FILE_FORMATS = {
    PDF: 'pdf',
    ZUGFERD_EMBEDDED_PDF: 'zugferd_embedded_pdf',
    HTML: 'html',
    ZUGFERD_XML: 'zugferd_xml',
} as const;

const FILE_FORMAT_PRIORITY: string[] = [
    FILE_FORMATS.PDF,
    FILE_FORMATS.HTML,
    FILE_FORMATS.ZUGFERD_EMBEDDED_PDF,
    FILE_FORMATS.ZUGFERD_XML,
] as const;

const FILE_FORMAT_MIME_TYPES: Record<string, string> = {
    [FILE_FORMATS.HTML]: 'text/html',
    [FILE_FORMATS.PDF]: 'application/pdf',
    [FILE_FORMATS.ZUGFERD_EMBEDDED_PDF]: 'application/pdf',
    [FILE_FORMATS.ZUGFERD_XML]: 'application/xml,text/xml',
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
    FILE_FORMATS,
    FILE_FORMAT_MIME_TYPES,
};

/**
 * @sw-package after-sales
 * @private
 * @class
 */
export default class DocumentV2Service {
    private availableDocumentTypes: Promise<Record<string, DocumentTypeFormats>> | null = null;

    constructor(private readonly documentV2ApiService: DocumentV2ApiService) {}

    public getAvailableDocumentTypes(): Promise<Record<string, DocumentTypeFormats>> {
        this.availableDocumentTypes ??= this.documentV2ApiService
            .getAvailableTypes()
            .then((response) => response.documentTypes ?? {})
            .catch((error) => {
                // Let the next caller retry instead of caching the failure forever.
                this.availableDocumentTypes = null;

                throw error;
            });

        return this.availableDocumentTypes;
    }

    public getDocumentFamily(technicalName: string | null): string | null {
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

    public getDocumentNumberRangeType(technicalName: string): string {
        return this.getDocumentFamily(technicalName) ?? technicalName;
    }

    private getFileFormatPriority(fileFormat: string): number {
        const priority = FILE_FORMAT_PRIORITY.indexOf(fileFormat);

        return priority === -1 ? Number.MAX_SAFE_INTEGER : priority;
    }

    public sortFileFormats(fileFormats: string[]): string[] {
        return [...fileFormats].sort((left, right) => {
            return this.getFileFormatPriority(left) - this.getFileFormatPriority(right);
        });
    }

    public getPreferredFileFormat(fileFormats: string[], defaultFormat: string | null = null): string | null {
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

    public filterDocumentsByTypes(documents: Entity<'document'>[], documentTypes: string[]): Entity<'document'>[] {
        return documents.filter((document) => {
            const technicalName = document.documentType?.technicalName;

            return typeof technicalName === 'string' && documentTypes.includes(technicalName);
        });
    }

    public getDocumentNumbersByTypes(documents: Entity<'document'>[], documentTypes: string[]): string[] {
        return this.filterDocumentsByTypes(documents, documentTypes)
            .map((document) => document.documentNumber)
            .filter((documentNumber): documentNumber is string => !!documentNumber);
    }

    public getFileFormatSnippet(format: string): string {
        const translationKey = (
            {
                [FILE_FORMATS.HTML]: 'sw-order.components.createDocumentModal.fileFormats.html',
                [FILE_FORMATS.PDF]: 'sw-order.components.createDocumentModal.fileFormats.pdf',
                [FILE_FORMATS.ZUGFERD_EMBEDDED_PDF]:
                    'sw-order.components.createDocumentModal.fileFormats.zugferdEmbeddedPdf',
                [FILE_FORMATS.ZUGFERD_XML]: 'sw-order.components.createDocumentModal.fileFormats.zugferdXml',
            } as Record<string, string>
        )[format];

        return translationKey ?? `sw-order.components.createDocumentModal.fileFormats.${format}`;
    }

    public getDocumentTypeLabel(technicalName: string, label?: Record<string, string> | null): string {
        if (label && Object.keys(label).length > 0) {
            const locale = Shopware.Store.get('session')?.currentLocale ?? 'en-GB';

            return label[locale] ?? label['en-GB'] ?? Object.values(label)[0] ?? technicalName;
        }

        const translationKey =
            (
                {
                    [DOCUMENT_TYPES.INVOICE]: 'sw-order.components.createDocumentModal.documentTypes.invoice',
                    [DOCUMENT_TYPES.CREDIT_NOTE]: 'sw-order.components.createDocumentModal.documentTypes.creditNote',
                    [DOCUMENT_TYPES.CANCELLATION_INVOICE]:
                        'sw-order.components.createDocumentModal.documentTypes.cancellationInvoice',
                    [DOCUMENT_TYPES.DELIVERY_NOTE]: 'sw-order.components.createDocumentModal.documentTypes.deliveryNote',
                } as Record<string, string>
            )[technicalName] ?? `sw-order.components.createDocumentModal.documentTypes.${technicalName}`;

        if (!Shopware.Snippet?.te?.(translationKey)) {
            return technicalName;
        }

        // @ts-expect-error
        return (Shopware.Snippet?.tc(translationKey) as string | undefined) ?? technicalName;
    }

    public getErrorTranslation(errorCode: string, errorParams: { [key: string]: unknown }): string | null {
        const app = Shopware.Application.getApplicationRoot();

        if (!app) {
            return null;
        }

        switch (errorCode) {
            case 'DOCUMENT_V2__CONFIG_MISSING_REQUIRED_FIELDS':
                return app.$t('sw-order.documentCard.error.missingCompanyInformation', {
                    field: app.$t(
                        `sw-settings-document.detail.label${errorParams.field?.toString().charAt(0).toLocaleUpperCase() ?? ''}${errorParams.field?.toString().substring(1) ?? ''}`,
                    ),
                });
            case 'DOCUMENT_V2__NO_UNPROCESSED_CREDIT_LINE_ITEMS':
                return app.$t('sw-order.documentCard.error.noUnprocessedCreditLineItems');
            case 'DOCUMENT_V2__DOCUMENT_NUMBER_ALREADY_EXISTS':
                return app.$t('sw-order.documentCard.error.duplicateDocumentNumber', {
                    documentNumber: errorParams.documentNumber,
                });
            default:
                return null;
        }
    }
}
