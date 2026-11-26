/**
 * @sw-package inventory
 */
import { useId, type PropType } from 'vue';
import useNotification from 'src/app/composables/use-notification';
import type Repository from 'src/core/data/repository.data';
import type {
    DocumentChanges,
    DocumentVariantOperation,
} from 'src/module/sw-product/service/product-document-variant.service';
import { getDocumentMedia, getSavedDocumentValues } from '../product-document.helper';
import template from './sw-product-document-edit-modal.html.twig';

const { Context, Store } = Shopware;

/** @private */
export type DocumentUploadRules = {
    allowedExtensions: string[];
    allowedMimeTypesByExtension: Record<string, string[]>;
    maxFileSize: number;
    fileAccept: string;
};

/** @private */
export type DocumentEdit = {
    changes: DocumentChanges;
    media: Entity<'media'> | null;
    operation: DocumentVariantOperation | null;
    message: string | null;
};

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
        'productDocumentVariantService',
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
        uploadRules: {
            type: Object as PropType<DocumentUploadRules>,
            required: true,
        },
        uploadHint: {
            type: String,
            required: false,
            default: '',
        },
        titlePlaceholder: {
            type: String,
            required: false,
            default: '',
        },
        validateMedia: {
            type: Function as PropType<(media: Entity<'media'>, exceptDocument: Entity<'product_document'>) => boolean>,
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
    },

    emits: [
        'close',
        'apply',
    ],

    setup() {
        return { listenerId: useId() };
    },

    data() {
        const isSystemDefaultLanguage = Store.get('context').isSystemDefaultLanguage;

        return {
            title: (isSystemDefaultLanguage ? this.document.translated?.title : this.document.title) || '',
            replacementMedia: null as Entity<'media'> | null,
            isFileRemoved: false,
            applyToVariants: true,
            hasSubmitted: false,
            isApplying: false,
            showMediaModal: false,
        };
    },

    computed: {
        mediaRepository(): Repository<'media'> {
            return this.repositoryFactory.create('media');
        },

        uploadTag(): string {
            return `sw-product-document-edit-modal-${this.listenerId}`;
        },

        hasVariants(): boolean {
            const { product } = Store.get('swProductDetail');
            return !product.parentId && !!product.childCount;
        },

        isSystemDefaultLanguage(): boolean {
            return Store.get('context').isSystemDefaultLanguage;
        },

        modalTitle(): string {
            return !this.isSystemDefaultLanguage && !this.document.title && !this.document.mediaId
                ? this.$t('sw-product.documentForm.addLanguageVersion', { language: this.languageName })
                : this.$t('sw-product.documentForm.editTitle');
        },

        editingMedia(): Entity<'media'> | undefined {
            const showSavedFile = !this.isFileRemoved && (this.isSystemDefaultLanguage || this.document.mediaId);
            return this.replacementMedia || (showSavedFile ? getDocumentMedia(this.document) : undefined);
        },

        titleError(): { code: string; detail: string } | null {
            return this.isSystemDefaultLanguage && this.hasSubmitted && !this.title.trim()
                ? { code: 'PRODUCT_DOCUMENT_NAME_REQUIRED', detail: this.$t('sw-product.documentForm.errorNameRequired') }
                : null;
        },

        fileError(): { code: string; detail: string } | null {
            return this.isSystemDefaultLanguage && this.hasSubmitted && !this.editingMedia
                ? { code: 'PRODUCT_DOCUMENT_FILE_REQUIRED', detail: this.$t('sw-product.documentForm.errorFileRequired') }
                : null;
        },

        fileHint(): string {
            return this.isSystemDefaultLanguage ? this.uploadHint : this.$t('sw-product.documentForm.fileFallbackHint');
        },

        newTitle(): string | null | undefined {
            const title = this.title.trim();
            const isUnchanged = this.isSystemDefaultLanguage && title === (this.document.translated?.title ?? '').trim();
            return isUnchanged ? undefined : title || null;
        },

        newMediaId(): EntityKey<'media'> | null | undefined {
            return this.replacementMedia?.id ?? (this.isFileRemoved ? null : undefined);
        },

        changes(): DocumentChanges {
            const changes: DocumentChanges = {};
            if (this.newTitle !== undefined && this.newTitle !== (this.document.title ?? null)) {
                changes.title = this.newTitle;
            }
            if (this.newMediaId !== undefined && this.newMediaId !== (this.document.mediaId ?? null)) {
                changes.mediaId = this.newMediaId;
            }
            return changes;
        },

        hasChanges(): boolean {
            return Object.keys(this.changes).length > 0;
        },

        appliedTitle(): string {
            if (this.changes.title === undefined) {
                return this.document.translated?.title ?? '';
            }

            return this.changes.title ?? this.titlePlaceholder;
        },

        variantOperation(): DocumentVariantOperation | null {
            const queuedOperation = Store.get('swProductDetail').documentVariantOperations[this.document.id];
            const { mediaId, title } = queuedOperation ?? getSavedDocumentValues(this.document);
            const queuedChanges = queuedOperation?.type === 'update' ? queuedOperation.changes : {};
            return this.applyToVariants && this.hasVariants && this.hasChanges && mediaId
                ? { type: 'update', mediaId, title: title || null, changes: { ...queuedChanges, ...this.changes } }
                : null;
        },

        isDisabled(): boolean {
            return !this.canEdit || this.isApplying;
        },
    },

    methods: {
        async onUploadFinished({ targetId }: { targetId: EntityKey<'media'> }): Promise<void> {
            try {
                const media = await this.mediaRepository.get(targetId, Context.api);
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
                .filter((media) => this.validateMedia(media, this.document))
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

        async apply(): Promise<void> {
            this.hasSubmitted = true;
            if (this.isDisabled || (this.isSystemDefaultLanguage && (this.titleError || this.fileError))) {
                return;
            }

            const { changes, replacementMedia: media, variantOperation: operation, appliedTitle } = this;
            const { product } = Store.get('swProductDetail');
            let variantCount = 0;

            if (operation) {
                this.isApplying = true;
                try {
                    variantCount = (await this.productDocumentVariantService.sync(product.id, [operation], true)).length;
                } catch {
                    this.isApplying = false;
                    useNotification().createNotificationError({
                        message: this.$t('sw-product.documentForm.errorLoadVariants'),
                    });
                    return;
                }
            }

            if (Store.get('swProductDetail').product !== product) {
                return;
            }

            const message = operation
                ? this.$t('sw-product.documentForm.applySuccess', { name: appliedTitle, count: variantCount })
                : null;
            this.$emit('apply', { changes, media, operation, message } satisfies DocumentEdit);
        },
    },
});
