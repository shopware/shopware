/**
 * @sw-package inventory
 */
import type { PropType } from 'vue';
import useNotification from 'src/app/composables/use-notification';
import {
    getDocumentTitle,
    getTranslated,
    getDocumentMediaId,
    syncDocumentVariants,
    type DocumentVariantOperation,
} from '../product-document.helper';
import template from './sw-product-document-remove-modal.html.twig';
import './sw-product-document-remove-modal.scss';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        documents: {
            type: Array as PropType<Entity<'product_document'>[]>,
            required: true,
        },
        languageName: {
            type: String,
            required: false,
            default: '',
        },
        canEdit: {
            type: Boolean,
            required: false,
            default: false,
        },
        hasVariants: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: [
        'close',
        'remove',
    ],

    data() {
        return {
            removeFromVariants: this.hasVariants,
            isRemoving: false,
        };
    },

    computed: {
        modalTitle(): string {
            const count = this.documents.length;
            if (count > 1) {
                return this.$t(
                    this.languageName
                        ? 'sw-product.documentForm.bulkRemoveLanguageVersionTitle'
                        : 'sw-product.documentForm.bulkRemoveTitle',
                    { count, language: this.languageName },
                );
            }

            const name = getDocumentTitle(this.documents[0]);
            return this.languageName
                ? this.$t('sw-product.documentForm.removeLanguageVersionTitle', { language: this.languageName, name })
                : this.$t('sw-product.documentForm.removeTitle', { name });
        },

        modalDescription(): string {
            if (this.languageName) {
                return this.$t('sw-product.documentForm.removeLanguageVersionDescription', {
                    language: this.languageName,
                });
            }

            return this.$t(
                this.documents.length > 1
                    ? 'sw-product.documentForm.bulkRemoveDescription'
                    : 'sw-product.documentForm.removeDescription',
            );
        },

        isDisabled(): boolean {
            return !this.canEdit || this.isRemoving;
        },
    },

    methods: {
        async remove(): Promise<void> {
            if (this.isDisabled) {
                return;
            }

            const operations: DocumentVariantOperation[] =
                this.removeFromVariants && this.hasVariants
                    ? this.documents.flatMap((document) => {
                          const mediaId = getDocumentMediaId(document);
                          const { title } = getTranslated(document);
                          return mediaId ? [{ mediaId, title: document.title || title || null }] : [];
                      })
                    : [];
            let variantCount = 0;

            if (operations.length) {
                this.isRemoving = true;
                try {
                    variantCount = (await syncDocumentVariants(this.documents[0].productId, operations, true)).length;
                } catch {
                    this.isRemoving = false;
                    useNotification().createNotificationError({
                        message: this.$t('sw-product.documentForm.errorLoadVariants'),
                    });
                    return;
                }
            }

            this.$emit('remove', { operations, variantCount });
        },
    },
});
