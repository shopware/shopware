/**
 * @sw-package inventory
 */
import { getSavedDocumentValues } from './product-document.helper';

function createDocument(translated: { title: string; mediaId: string }) {
    const document = Shopware.Service('repositoryFactory').create('product_document').create();
    document.translated = translated as Entity<'product_document'>['translated'];
    return document;
}

describe('src/module/sw-product/component/sw-product-document-form/product-document.helper', () => {
    /**
     * Variant document copies were looked up by the unsaved title and file, so none matched.
     * @regression
     * @see https://github.com/shopware/shopware/pull/23269
     */
    it('returns the saved title and file of a saved document, not its unsaved changes', () => {
        const document = createDocument({ title: 'Manual v2', mediaId: 'new-media' });
        Object.assign(document, { _isNew: false });
        Object.assign(document.getOrigin(), { translated: { title: 'Manual', mediaId: 'saved-media' } });

        expect(getSavedDocumentValues(document)).toEqual({ title: 'Manual', mediaId: 'saved-media' });
    });

    it('returns the current title and file of a new document', () => {
        const document = createDocument({ title: 'Manual', mediaId: 'media' });

        expect(getSavedDocumentValues(document)).toEqual({ title: 'Manual', mediaId: 'media' });
    });
});
