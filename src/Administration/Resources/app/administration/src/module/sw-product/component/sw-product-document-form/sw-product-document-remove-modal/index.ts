/**
 * @sw-package inventory
 */
import type { PropType } from 'vue';
import useNotification from 'src/app/composables/use-notification';
import useSnackbar from 'src/app/composables/use-snackbar';
import type { DocumentVariantOperation } from 'src/core/service/api/product-document-variant.api.service';
import template from './sw-product-document-remove-modal.html.twig';
import './sw-product-document-remove-modal.scss';

const { Store } = Shopware;

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['productDocumentVariantService'],

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
        isSystemDefaultLanguage(): boolean {
            return Store.get('context').isSystemDefaultLanguage;
        },

        isBulk(): boolean {
            return this.documents.length > 1;
        },

        documentName(): string {
            return this.documents[0]?.translated?.title ?? '';
        },

        productId(): string {
            return Store.get('swProductDetail').product.id;
        },

        modalTitle(): string {
            const values = { count: this.documents.length, language: this.languageName, name: this.documentName };
            if (this.isBulk) {
                return this.$t(
                    this.isSystemDefaultLanguage
                        ? 'sw-product.documentForm.bulkRemoveTitle'
                        : 'sw-product.documentForm.bulkRemoveLanguageVersionTitle',
                    values,
                );
            }

            return this.$t(
                this.isSystemDefaultLanguage
                    ? 'sw-product.documentForm.removeTitle'
                    : 'sw-product.documentForm.removeLanguageVersionTitle',
                values,
            );
        },

        modalDescription(): string {
            if (!this.isSystemDefaultLanguage) {
                return this.$t('sw-product.documentForm.removeLanguageVersionDescription', {
                    language: this.languageName,
                });
            }

            return this.$t(
                this.isBulk ? 'sw-product.documentForm.bulkRemoveDescription' : 'sw-product.documentForm.removeDescription',
            );
        },

        isDisabled(): boolean {
            return !this.canEdit || this.isRemoving;
        },
    },

    methods: {
        getSuccessMessage(variantCount: number): string {
            const values = { count: this.documents.length, language: this.languageName, name: this.documentName };
            if (this.isBulk) {
                return this.$t(
                    this.isSystemDefaultLanguage
                        ? 'sw-product.documentForm.bulkRemoveSuccess'
                        : 'sw-product.documentForm.bulkRemoveLanguageVersionSuccess',
                    values,
                );
            }

            return this.isSystemDefaultLanguage
                ? this.$t('sw-product.documentForm.removeSuccess', { ...values, count: variantCount })
                : this.$t('sw-product.documentForm.removeLanguageVersionSuccess', values);
        },

        async remove(): Promise<void> {
            if (this.isDisabled) {
                return;
            }

            const operations: DocumentVariantOperation[] =
                this.removeFromVariants && this.hasVariants
                    ? this.documents.flatMap((document) => {
                          const { mediaId, title } = document.translated ?? {};
                          return mediaId ? [{ mediaId, title: title || null }] : [];
                      })
                    : [];
            const productId = this.productId;
            let variantCount = 0;

            if (operations.length) {
                this.isRemoving = true;
                try {
                    variantCount = (await this.productDocumentVariantService.sync(productId, operations, true)).length;
                } catch {
                    this.isRemoving = false;
                    useNotification().createNotificationError({
                        message: this.$t('sw-product.documentForm.errorLoadVariants'),
                    });
                    return;
                }
            }

            if (this.productId !== productId) {
                return;
            }

            const message = this.getSuccessMessage(variantCount);

            this.$emit('remove');
            Store.get('swProductDetail').documentVariantOperations.push(...operations);
            useSnackbar().addSnackbar({ variant: 'success', message });
        },
    },
});
