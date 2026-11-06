/**
 * @sw-package inventory
 */
import type { PropType } from 'vue';
import template from './sw-product-document-variant-badge.html.twig';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        document: {
            type: Object as PropType<Entity<'product_document'>>,
            required: true,
        },
    },

    computed: {
        isVariantOnly(): boolean {
            const { product, parentProduct, newDocuments } = Shopware.Store.get('swProductDetail');
            const mediaId = this.document.mediaId || this.document.translated?.mediaId;
            return (
                newDocuments.has(this.document) &&
                !!product.parentId &&
                !!mediaId &&
                !parentProduct.documents?.some(
                    (document) =>
                        document.mediaId === mediaId ||
                        document.translated?.mediaId === mediaId ||
                        document.translations?.some((translation) => translation.mediaId === mediaId),
                )
            );
        },
    },
});
