/**
 * @sw-package after-sales
 */
import { translateDocumentFileFormat } from 'src/module/sw-flow/constant/document-file-format.constant';

describe('module/sw-flow/constant/document-file-format.constant', () => {
    it.each([
        ['pdf', 'sw-flow.modals.document.fileFormats.pdf'],
        ['html', 'sw-flow.modals.document.fileFormats.html'],
        ['zugferd_xml', 'sw-flow.modals.document.fileFormats.zugferdXml'],
        ['zugferd_embedded_pdf', 'sw-flow.modals.document.fileFormats.zugferdEmbeddedPdf'],
    ])('should translate the known format "%s" via the given translation key "%s"', (format, expectedKey) => {
        const translate = jest.fn((key: string) => `translated:${key}`);

        const label = translateDocumentFileFormat(format, translate);

        expect(translate).toHaveBeenCalledWith(expectedKey);
        expect(label).toBe(`translated:${expectedKey}`);
    });

    it('should fall back to the raw format when it has no known translation key', () => {
        const translate = jest.fn((key: string) => `translated:${key}`);

        const label = translateDocumentFileFormat('unknown_format', translate);

        expect(translate).not.toHaveBeenCalled();
        expect(label).toBe('unknown_format');
    });
});
