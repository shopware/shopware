/**
 * @sw-package inventory
 */
import type { PropType } from 'vue';
import template from './sw-product-document-edit-modal.html.twig';

type FieldError = { code: string; detail: string };

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        allowFallback: {
            type: Boolean,
            required: false,
            default: false,
        },
        languageName: {
            type: String,
            required: false,
            default: '',
        },
        isAddingLanguageVersion: {
            type: Boolean,
            required: false,
            default: false,
        },
        documentTitlePlaceholder: {
            type: String,
            required: false,
            default: '',
        },
        documentTitle: {
            type: String,
            required: true,
        },
        editingMedia: {
            type: Object as PropType<Entity<'media'> | undefined>,
            required: false,
            default: undefined,
        },
        documentNameError: {
            type: Object as PropType<FieldError | null>,
            required: false,
            default: null,
        },
        documentFileError: {
            type: Object as PropType<FieldError | null>,
            required: false,
            default: null,
        },
        uploadTag: {
            type: String,
            required: true,
        },
        uploadHint: {
            type: String,
            required: true,
        },
        allowedExtensions: {
            type: Array as PropType<string[]>,
            required: true,
        },
        maxFileSize: {
            type: Number,
            required: true,
        },
        canEdit: {
            type: Boolean,
            required: false,
            default: false,
        },
        isSavingDocument: {
            type: Boolean,
            required: false,
            default: false,
        },
        hasVariants: {
            type: Boolean,
            required: false,
            default: false,
        },
        applyToVariants: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: [
        'close',
        'save',
        'upload',
        'file-remove',
        'media-select',
        'update:documentTitle',
        'update:applyToVariants',
    ],

    computed: {
        modalTitle(): string {
            return this.isAddingLanguageVersion
                ? this.$t('sw-product.documentForm.addLanguageVersion', { language: this.languageName })
                : this.$t('sw-product.documentForm.editTitle');
        },

        documentNameHint(): string | undefined {
            return this.allowFallback ? undefined : this.$t('sw-product.documentForm.nameHint');
        },

        documentFileHint(): string {
            return this.allowFallback ? this.$t('sw-product.documentForm.fileFallbackHint') : this.uploadHint;
        },

        extensionAccept(): string {
            return this.allowedExtensions.join(',');
        },

        isDisabled(): boolean {
            return !this.canEdit || this.isSavingDocument;
        },
    },
});
