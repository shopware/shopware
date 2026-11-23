/** @sw-package inventory */
import {
    cleanupWrappers,
    createProduct,
    createWrapper,
} from 'src/module/sw-product/component/sw-product-document-form/sw-product-document-form.spec/helpers';

describe('sw-product-detail-base documents', () => {
    beforeEach(() => {
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = createProduct();
        store.parentProduct = createProduct();
    });

    afterEach(() => {
        cleanupWrappers();
    });

    it.each(['physical', 'digital'])('places documents immediately below media for a %s product', async (type) => {
        Shopware.Store.get('swProductDetail').product.type = type;
        const wrapper = await createWrapper(undefined, undefined, undefined, { baseView: true });
        await flushPromises();

        const cards = wrapper.findAll('.sw-product-detail-base__media, .sw-product-detail-base__documents');
        expect(cards).toHaveLength(2);
        expect(cards[0].classes()).toContain('sw-product-detail-base__media');
        expect(cards[1].classes()).toContain('sw-product-detail-base__documents');
    });
});
