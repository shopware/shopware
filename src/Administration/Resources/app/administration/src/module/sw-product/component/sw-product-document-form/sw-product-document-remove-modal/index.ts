/**
 * @sw-package inventory
 */
import template from './sw-product-document-remove-modal.html.twig';
import './sw-product-document-remove-modal.scss';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        documentTitle: {
            type: String,
            required: true,
        },
        documentCount: {
            type: Number,
            required: false,
            default: 1,
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
        isRemovingDocument: {
            type: Boolean,
            required: false,
            default: false,
        },
        hasVariants: {
            type: Boolean,
            required: false,
            default: false,
        },
        removeFromVariants: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: ['close', 'remove', 'update:removeFromVariants'],

    computed: {
        modalTitle(): string {
            if (this.documentCount > 1) {
                return this.$t(
                    this.languageName
                        ? 'sw-product.documentForm.bulkRemoveLanguageVersionTitle'
                        : 'sw-product.documentForm.bulkRemoveTitle',
                    {
                        count: this.documentCount,
                        language: this.languageName,
                    },
                );
            }

            return this.languageName
                ? this.$t('sw-product.documentForm.removeLanguageVersionTitle', {
                      language: this.languageName,
                      name: this.documentTitle,
                  })
                : this.$t('sw-product.documentForm.removeTitle', { name: this.documentTitle });
        },
        modalDescription(): string {
            if (this.languageName) {
                return this.$t('sw-product.documentForm.removeLanguageVersionDescription', {
                    language: this.languageName,
                });
            }

            if (this.documentCount > 1) {
                return this.$t('sw-product.documentForm.bulkRemoveDescription');
            }

            return this.$t('sw-product.documentForm.removeDescription');
        },
        isDisabled(): boolean {
            return !this.canEdit || this.isRemovingDocument;
        },
    },
});
