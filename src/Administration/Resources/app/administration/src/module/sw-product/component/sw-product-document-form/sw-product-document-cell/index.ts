/**
 * @sw-package inventory
 */
import type { PropType } from 'vue';
import { getDocumentTitle, getDocumentMedia, getDocumentMediaId } from '../product-document.helper';
import template from './sw-product-document-cell.html.twig';
import './sw-product-document-cell.scss';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        document: {
            type: Object as PropType<Entity<'product_document'>>,
            required: true,
        },
        column: {
            type: String as PropType<'title' | 'fileName' | 'fileType' | 'fileSize'>,
            required: true,
        },
        showVariantBadge: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        media(): Entity<'media'> | null | undefined {
            return getDocumentMedia(this.document);
        },

        value(): string {
            const media = this.media;
            switch (this.column) {
                case 'title':
                    return getDocumentTitle(this.document);
                case 'fileName':
                    return media?.fileName ? [media.fileName, media.fileExtension].filter(Boolean).join('.') : '';
                case 'fileType':
                    return media?.fileExtension?.toUpperCase() ?? '';
                default:
                    return typeof media?.fileSize === 'number' ? Shopware.Utils.format.fileSize(media.fileSize) : '';
            }
        },

        isLanguageColumn(): boolean {
            return this.column === 'title' || this.column === 'fileName';
        },

        isTranslated(): boolean {
            return this.column === 'title' ? !!this.document.title : !!this.document.mediaId;
        },

        isDefaultLanguage(): boolean {
            return Shopware.Store.get('context').isSystemDefaultLanguage;
        },

        languageCode(): string {
            const { languageId } = Shopware.Store.get('context').api;
            const language = Shopware.Store.get('context').api.language as Entity<'language'> | null;
            return language?.id === languageId ? language.locale?.code.split('-')[0].toUpperCase() || '' : '';
        },

        isVariantOnly(): boolean {
            const { product, parentProduct } = Shopware.Store.get('swProductDetail');
            const mediaId = getDocumentMediaId(this.document);
            return (
                this.showVariantBadge &&
                !!product.parentId &&
                !!mediaId &&
                !parentProduct.documents?.some(
                    (document) =>
                        getDocumentMediaId(document) === mediaId ||
                        document.translations?.some((translation) => translation.mediaId === mediaId),
                )
            );
        },
    },
});
