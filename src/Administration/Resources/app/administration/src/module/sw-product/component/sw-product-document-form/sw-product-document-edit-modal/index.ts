/**
 * @sw-package inventory
 */
import { useId, type PropType } from 'vue';
import useNotification from 'src/app/composables/use-notification';
import useSnackbar from 'src/app/composables/use-snackbar';
import {
    allowedExtensions,
    maxFileSize,
    getUploadHint,
    getFileAccept,
    isValidDocumentMedia,
    getFallbackTranslation,
    getDocumentTitle,
    getTranslated,
    getDocumentMedia,
    getDocumentMediaId,
    syncDocumentVariants,
    type DocumentChanges,
    type DocumentVariantOperation,
} from '../product-document.helper';
import template from './sw-product-document-edit-modal.html.twig';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
        'fileValidationService',
    ],

    props: {
        document: {
            type: Object as PropType<Entity<'product_document'>>,
            required: true,
        },
        languageName: {
            type: String,
            required: false,
            default: '',
        },
        uploadTag: {
            type: String,
            required: true,
        },
        mediaFolderId: {
            type: String as PropType<string | null>,
            required: false,
            default: undefined,
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
        'apply',
    ],

    setup() {
        return { listenerId: useId() };
    },

    data() {
        const isDefaultLanguage = Shopware.Store.get('context').isSystemDefaultLanguage;

        return {
            title: isDefaultLanguage ? getDocumentTitle(this.document) : this.document.title || '',
            replacementMedia: null as Entity<'media'> | null,
            isFileRemoved: false,
            applyToVariants: this.hasVariants,
            hasSubmitted: false,
            isApplying: false,
            showMediaModal: false,
            allowedExtensions,
            maxFileSize,
        };
    },

    computed: {
        isDefaultLanguage(): boolean {
            return Shopware.Store.get('context').isSystemDefaultLanguage;
        },

        modalTitle(): string {
            return !this.isDefaultLanguage && !this.document.title && !this.document.mediaId
                ? this.$t('sw-product.documentForm.addLanguageVersion', { language: this.languageName })
                : this.$t('sw-product.documentForm.editTitle');
        },

        titlePlaceholder(): string {
            if (this.isDefaultLanguage) {
                return '';
            }

            return getFallbackTranslation(this.document, 'title')?.title || getDocumentMedia(this.document)?.fileName || '';
        },

        editingMedia(): Entity<'media'> | undefined {
            const showSavedFile = !this.isFileRemoved && (this.isDefaultLanguage || this.document.mediaId);
            return this.replacementMedia || (showSavedFile ? getDocumentMedia(this.document) || undefined : undefined);
        },

        nameError(): { code: string; detail: string } | null {
            return this.isDefaultLanguage && this.hasSubmitted && !this.title.trim()
                ? { code: 'PRODUCT_DOCUMENT_NAME_REQUIRED', detail: this.$t('sw-product.documentForm.errorNameRequired') }
                : null;
        },

        fileError(): { code: string; detail: string } | null {
            return this.isDefaultLanguage && this.hasSubmitted && !this.editingMedia
                ? { code: 'PRODUCT_DOCUMENT_FILE_REQUIRED', detail: this.$t('sw-product.documentForm.errorFileRequired') }
                : null;
        },

        fileHint(): string {
            return this.isDefaultLanguage ? getUploadHint(this.$t) : this.$t('sw-product.documentForm.fileFallbackHint');
        },

        fileAccept(): string {
            return getFileAccept(this.fileValidationService.extensionByType);
        },

        isDisabled(): boolean {
            return !this.canEdit || this.isApplying;
        },
    },

    methods: {
        async onUploadFinished({ targetId }: { targetId: EntityKey<'media'> }): Promise<void> {
            try {
                const media = await this.repositoryFactory.create('media').get(targetId, Shopware.Context.api);
                if (media) {
                    this.selectMedia([media]);
                }
            } catch {
                useNotification().createNotificationError({
                    message: this.$t('sw-product.documentForm.errorLoadMedia'),
                });
            }
        },

        selectMedia(mediaItems: Entity<'media'>[]): void {
            mediaItems
                .filter((media) => isValidDocumentMedia(media, this.document, this.$t))
                .forEach((media) => {
                    this.replacementMedia = media;
                    this.isFileRemoved = false;
                });
            this.showMediaModal = false;
        },

        removeFile(): void {
            this.replacementMedia = null;
            this.isFileRemoved = true;
        },

        getChanges(): DocumentChanges {
            const title = this.title.trim();
            const newTitle =
                this.isDefaultLanguage && title === getDocumentTitle(this.document).trim() ? undefined : title || null;
            const newMediaId = this.replacementMedia?.id ?? (this.isFileRemoved ? null : undefined);
            const changes: DocumentChanges = {};

            if (newTitle !== undefined && newTitle !== (this.document.title ?? null)) {
                changes.title = newTitle;
            }
            if (newMediaId !== undefined && newMediaId !== (this.document.mediaId ?? null)) {
                changes.mediaId = newMediaId;
            }

            return changes;
        },

        async apply(): Promise<void> {
            this.hasSubmitted = true;
            if (this.isDisabled || (this.isDefaultLanguage && (this.nameError || this.fileError))) {
                return;
            }

            const changes = this.getChanges();
            const mediaId = getDocumentMediaId(this.document);
            const { title } = getTranslated(this.document);
            const operation: DocumentVariantOperation | null =
                this.applyToVariants && this.hasVariants && mediaId
                    ? { mediaId, title: this.document.title || title || null, changes }
                    : null;
            let variantCount = 0;

            if (operation && Object.keys(changes).length) {
                this.isApplying = true;
                try {
                    variantCount = (await syncDocumentVariants(this.document.productId, [operation], true)).length;
                } catch {
                    this.isApplying = false;
                    useNotification().createNotificationError({
                        message: this.$t('sw-product.documentForm.errorLoadVariants'),
                    });
                    return;
                }
            }

            this.$emit('apply', { changes, media: this.replacementMedia });

            if (!operation) {
                return;
            }
            if (Object.keys(changes).length) {
                Shopware.Store.get('swProductDetail').documentVariantOperations.push(operation);
            }
            useSnackbar().addSnackbar({
                variant: 'success',
                message: this.$t('sw-product.documentForm.applySuccess', {
                    name: getDocumentTitle(this.document),
                    count: variantCount,
                }),
            });
        },
    },
});
