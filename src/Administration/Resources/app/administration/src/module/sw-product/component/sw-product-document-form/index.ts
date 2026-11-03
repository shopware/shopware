/**
 * @sw-package inventory
 */
import useProductDocumentVariants from '../../composables/use-product-document-variants';
import useNotification from 'src/app/composables/use-notification';
import template from './sw-product-document-form.html.twig';
import './sw-product-document-form.scss';

type ProductDocument = Entity<'product_document'>;

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'mediaService',
    ],

    props: {
        isInherited: {
            type: Boolean,
            default: false,
        },
    },

    setup() {
        return useProductDocumentVariants();
    },

    data() {
        return {
            showUploadModal: false,
            showMediaModal: false,
            editingDocument: null as ProductDocument | null,
            removingDocument: null as ProductDocument | null,
            removeFromVariants: false,
            isRemovingDocument: false,
            removeModalVersion: 0,
            documentTitle: '',
            replacementMedia: null as Entity<'media'> | null,
            documentFileRemoved: false,
            applyToVariants: false,
            isSavingDocument: false,
            documentValidationSubmitted: false,
            uploadedMedia: {} as Record<string, Entity<'media'>>,
            // Use the epic defaults until the product-document configuration is exposed by the backend.
            allowedExtensions: [
                'pdf',
                'docx',
                'jpg',
                'jpeg',
                'png',
            ],
            maxFileSize: 20 * 1024 * 1024,
        };
    },

    computed: {
        product() {
            return Shopware.Store.get('swProductDetail').product;
        },

        parentProduct() {
            return Shopware.Store.get('swProductDetail').parentProduct;
        },

        documents(): EntityCollection<'product_document'> {
            return (
                this.isInherited ? this.parentProduct.documents : this.product.documents
            ) as EntityCollection<'product_document'>;
        },

        documentRepository() {
            return this.repositoryFactory.create('product_document');
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        uploadTag(): string {
            return `product-documents-${this.product.id}-${Shopware.Context.api.languageId}`;
        },

        canEdit(): boolean {
            return this.acl.can('product.editor');
        },

        isDefaultLanguage(): boolean {
            return Shopware.Context.api.languageId === Shopware.Context.api.systemLanguageId;
        },

        editingMedia(): Entity<'media'> | undefined {
            return (
                this.replacementMedia ||
                (!this.documentFileRemoved && this.editingDocument ? this.getMedia(this.editingDocument) : undefined)
            );
        },

        canSaveDocument(): boolean {
            return this.canEdit && !this.isSavingDocument && !!this.documentTitle.trim() && !!this.editingMedia;
        },

        documentNameError(): { code: string; detail: string } | null {
            return this.documentValidationSubmitted && !this.documentTitle.trim()
                ? { code: 'PRODUCT_DOCUMENT_NAME_REQUIRED', detail: this.$t('sw-product.documentForm.errorNameRequired') }
                : null;
        },

        documentFileError(): { code: string; detail: string } | null {
            return this.documentValidationSubmitted && !this.editingMedia
                ? { code: 'PRODUCT_DOCUMENT_FILE_REQUIRED', detail: this.$t('sw-product.documentForm.errorFileRequired') }
                : null;
        },

        hasVariants(): boolean {
            return !this.product.parentId && !!this.product.childCount;
        },

        documentColumns() {
            return [
                { property: 'number', label: '#', width: '56px', align: 'center' },
                { property: 'position', label: this.$t('sw-product.documentForm.columnPosition'), width: '110px' },
                { property: 'title', label: this.$t('sw-product.documentForm.columnTitle'), primary: true, width: '300px' },
                { property: 'fileName', label: this.$t('sw-product.documentForm.columnFileName'), width: '190px' },
                { property: 'fileType', label: this.$t('sw-product.documentForm.columnFileType'), width: '104px' },
                {
                    property: 'fileSize',
                    label: this.$t('sw-product.documentForm.columnFileSize'),
                    width: '100px',
                    align: 'right',
                },
            ].map((column) => ({ ...column, sortable: false, resizable: false }));
        },

        uploadHint(): string {
            const extensions = this.allowedExtensions.filter((extension) => extension !== 'jpeg');
            return this.$t('sw-product.documentForm.uploadHint', {
                extensions: extensions.slice(0, -1).join(', ').toUpperCase(),
                lastExtension: extensions.at(-1)?.toUpperCase(),
                size: this.maxFileSize / (1024 * 1024),
            });
        },

        fileAccept(): string {
            return this.allowedExtensions.map((extension) => `.${extension}`).join(',');
        },
    },

    watch: {
        product() {
            this.showUploadModal = false;
            this.showMediaModal = false;
            this.editingDocument = null;
            this.removingDocument = null;
            this.uploadedMedia = {};
        },
    },

    methods: {
        canChangeDocuments(): boolean {
            return this.canEdit && !this.isInherited;
        },

        getMedia(document: ProductDocument): Entity<'media'> | undefined {
            const mediaId = document.mediaId || document.translated?.mediaId;

            if (!mediaId) {
                return undefined;
            }

            return this.uploadedMedia[mediaId] || document.translations?.find((item) => item.mediaId === mediaId)?.media;
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

        async downloadDocument(document: ProductDocument): Promise<void> {
            const media = this.getMedia(document);
            if (!this.acl.can('media.viewer') || !media) {
                return;
            }

            try {
                const downloadConfig = (await this.mediaService.prepareDownloadMedia(media.id)) as
                    | { type: 'external'; url: string }
                    | { type: 'blob' };
                if (downloadConfig.type === 'external') {
                    this.triggerDownload(downloadConfig.url);
                    return;
                }

                const data = (await this.mediaService.downloadMedia(media.id)) as Blob;
                const url = URL.createObjectURL(data);
                try {
                    this.triggerDownload(url, this.getFileName(document));
                } finally {
                    URL.revokeObjectURL(url);
                }
            } catch {
                useNotification().createNotificationError({
                    message: this.$t('global.sw-media-media-item.notification.downloadError.message'),
                });
            }
        },

        triggerDownload(url: string, fileName?: string): void {
            const link = document.createElement('a');
            link.href = url;
            if (fileName) {
                link.download = fileName;
            } else {
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
            }
            link.dispatchEvent(new MouseEvent('click'));
            link.remove();
        },

        async successfulUpload(
            { targetId }: { targetId: EntityKey<'media'> },
            editingDocument: ProductDocument | null = null,
        ): Promise<void> {
            const product = this.product;
            const languageId = Shopware.Context.api.languageId;

            try {
                const media = await this.mediaRepository.get(targetId, Shopware.Context.api);
                if (product !== this.product || languageId !== Shopware.Context.api.languageId || !media) {
                    return;
                }

                if (editingDocument && editingDocument !== this.editingDocument) {
                    return;
                }

                this.addDocuments([media], editingDocument);
            } catch {
                useNotification().createNotificationError({ message: this.$t('sw-product.documentForm.errorLoadMedia') });
            }
        },

        addDocuments(mediaItems: Entity<'media'>[], editingDocument: ProductDocument | null = null): void {
            if (!this.canChangeDocuments() || (!editingDocument && !this.isDefaultLanguage)) {
                return;
            }

            mediaItems.forEach((media) => {
                if (media.private) {
                    useNotification().createNotificationError({
                        message: this.$t('sw-product.documentForm.errorPrivateMedia'),
                    });
                    return;
                }

                if (
                    !this.allowedExtensions.includes(media.fileExtension?.toLowerCase() ?? '') ||
                    typeof media.fileSize !== 'number' ||
                    media.fileSize > this.maxFileSize
                ) {
                    useNotification().createNotificationError({ message: this.uploadHint });
                    return;
                }

                if (
                    this.documents.some(
                        (document) =>
                            document !== editingDocument && (document.mediaId || document.translated?.mediaId) === media.id,
                    )
                ) {
                    useNotification().createNotificationError({
                        message: this.$t('sw-product.documentForm.errorDocumentDuplicated'),
                    });
                    return;
                }

                if (editingDocument) {
                    this.replacementMedia = media;
                    this.documentFileRemoved = false;
                    return;
                }

                const document = this.documentRepository.create(Shopware.Context.api);
                document.productId = this.product.id;
                document.mediaId = media.id;
                document.title = media.fileName;
                document.position = this.documents.length;
                this.uploadedMedia[media.id] = media;
                this.documents.add(document);
            });
            this.showMediaModal = false;
        },

        moveDocument(document: ProductDocument, offset: number): void {
            if (!this.canChangeDocuments()) {
                return;
            }

            const oldIndex = this.documents.indexOf(document);
            const newIndex = oldIndex + offset;
            if (oldIndex < 0 || newIndex < 0 || newIndex >= this.documents.length) {
                return;
            }

            this.documents.moveItem(oldIndex, newIndex);
            this.updatePositions();
        },

        editDocument(document: ProductDocument): void {
            if (!this.canChangeDocuments()) {
                return;
            }

            this.editingDocument = document;
            this.documentTitle = this.getTitle(document);
            this.replacementMedia = null;
            this.documentFileRemoved = false;
            this.applyToVariants = false;
            this.documentValidationSubmitted = false;
        },

        removeEditingFile(): void {
            this.replacementMedia = null;
            this.documentFileRemoved = true;
        },

        async saveDocument(): Promise<void> {
            this.documentValidationSubmitted = true;
            const media = this.editingMedia;
            if (!this.canChangeDocuments() || !this.editingDocument || !this.canSaveDocument || !media) {
                return;
            }

            const product = this.product;
            const document = this.editingDocument;
            const context = { ...Shopware.Context.api, languageId: Shopware.Context.api.languageId, inheritance: false };
            const originalMediaId = document.mediaId || document.translated?.mediaId;
            const title = this.documentTitle.trim();
            const matchingMediaId = this.applyToVariants && this.hasVariants ? originalMediaId : null;
            this.isSavingDocument = true;

            try {
                const variants = matchingMediaId
                    ? await this.loadDocumentVariants(product.id, matchingMediaId, context)
                    : [];
                if (
                    product !== this.product ||
                    document !== this.editingDocument ||
                    context.languageId !== Shopware.Context.api.languageId
                ) {
                    return;
                }

                const updatedVariantIds = matchingMediaId
                    ? this.stageVariantDocumentChanges(variants, matchingMediaId, media.id, title)
                    : [];
                document.mediaId = media.id;
                this.uploadedMedia[media.id] = media;
                document.title = title;
                Shopware.Store.get('swProductDetail').queueDocumentSaveNotification(document.id, title, updatedVariantIds);
                this.editingDocument = null;
            } catch {
                useNotification().createNotificationError({ message: this.$t('sw-product.documentForm.errorLoadVariants') });
            } finally {
                this.isSavingDocument = false;
            }
        },

        confirmRemoveDocument(document: ProductDocument): void {
            if (!this.canChangeDocuments()) {
                return;
            }

            this.removingDocument = document;
            this.removeFromVariants = false;
            this.removeModalVersion += 1;
        },

        async removeDocument(): Promise<void> {
            if (!this.canChangeDocuments() || !this.removingDocument || this.isRemovingDocument) {
                return;
            }

            const product = this.product;
            const document = this.removingDocument;
            const modalVersion = this.removeModalVersion;
            const context = { ...Shopware.Context.api, languageId: Shopware.Context.api.languageId, inheritance: false };
            const mediaId =
                this.removeFromVariants && this.hasVariants ? document.mediaId || document.translated?.mediaId : null;
            this.isRemovingDocument = true;
            try {
                const variants = mediaId ? await this.loadDocumentVariants(product.id, mediaId, context) : [];
                if (
                    modalVersion !== this.removeModalVersion ||
                    product !== this.product ||
                    document !== this.removingDocument ||
                    context.languageId !== Shopware.Context.api.languageId
                ) {
                    return;
                }
                if (mediaId) {
                    this.stageVariantDocumentRemovals(variants, mediaId);
                }
                this.documents.remove(document.id);
                const store = Shopware.Store.get('swProductDetail');
                store.documentSaveNotifications = store.documentSaveNotifications.filter(
                    (entry) => entry.documentId !== document.id,
                );
                this.updatePositions();
                this.removingDocument = null;
            } catch {
                useNotification().createNotificationError({ message: this.$t('sw-product.documentForm.errorLoadVariants') });
            } finally {
                this.isRemovingDocument = false;
            }
        },

        updatePositions(): void {
            this.documents.forEach((document, index) => {
                document.position = index;
            });
        },
    },
});
