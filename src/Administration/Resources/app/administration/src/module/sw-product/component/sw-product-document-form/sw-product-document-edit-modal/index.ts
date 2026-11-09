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
            default: false,
        },

        languageName: {
            type: String,
            default: '',
        },

        isAddingLanguageVersion: {
            type: Boolean,
            default: false,
        },

        documentTitlePlaceholder: {
            type: String,
            default: '',
        },

        documentId: {
            type: String,
            required: true,
        },

        documentTitle: {
            type: String,
            required: true,
        },

        editingMedia: {
            type: Object as PropType<Entity<'media'> | undefined>,
            default: undefined,
        },

        documentNameError: {
            type: Object as PropType<FieldError | null>,
            default: null,
        },

        documentFileError: {
            type: Object as PropType<FieldError | null>,
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
            default: false,
        },

        isSavingDocument: {
            type: Boolean,
            default: false,
        },
    },

    emits: [
        'close',
        'save',
        'upload',
        'remove-file',
        'select-media',
        'update:documentTitle',
    ],

    computed: {
        isDisabled(): boolean {
            return !this.canEdit || this.isSavingDocument;
        },
    },
});
