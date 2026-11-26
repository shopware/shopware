/**
 * @sw-package inventory
 */
import type { PropType } from 'vue';
import useNotification from 'src/app/composables/use-notification';
import type { DocumentVariantOperation } from 'src/module/sw-product/service/product-document-variant.service';
import { getSavedDocumentValues } from '../product-document.helper';
import template from './sw-product-document-remove-modal.html.twig';
import './sw-product-document-remove-modal.scss';

const { Store } = Shopware;

/** @private */
export type DocumentRemoval = { operations: Record<string, DocumentVariantOperation>; message: string };

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
    },

    emits: [
        'close',
        'remove',
    ],

    data() {
        return {
            removeFromVariants: true,
            isRemoving: false,
        };
    },

    computed: {
        isSystemDefaultLanguage(): boolean {
            return Store.get('context').isSystemDefaultLanguage;
        },

        hasVariants(): boolean {
            const { product } = Store.get('swProductDetail');
            return !product.parentId && !!product.childCount;
        },

        isBulk(): boolean {
            return this.documents.length > 1;
        },

        documentName(): string {
            return this.documents[0]?.translated?.title ?? '';
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

        operations(): Record<string, DocumentVariantOperation> {
            if (!this.removeFromVariants || !this.hasVariants) {
                return {};
            }

            const queuedOperations = Store.get('swProductDetail').documentVariantOperations;
            const removal = this.isSystemDefaultLanguage
                ? { type: 'remove' as const }
                : { type: 'update' as const, changes: { title: null, mediaId: null } };

            return Object.fromEntries(
                this.documents.flatMap((document) => {
                    const { mediaId, title } = queuedOperations[document.id] ?? getSavedDocumentValues(document);
                    return mediaId ? [[document.id, { ...removal, mediaId, title: title || null }]] : [];
                }),
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

            const { operations } = this;
            const { product } = Store.get('swProductDetail');
            const showsVariantCount = !this.isBulk && this.isSystemDefaultLanguage;
            let variantCount = 0;

            if (showsVariantCount && Object.keys(operations).length) {
                this.isRemoving = true;
                try {
                    variantCount = (
                        await this.productDocumentVariantService.sync(product.id, Object.values(operations), true)
                    ).length;
                } catch {
                    this.isRemoving = false;
                    useNotification().createNotificationError({
                        message: this.$t('sw-product.documentForm.errorLoadVariants'),
                    });
                    return;
                }
            }

            if (Store.get('swProductDetail').product !== product) {
                return;
            }

            this.$emit('remove', { operations, message: this.getSuccessMessage(variantCount) } satisfies DocumentRemoval);
        },
    },
});
