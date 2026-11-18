/**
 * @sw-package inventory
 */
import { getDocumentTitle, setDocumentMedia } from './product-document.helper';

function createDocument(values: Partial<Entity<'product_document'>> = {}): Entity<'product_document'> {
    return { title: null, translated: {}, mediaId: 'media-id', ...values } as unknown as Entity<'product_document'>;
}

describe('product-document.helper', () => {
    describe('getDocumentTitle', () => {
        it('returns the title of the document', () => {
            expect(getDocumentTitle(createDocument({ title: 'Manual', translated: { title: 'Handbuch' } }))).toBe('Manual');
        });

        it('returns the translated title when the document has no own title', () => {
            expect(getDocumentTitle(createDocument({ translated: { title: 'Manual' } }))).toBe('Manual');
        });

        it('does not fall back to the file name', () => {
            const document = createDocument();
            setDocumentMedia(document, { id: 'media-id', fileName: 'operating-manual' } as Entity<'media'>);

            expect(getDocumentTitle(document)).toBe('');
        });
    });
});
