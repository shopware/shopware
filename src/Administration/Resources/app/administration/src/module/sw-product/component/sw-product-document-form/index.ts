/**
 * @sw-package inventory
 */
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
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        isInherited: {
            type: Boolean,
            default: false,
        },
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
            documentEditSession: 0,
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
            maxDocuments: 10,
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

        canAddDocuments(): boolean {
            return this.canChangeDocuments() && this.isDefaultLanguage && this.documents.length < this.maxDocuments;
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
            this.closeDocumentEditor();
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
            const editSession = this.documentEditSession;

            try {
                const media = await this.mediaRepository.get(targetId, Shopware.Context.api);
                if (product !== this.product || languageId !== Shopware.Context.api.languageId || !media) {
                    return;
                }

                if (
                    editingDocument &&
                    (editingDocument !== this.editingDocument || editSession !== this.documentEditSession)
                ) {
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

            for (const media of mediaItems) {
                if (media.private) {
                    useNotification().createNotificationError({
                        message: this.$t('sw-product.documentForm.errorPrivateMedia'),
                    });
                    continue;
                }

                if (
                    !this.allowedExtensions.includes(media.fileExtension?.toLowerCase() ?? '') ||
                    typeof media.fileSize !== 'number' ||
                    media.fileSize > this.maxFileSize
                ) {
                    useNotification().createNotificationError({ message: this.uploadHint });
                    continue;
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
                    continue;
                }

                if (editingDocument) {
                    this.replacementMedia = media;
                    this.documentFileRemoved = false;
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
                this.uploadedMedia[media.id] = media;
                this.documents.add(document);
            }
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

            this.documentEditSession += 1;
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
            const session = this.documentEditSession;
            const replacementMedia = this.replacementMedia;
            const context = { ...Shopware.Context.api, languageId: Shopware.Context.api.languageId, inheritance: false };
            const isCurrentEdit = () =>
                session === this.documentEditSession &&
                product === this.product &&
                document === this.editingDocument &&
                context.languageId === Shopware.Context.api.languageId;
            const originalMediaId = document.mediaId || document.translated?.mediaId;
            const title = this.documentTitle.trim();
            const matchingMediaId = this.applyToVariants && this.hasVariants ? originalMediaId : null;
            this.isSavingDocument = true;

            try {
                let updatedVariantIds: string[] = [];
                if (matchingMediaId) {
                    const variants = await this.loadDocumentVariants(product.id, matchingMediaId, context);
                    if (!isCurrentEdit()) {
                        return;
                    }
                    updatedVariantIds = this.stageVariantDocumentChanges(variants, matchingMediaId, media.id, title);
                }
                if (title !== this.getTitle(document).trim()) {
                    document.title = title;
                }
                if (replacementMedia) {
                    document.mediaId = replacementMedia.id;
                    this.uploadedMedia[replacementMedia.id] = replacementMedia;
                }
                Shopware.Store.get('swProductDetail').queueDocumentSaveNotification(document.id, title, updatedVariantIds);
                this.closeDocumentEditor();
            } catch {
                if (isCurrentEdit()) {
                    useNotification().createNotificationError({
                        message: this.$t('sw-product.documentForm.errorLoadVariants'),
                    });
                }
            } finally {
                this.isSavingDocument = false;
            }
        },

        closeDocumentEditor(): void {
            this.documentEditSession += 1;
            this.editingDocument = null;
            this.replacementMedia = null;
            this.showMediaModal = false;
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

        async loadDocumentVariants(
            productId: string,
            mediaId: string,
            context: typeof Shopware.Context.api,
        ): Promise<Entity<'product'>[]> {
            const productRepository = this.repositoryFactory.create('product');
            const { Criteria } = Shopware.Data;
            const variants: Entity<'product'>[] = [];
            let page = 1;
            let result: EntityCollection<'product'>;
            do {
                const criteria = new Criteria(page, 100);
                criteria.setTotalCountMode(0);
                criteria.addSorting(Criteria.sort('id'));
                criteria.addFilter(Criteria.equals('parentId', productId));
                criteria.addFilter(Criteria.equals('documents.mediaId', mediaId));
                criteria.getAssociation('documents').addSorting(Criteria.sort('position', 'ASC'));
                result = await productRepository.search(criteria, context);
                variants.push(...result);
                page += 1;
            } while (result.length === 100);
            return variants;
        },

        stageVariantDocumentChanges(
            variants: Entity<'product'>[],
            originalMediaId: EntityKey<'media'>,
            mediaId: EntityKey<'media'>,
            title: string,
        ): string[] {
            const product = this.product;
            const children =
                product.children || new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api);
            const candidates = new Map(variants.map((variant) => [variant.id, variant]));
            children.forEach((variant) => candidates.set(variant.id, variant));
            const updatedVariantIds: string[] = [];
            candidates.forEach((variant) => {
                let changed = false;
                variant.documents?.forEach((copy) => {
                    if (copy.productId !== variant.id || (copy.mediaId || copy.translated?.mediaId) !== originalMediaId) {
                        return;
                    }
                    if (title !== (copy.title ?? copy.translated?.title ?? '').trim()) {
                        copy.title = title;
                    }
                    if (mediaId !== (copy.mediaId || copy.translated?.mediaId)) {
                        copy.mediaId = mediaId;
                    }
                    const origin = copy.getOrigin();
                    if (origin.title !== copy.title || origin.mediaId !== copy.mediaId) {
                        changed = true;
                    }
                });
                if (changed) {
                    if (!children.has(variant.id)) {
                        children.add(variant);
                    }
                    updatedVariantIds.push(variant.id);
                }
            });
            product.children = children;
            return updatedVariantIds;
        },

        stageVariantDocumentRemovals(variants: Entity<'product'>[], mediaId: EntityKey<'media'>): void {
            const product = this.product;
            const children =
                product.children || new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api);
            const origin = product.getOrigin();
            const originChildren =
                origin.children || new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api);
            const candidates = new Map(variants.map((variant) => [variant.id, variant]));
            children.forEach((variant) => candidates.set(variant.id, variant));
            candidates.forEach((variant) => {
                const documents = variant.documents;
                const matching = documents?.filter(
                    (copy) => copy.productId === variant.id && (copy.mediaId || copy.translated?.mediaId) === mediaId,
                );
                if (!documents || !matching?.length) {
                    return;
                }
                // Register loaded children in the origin so the changeset includes nested document deletions.
                if (!variant.isNew() && !originChildren.has(variant.id)) {
                    originChildren.add(variant);
                }
                if (!children.has(variant.id)) {
                    children.add(variant);
                }
                matching.forEach((copy) => documents.remove(copy.id));
                documents.forEach((copy, index) => {
                    copy.position = index;
                });
            });
            product.children = children;
            origin.children = originChildren;
        },

        updatePositions(): void {
            this.documents.forEach((document, index) => {
                document.position = index;
            });
        },
    },
});
