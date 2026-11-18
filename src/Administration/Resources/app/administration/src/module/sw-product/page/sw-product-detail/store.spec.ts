/**
 * @sw-package inventory
 */
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';

describe('swProductDetail product document media', () => {
    const store = Shopware.Store.get('swProductDetail');

    beforeEach(() => {
        store.$reset();
    });

    it('keeps the latest file details when selecting the same media again', () => {
        const media = repositoryFactory.create('media').create();
        media.fileName = 'manual';
        store.setProductDocumentMedia(media);
        const updatedMedia = repositoryFactory.create('media').create(Shopware.Context.api, media.id);
        updatedMedia.fileName = 'updated-manual';

        store.setProductDocumentMedia(updatedMedia);

        expect(store.getProductDocumentMedia(media.id)?.fileName).toBe('updated-manual');
    });

    it('keeps unsaved file details with their product when navigating between products', () => {
        const firstProduct = repositoryFactory.create('product').create();
        const secondProduct = repositoryFactory.create('product').create();
        const media = repositoryFactory.create('media').create();
        media.fileName = 'manual';
        store.product = firstProduct;
        store.setProductDocumentMedia(media);

        store.product = secondProduct;
        expect(store.getProductDocumentMedia(media.id)).toBeUndefined();

        store.product = firstProduct;
        expect(store.getProductDocumentMedia(media.id)?.fileName).toBe('manual');
    });
});
