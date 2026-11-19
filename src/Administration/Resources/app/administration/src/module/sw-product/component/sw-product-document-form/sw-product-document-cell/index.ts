/**
 * @sw-package inventory
 */
import type { PropType } from 'vue';
import { getDocumentMedia } from '../product-document.helper';
import template from './sw-product-document-cell.html.twig';
import './sw-product-document-cell.scss';

const { Store } = Shopware;

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        document: {
            type: Object as PropType<Entity<'product_document'>>,
            required: true,
        },
        field: {
            type: String as PropType<'title' | 'mediaId'>,
            required: true,
        },
        showVariantBadge: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        value(): string {
            if (this.field === 'title') {
                return this.document.translated?.title ?? '';
            }

            const media = getDocumentMedia(this.document);
            return media?.fileName ? [media.fileName, media.fileExtension].filter(Boolean).join('.') : '';
        },

        isFallback(): boolean {
            return !Store.get('context').isSystemDefaultLanguage && !this.document[this.field];
        },

        languageCode(): string {
            const context = Store.get('context');
            const language = context.api.language as Entity<'language'> | null;
            if (context.isSystemDefaultLanguage || language?.id !== context.api.languageId) {
                return '';
            }

            return language.locale?.code.split('-')[0].toUpperCase() ?? '';
        },

        isVariantOnly(): boolean {
            const { product, parentProduct } = Store.get('swProductDetail');
            const mediaId = this.document.translated?.mediaId;
            return (
                this.showVariantBadge &&
                this.field === 'title' &&
                !!product.parentId &&
                !!mediaId &&
                !parentProduct.documents?.some(
                    (parentDocument) =>
                        parentDocument.translated?.mediaId === mediaId ||
                        parentDocument.translations?.some((translation) => translation.mediaId === mediaId),
                )
            );
        },
    },
});
