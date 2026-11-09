/**
 * @sw-package inventory
 */

import useNotification from 'src/app/composables/use-notification';
import documentColumns, { allowedExtensions, maxFileSize, maxDocuments } from './document-form.config';

import { getHintParams, getFileAccept, isInvalidDocumentFile } from './document-upload.helper';
import template from './sw-product-document-form.html.twig';
import './sw-product-document-form.scss';

type ProductDocument = Entity<'product_document'>;
type DocumentInheritance = { isInherited: boolean; forceInheritanceRemove: boolean };

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'fileValidationService',
        'mediaService',
    ],

    emits: ['change-language'],

    props: {
        disabled: {
            type: Boolean,
            default: false,
        },
    },

    data() {
        return {
            showUploadModal: false,

            showMediaModal: false,

            mediaFolderId: undefined as string | null | undefined,

            documentEditSession: 0,

            allowedExtensions: [...allowedExtensions],

            maxFileSize,

            maxDocuments,
        };
    },

    computed: {
        product: () => Shopware.Store.get('swProductDetail').product,

        parentProduct: () => Shopware.Store.get('swProductDetail').parentProduct,

        documents(): EntityCollection<'product_document'> {
            return this.product.documents as EntityCollection<'product_document'>;
        },

        documentRepository() {
            return this.repositoryFactory.create('product_document');
        },

        uploadTag(): string {
            return `product-documents-${this.product.id}-${Shopware.Context.api.languageId}`;
        },

        canEdit(): boolean {
            return !this.disabled && this.acl.can('product.editor');
        },

        canAddDocuments(): boolean {
            return this.canEdit && this.isDefaultLanguage && this.documents.length < this.maxDocuments;
        },

        isDefaultLanguage: () => Shopware.Context.api.languageId === Shopware.Context.api.systemLanguageId,

        documentColumns() {
            return documentColumns.map(({ label, ...column }) => ({
                ...column,
                label: label === '#' ? label : this.$t(label),
            }));
        },

        uploadHint(): string {
            return this.$t('sw-product.documentForm.uploadHint', getHintParams(this.allowedExtensions, this.maxFileSize));
        },

        fileAccept(): string {
            return getFileAccept(this.fileValidationService.extensionByType, this.allowedExtensions);
        },
    },

    watch: {
        product() {
            this.showUploadModal = false;
            this.showMediaModal = false;
            this.documentEditSession += 1;
        },
    },

    async created() {
        const folder = this.mediaService.getDefaultFolderId('product_document');
        this.mediaFolderId = await folder.catch(() => /* The picker can open at the media root. */ null);
    },

    beforeUnmount() {
        this.documentEditSession += 1;
    },

    methods: {
        canChangeDocuments(): boolean {
            const inheritance = this.$refs.documentInheritance as DocumentInheritance | undefined;
            return this.canEdit && inheritance?.isInherited === false;
        },

        getMedia(document: ProductDocument | null): Entity<'media'> | undefined {
            const mediaId = document?.mediaId || document?.translated?.mediaId;

            const files = Shopware.Store.get('swProductDetail').documentMedia.get(this.product);
            return mediaId
                ? files?.[mediaId] || document?.translations?.find((item) => item.mediaId === mediaId)?.media
                : undefined;
        },

        getTitle(document: ProductDocument): string {
            return document.title || document.translated?.title || this.getMedia(document)?.fileName || '';
        },

        getFileSize(document: ProductDocument): string {
            const size = this.getMedia(document)?.fileSize;
            return size === undefined ? '' : Shopware.Utils.format.fileSize(size);
        },

        getFileName(document: ProductDocument): string {
            const media = this.getMedia(document);
            return media?.fileName ? [media.fileName, media.fileExtension].filter(Boolean).join('.') : '';
        },

        async successfulUpload({ targetId }: { targetId: EntityKey<'media'> }): Promise<void> {
            const product = this.product;
            const languageId = Shopware.Context.api.languageId;
            const session = this.documentEditSession;
            const isCurrentUpload = () =>
                product === this.product &&
                languageId === Shopware.Context.api.languageId &&
                session === this.documentEditSession &&
                this.canChangeDocuments();
            try {
                const media = await this.repositoryFactory.create('media').get(targetId, Shopware.Context.api);
                if (isCurrentUpload() && media) this.addDocuments([media]);
            } catch {
                if (isCurrentUpload())
                    useNotification().createNotificationError({
                        message: this.$t('sw-product.documentForm.errorLoadMedia'),
                    });
            }
        },

        addDocuments(mediaItems: Entity<'media'>[]): void {
            if (!this.canChangeDocuments() || !this.isDefaultLanguage) {
                return;
            }

            for (const media of mediaItems) {
                if (media.private) {
                    useNotification().createNotificationError({
                        message: this.$t('sw-product.documentForm.errorPrivateMedia'),
                    });
                    continue;
                }

                if (isInvalidDocumentFile(media, this.allowedExtensions, this.maxFileSize)) {
                    useNotification().createNotificationError({ message: this.uploadHint });
                    continue;
                }

                if (this.documents.some((document) => (document.mediaId || document.translated?.mediaId) === media.id)) {
                    useNotification().createNotificationError({
                        message: this.$t('sw-product.documentForm.errorDocumentDuplicated'),
                    });
                    continue;
                }

                if (this.documents.length >= this.maxDocuments) {
                    useNotification().createNotificationError({
                        message: this.$t('sw-product.documentForm.errorDocumentLimit', { count: this.maxDocuments }),
                    });
                    break;
                }

                const document = this.documentRepository.create(Shopware.Context.api);
                document.productId = this.product.id;
                document.mediaId = media.id;
                document.title = media.fileName;
                document.position = this.documents.length;
                Shopware.Store.get('swProductDetail').cacheDocumentMedia(media);
                Shopware.Store.get('swProductDetail').addDocument(document);
            }
            this.showMediaModal = false;
        },
    },
});
