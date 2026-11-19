/**
 * @sw-package inventory
 */
import useSnackbar from 'src/app/composables/use-snackbar';
import type EntityFactory from 'src/core/data/entity-factory.data';
import useNotification from 'src/app/composables/use-notification';
import * as helper from './document-form.helper';
import documentColumns, { allowedExtensions, maxFileSize } from './document-form.config';
import template from './sw-product-document-form.html.twig';
import './sw-product-document-form.scss';

type DocumentGrid = { resetSelection: () => void };
type DocumentUploadResult = { targetId: EntityKey<'media'>; successAmount?: number; totalAmount?: number };
type DocumentInheritance = { isInherited: boolean; forceInheritanceRemove: boolean; restoreInheritance: () => void };

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
        'entityFactory',
        'acl',
        'fileValidationService',
        'mediaDefaultFolderService',
    ],

    props: {
        disabled: { type: Boolean, required: false, default: false },
    },

    data() {
        return {
            page: 1,
            limit: 10,
            showUploadModal: false,
            showMediaModal: false,
            showRestoreInheritanceModal: false,
            mediaFolderId: undefined as string | null | undefined,
            editingDocument: null as Entity<'product_document'> | null,
            selectedDocuments: [] as Entity<'product_document'>[],
            documentsToRemove: [] as Entity<'product_document'>[],
            removeFromVariants: false,
            isRemovingDocuments: false,
            documentRemovalSession: 0,
            documentTitle: '',
            documentEditSession: 0,
            replacementMedia: null as Entity<'media'> | null,
            isDocumentFileRemoved: false,
            applyToVariants: false,
            isApplyingDocumentEdit: false,
            hasSubmittedDocumentEdit: false,
            allowedExtensions: [...allowedExtensions],
            maxFileSize,
        };
    },

    computed: {
        product(): Entity<'product'> {
            return Shopware.Store.get('swProductDetail').product;
        },

        parentProduct(): Entity<'product'> {
            return Shopware.Store.get('swProductDetail').parentProduct;
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        documentRepository() {
            return this.repositoryFactory.create('product_document');
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        languageId() {
            return Shopware.Store.get('context').api.languageId;
        },

        isSystemDefaultLanguage(): boolean {
            return Shopware.Store.get('context').isSystemDefaultLanguage;
        },

        documentContext() {
            return { ...Shopware.Context.api, language: Shopware.Store.get('swProductDetail').documentLanguage };
        },

        documents(): EntityCollection<'product_document'> {
            return this.product.documents as EntityCollection<'product_document'>;
        },

        pageOffset(): number {
            return (this.page - 1) * this.limit;
        },

        uploadTag(): string {
            return `product-documents-${this.product.id}-${this.languageId}`;
        },

        canEdit(): boolean {
            const isLanguageReady = this.isSystemDefaultLanguage || this.documentContext.language?.id === this.languageId;
            return !this.disabled && this.acl.can('product.editor') && isLanguageReady;
        },

        canAddDocuments(): boolean {
            return this.canEdit && this.isSystemDefaultLanguage;
        },

        contentLanguageName(): string {
            return helper.getDocumentContentLanguageName(
                this.documentContext.language,
                Shopware.Store.get('session').currentLocale,
            );
        },

        documentTitlePlaceholder(): string {
            const document = this.editingDocument;
            return !document || this.isSystemDefaultLanguage
                ? ''
                : helper.getDocumentTitlePlaceholder(document, this.documentContext, this.getMedia(document));
        },

        editingMedia(): Entity<'media'> | undefined {
            const document = this.editingDocument;
            const showSaved = document && !this.isDocumentFileRemoved && (this.isSystemDefaultLanguage || document.mediaId);
            return this.replacementMedia || (showSaved ? this.getMedia(document) : undefined);
        },

        canApplyDocumentEdit(): boolean {
            const hasRequiredFields = !!this.documentTitle.trim() && !!this.editingMedia;
            return this.canEdit && !this.isApplyingDocumentEdit && (!this.isSystemDefaultLanguage || hasRequiredFields);
        },

        documentTitleError(): { code: string; detail: string } | null {
            return this.isSystemDefaultLanguage && this.hasSubmittedDocumentEdit && !this.documentTitle.trim()
                ? { code: 'PRODUCT_DOCUMENT_NAME_REQUIRED', detail: this.$t('sw-product.documentForm.errorNameRequired') }
                : null;
        },

        documentFileError(): { code: string; detail: string } | null {
            return this.isSystemDefaultLanguage && this.hasSubmittedDocumentEdit && !this.editingMedia
                ? { code: 'PRODUCT_DOCUMENT_FILE_REQUIRED', detail: this.$t('sw-product.documentForm.errorFileRequired') }
                : null;
        },

        hasVariants(): boolean {
            return !this.product.parentId && !!this.product.childCount;
        },

        documentColumns() {
            return documentColumns.map(({ label, ...column }) => ({
                ...column,
                label: label === '#' ? label : this.$t(label),
            }));
        },

        uploadHint(): string {
            return this.$t('sw-product.documentForm.uploadHint', helper.getDocumentUploadHintParams(this));
        },

        allowedMimeTypesByExtension(): Record<string, string[]> {
            return helper.getDocumentMimeTypesByExtension(
                this.fileValidationService.extensionByType,
                this.allowedExtensions,
            );
        },

        fileAccept(): string {
            return [...new Set(Object.values(this.allowedMimeTypesByExtension).flat())].join(',');
        },
    },

    watch: {
        'documents.length'() {
            const lastPage = Math.max(1, Math.ceil(this.documents.length / this.limit));
            this.page = Math.min(this.page, lastPage);
        },
        product() {
            this.page = 1;
            this.showUploadModal = false;
            this.onCloseRestoreInheritanceModal();
            this.closeDocumentEditor();
            this.closeRemoveDocumentModal();
            this.selectedDocuments = [];
        },
    },

    async created() {
        await this.createdComponent();
    },

    beforeUnmount() {
        this.closeDocumentEditor();
        this.closeRemoveDocumentModal();
    },

    methods: {
        async createdComponent() {
            try {
                this.mediaFolderId = await this.mediaDefaultFolderService.getDefaultFolderId('product_document');
            } catch {
                this.mediaFolderId = null;
            }
        },

        async loadDocumentVariants(
            mediaId: string | string[],
            apiContext: typeof Shopware.Context.api,
        ): Promise<Entity<'product'>[]> {
            const { Criteria } = Shopware.Data;
            const productId = this.product.id;
            const variants: Entity<'product'>[] = [];
            let page = 1;
            let result: EntityCollection<'product'>;
            do {
                const criteria = new Criteria(page, 100);
                criteria.setTotalCountMode(0);
                criteria.addSorting(Criteria.sort('id'));
                criteria.addFilter(Criteria.equals('parentId', productId));
                criteria.addFilter(
                    Array.isArray(mediaId)
                        ? Criteria.equalsAny('documents.mediaId', mediaId)
                        : Criteria.equals('documents.mediaId', mediaId),
                );
                criteria.addAssociation('documents.translations.media');
                criteria.getAssociation('documents').addSorting(Criteria.sort('position', 'ASC'));
                result = await this.productRepository.search(criteria, apiContext);
                variants.push(...result);
                page += 1;
            } while (result.length === 100);
            return variants;
        },

        canChangeDocuments(): boolean {
            const inheritance = this.$refs.documentInheritance as DocumentInheritance | undefined;
            return this.canEdit && inheritance?.isInherited === false;
        },

        isMissingLanguageVersion(document: Entity<'product_document'>): boolean {
            return !this.isSystemDefaultLanguage && !document.title && !document.mediaId;
        },

        getMedia(document: Entity<'product_document'> | null): Entity<'media'> | undefined {
            return helper.resolveDocumentMedia(document, (id) =>
                Shopware.Store.get('swProductDetail').getProductDocumentMedia(id),
            );
        },

        getTitle(document: Entity<'product_document'>): string {
            return document.title || document.translated?.title || this.getMedia(document)?.fileName || '';
        },

        getFileName(document: Entity<'product_document'>): string {
            return helper.getDocumentFileName(this.getMedia(document));
        },

        getFileSize(document: Entity<'product_document'>): string {
            return helper.getDocumentFileSize(this.getMedia(document));
        },

        getEditActionLabel(document: Entity<'product_document'>): string {
            if (this.isMissingLanguageVersion(document)) {
                return this.$t('sw-product.documentForm.addLanguageVersion', { language: this.contentLanguageName });
            }

            return this.$t('global.default.edit');
        },

        getRemoveActionLabel(): string {
            return this.isSystemDefaultLanguage
                ? this.$t('global.default.remove')
                : this.$t('sw-product.documentForm.removeLanguageVersion', { language: this.contentLanguageName });
        },

        getPaginatedDocuments(documents: EntityCollection<'product_document'>): Entity<'product_document'>[] {
            return documents.slice(this.pageOffset, this.pageOffset + this.limit);
        },

        onPageChange({ page, limit }: { page: number; limit: number }): void {
            this.page = page;
            this.limit = limit;
        },

        getDocumentSelectionFilter(isInherited: boolean): (document: Entity<'product_document'>) => boolean {
            return (document) => this.canEdit && !isInherited && !this.isMissingLanguageVersion(document);
        },

        onDocumentSelectionChange(selection: Record<string, Entity<'product_document'>>): void {
            this.selectedDocuments = Object.values(selection);
        },

        canMoveDocument(document: Entity<'product_document'>, offset: number): boolean {
            return helper.canMoveDocument(this.documents, document, offset);
        },

        moveDocument(document: Entity<'product_document'>, offset: number): void {
            if (!this.canChangeDocuments() || !this.canMoveDocument(document, offset)) {
                return;
            }

            helper.moveDocument(this.documents, document, offset);
        },

        async onUploadFinished(
            { targetId, successAmount = 1, totalAmount = 1 }: DocumentUploadResult,
            editingDocument: Entity<'product_document'> | null = null,
        ): Promise<void> {
            const product = this.product;
            const languageId = this.languageId;
            const session = this.documentEditSession;

            const isCurrentUpload = () =>
                product === this.product &&
                languageId === this.languageId &&
                this.canChangeDocuments() &&
                (!editingDocument || (session === this.documentEditSession && editingDocument === this.editingDocument));

            try {
                const media = await this.mediaRepository.get(targetId, Shopware.Context.api);
                if (isCurrentUpload() && media) {
                    const documentCount = this.documents.length;
                    this.addDocuments([media], editingDocument);
                    if (!editingDocument && this.documents.length > documentCount && successAmount === totalAmount) {
                        this.showUploadModal = false;
                    }
                }
            } catch {
                if (isCurrentUpload()) {
                    useNotification().createNotificationError({
                        message: this.$t('sw-product.documentForm.errorLoadMedia'),
                    });
                }
            }
        },

        addDocuments(mediaItems: Entity<'media'>[], editingDocument: Entity<'product_document'> | null = null): void {
            if (!this.canChangeDocuments() || (!editingDocument && !this.isSystemDefaultLanguage)) {
                return;
            }

            for (const media of mediaItems) {
                const validationError = this.getDocumentValidationError(media, editingDocument);
                if (validationError !== null) {
                    useNotification().createNotificationError({ message: validationError });
                    continue;
                }

                if (editingDocument) {
                    this.replacementMedia = media;
                    this.isDocumentFileRemoved = false;
                    continue;
                }

                const document = helper.createProductDocument(media, this.product, this.documentRepository);
                Shopware.Store.get('swProductDetail').setProductDocumentMedia(media);
                this.documents.add(document);
            }
            this.showMediaModal = false;
        },

        getDocumentValidationError(
            media: Entity<'media'>,
            editingDocument: Entity<'product_document'> | null,
        ): string | null {
            const error = helper.getDocumentValidationError(media, editingDocument, this);
            return error === 'uploadHint' ? this.uploadHint : error ? this.$t(error) : null;
        },

        openDocumentEditor(document: Entity<'product_document'>): void {
            if (!this.canChangeDocuments()) {
                return;
            }

            this.documentEditSession += 1;
            this.editingDocument = document;
            this.documentTitle = this.isSystemDefaultLanguage ? this.getTitle(document) : document.title || '';
            this.replacementMedia = null;
            this.isDocumentFileRemoved = false;
            this.applyToVariants = this.hasVariants;
            this.hasSubmittedDocumentEdit = false;
        },

        removeEditingFile(): void {
            this.replacementMedia = null;
            this.isDocumentFileRemoved = true;
        },

        async applyDocumentEdit(): Promise<void> {
            this.hasSubmittedDocumentEdit = true;
            if (!this.canChangeDocuments() || !this.editingDocument || !this.canApplyDocumentEdit) {
                return;
            }

            const product = this.product;
            const document = this.editingDocument;
            const session = this.documentEditSession;
            const replacementMedia = this.replacementMedia;
            const context = { ...this.documentContext, inheritance: false };
            const isCurrentEdit = () =>
                session === this.documentEditSession &&
                product === this.product &&
                document === this.editingDocument &&
                context.languageId === this.languageId;
            const originalMediaId = document.mediaId || document.translated?.mediaId;
            const { changes, variantChanges } = helper.getDocumentEditChanges(
                document,
                this,
                this.getTitle(document),
                context,
            );
            const matchingMediaId = this.applyToVariants && this.hasVariants ? originalMediaId : null;
            this.isApplyingDocumentEdit = true;

            try {
                if (replacementMedia) {
                    Shopware.Store.get('swProductDetail').setProductDocumentMedia(replacementMedia);
                }
                let updatedVariantIds: string[] = [];
                if (matchingMediaId) {
                    const variants = await this.loadDocumentVariants(matchingMediaId, context);
                    if (!isCurrentEdit()) {
                        return;
                    }
                    updatedVariantIds = helper.stageVariantDocumentChanges(product, variants, document, {
                        changes: variantChanges,
                        context: this.documentContext,
                        getMedia: (item) => this.getMedia(item),
                    });
                }
                helper.applyDocumentChanges(document, changes, this.documentContext, (item) => this.getMedia(item));
                if (matchingMediaId) {
                    useSnackbar().addSnackbar({
                        variant: 'success',
                        message: this.$t('sw-product.documentForm.applySuccess', {
                            name: this.getTitle(document),
                            count: updatedVariantIds.length,
                        }),
                    });
                }
                this.closeDocumentEditor();
            } catch {
                if (isCurrentEdit()) {
                    this.isApplyingDocumentEdit = false;
                    useNotification().createNotificationError({
                        message: this.$t('sw-product.documentForm.errorLoadVariants'),
                    });
                }
            }
        },

        closeDocumentEditor(): void {
            this.documentEditSession += 1;
            this.isApplyingDocumentEdit = false;
            this.editingDocument = null;
            this.replacementMedia = null;
            this.showMediaModal = false;
        },

        openRemoveDocumentModal(document: Entity<'product_document'> | Entity<'product_document'>[]): void {
            if (!this.canChangeDocuments()) {
                return;
            }

            const documents = Array.isArray(document) ? document : [document];
            this.documentsToRemove = documents.filter((item) => !this.isMissingLanguageVersion(item));
            this.removeFromVariants = this.hasVariants;
            this.documentRemovalSession += 1;
        },

        async removeDocuments(): Promise<void> {
            if (!this.canChangeDocuments() || !this.documentsToRemove.length || this.isRemovingDocuments) {
                return;
            }

            const product = this.product;
            const documents = this.documentsToRemove;
            const removalSession = this.documentRemovalSession;
            const context = { ...this.documentContext, inheritance: false };
            const isCurrentRemoval = () =>
                removalSession === this.documentRemovalSession &&
                product === this.product &&
                documents === this.documentsToRemove &&
                context.languageId === this.languageId;
            const mediaIds = this.removeFromVariants && this.hasVariants ? helper.getDocumentMediaIds(documents) : [];
            this.isRemovingDocuments = true;
            try {
                const variants = mediaIds.length ? await this.loadDocumentVariants(mediaIds, context) : [];
                if (!isCurrentRemoval()) {
                    return;
                }
                const name = this.getTitle(documents[0]);
                const removedVariantIds = helper.removeDocuments(product, documents, variants, {
                    context: this.documentContext,
                    getMedia: (item) => this.getMedia(item),
                    isSystemDefaultLanguage: this.isSystemDefaultLanguage,
                    removeFromVariants: mediaIds.length > 0,
                });
                const { key, params } = helper.getDocumentRemovalMessage(documents.length, this.isSystemDefaultLanguage, {
                    count: removedVariantIds.size,
                    language: this.contentLanguageName,
                    name,
                });
                useSnackbar().addSnackbar({ variant: 'success', message: this.$t(key, params) });
                (this.$refs.documentGrid as DocumentGrid | undefined)?.resetSelection();
                this.selectedDocuments = [];
                this.closeRemoveDocumentModal();
            } catch {
                if (isCurrentRemoval()) {
                    this.isRemovingDocuments = false;
                    useNotification().createNotificationError({
                        message: this.$t('sw-product.documentForm.errorLoadVariants'),
                    });
                }
            }
        },

        closeRemoveDocumentModal(): void {
            this.documentRemovalSession += 1;
            this.documentsToRemove = [];
            this.isRemovingDocuments = false;
        },

        removeDocumentInheritance(
            inheritedDocuments: EntityCollection<'product_document'>,
        ): EntityCollection<'product_document'> {
            if (!this.canEdit) {
                return this.documents;
            }

            (this.$refs.documentInheritance as DocumentInheritance).forceInheritanceRemove = true;
            helper
                .copyInheritedDocuments(
                    inheritedDocuments,
                    this.product.id,
                    this.documentRepository,
                    this.entityFactory as EntityFactory,
                )
                .forEach((document) => this.documents.add(document));

            return this.documents;
        },

        onShowRestoreInheritanceModal(): void {
            this.showRestoreInheritanceModal = true;
        },

        onConfirmRestoreInheritance(): void {
            this.onCloseRestoreInheritanceModal();
            (this.$refs.documentGrid as DocumentGrid | undefined)?.resetSelection();
            this.selectedDocuments = [];
            (this.$refs.documentInheritance as DocumentInheritance).restoreInheritance();
            useSnackbar().addSnackbar({
                variant: 'success',
                message: this.$t('sw-product.documentForm.restoreInheritanceSuccess'),
            });
        },

        onCloseRestoreInheritanceModal(): void {
            this.showRestoreInheritanceModal = false;
        },
    },
});
