/**
 * @sw-package inventory
 */

import useNotification from 'src/app/composables/use-notification';
import documentColumns, { allowedExtensions, maxFileSize, maxDocuments } from './document-form.config';

import { getHintParams, getFileAccept, isInvalidDocumentFile } from './document-upload.helper';
import template from './sw-product-document-form.html.twig';
import './sw-product-document-form.scss';

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

    props: {
        disabled: {
            type: Boolean,
            required: false,
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
        product(): Entity<'product'> {
            return Shopware.Store.get('swProductDetail').product;
        },

        parentProduct(): Entity<'product'> {
            return Shopware.Store.get('swProductDetail').parentProduct;
        },

        languageId() {
            return Shopware.Store.get('context').api.languageId;
        },

        systemLanguageId() {
            return Shopware.Store.get('context').api.systemLanguageId;
        },

        documents(): EntityCollection<'product_document'> {
            return this.product.documents as EntityCollection<'product_document'>;
        },

        documentRepository() {
            return this.repositoryFactory.create('product_document');
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        uploadTag(): string {
            return `product-documents-${this.product.id}-${this.languageId}`;
        },

        canEdit(): boolean {
            return !this.disabled && this.acl.can('product.editor');
        },

        canAddDocuments(): boolean {
            return this.canEdit && this.isDefaultLanguage && this.documents.length < this.maxDocuments;
        },

        isDefaultLanguage(): boolean {
            return this.languageId === this.systemLanguageId;
        },

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

    created() {
        void this.createdComponent();
    },

    beforeUnmount() {
        this.documentEditSession += 1;
    },

    methods: {
        async createdComponent(): Promise<void> {
            try {
                this.mediaFolderId = await this.mediaService.getDefaultFolderId('product_document');
            } catch {
                this.mediaFolderId = null;
            }
        },

        canChangeDocuments(): boolean {
            const inheritance = this.$refs.documentInheritance as DocumentInheritance | undefined;
            return this.canEdit && inheritance?.isInherited === false;
        },

        getMedia(document: Entity<'product_document'> | null): Entity<'media'> | undefined {
            const mediaId = document?.mediaId || document?.translated?.mediaId;

            return mediaId
                ? Shopware.Store.get('swProductDetail').getDocumentMedia(mediaId) ||
                      document?.translations?.find((item) => item.mediaId === mediaId)?.media
                : undefined;
        },

        getTitle(document: Entity<'product_document'>): string {
            return document.translated?.title || document.title || this.getMedia(document)?.fileName || '';
        },

        getFileSize(document: Entity<'product_document'>): string {
            const size = this.getMedia(document)?.fileSize;
            return size === undefined ? '' : Shopware.Utils.format.fileSize(size);
        },

        getFileName(document: Entity<'product_document'>): string {
            const media = this.getMedia(document);
            return media?.fileName ? [media.fileName, media.fileExtension].filter(Boolean).join('.') : '';
        },

        async successfulUpload({ targetId }: { targetId: EntityKey<'media'> }): Promise<void> {
            const product = this.product;
            const languageId = this.languageId;
            const session = this.documentEditSession;
            const isCurrentUpload = () =>
                product === this.product &&
                languageId === this.languageId &&
                session === this.documentEditSession &&
                this.canChangeDocuments();
            try {
                const media = await this.mediaRepository.get(targetId, Shopware.Context.api);
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
                this.documents.add(document);
            }
            this.showMediaModal = false;
        },
    },
});
