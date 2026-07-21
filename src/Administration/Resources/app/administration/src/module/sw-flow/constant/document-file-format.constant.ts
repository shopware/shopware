/**
 * @private
 * @sw-package after-sales
 */
export const DOCUMENT_FILE_FORMAT_TRANSLATION_KEYS: Record<string, string> = {
    pdf: 'sw-flow.modals.document.fileFormats.pdf',
    html: 'sw-flow.modals.document.fileFormats.html',
    zugferd_xml: 'sw-flow.modals.document.fileFormats.zugferdXml',
    zugferd_embedded_pdf: 'sw-flow.modals.document.fileFormats.zugferdEmbeddedPdf',
};

/**
 * @private
 * @sw-package after-sales
 */
export function translateDocumentFileFormat(format: string, translate: (key: string) => string): string {
    const translationKey = DOCUMENT_FILE_FORMAT_TRANSLATION_KEYS[format];

    return translationKey ? translate(translationKey) : format;
}
