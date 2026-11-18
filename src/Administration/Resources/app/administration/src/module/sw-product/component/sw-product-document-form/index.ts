/**
 * @sw-package inventory
 */
import useSnackbar from 'src/app/composables/use-snackbar';
import type EntityFactory from 'src/core/data/entity-factory.data';
import useNotification from 'src/app/composables/use-notification';
import documentColumns from './document-form.config';
import { getDocumentTranslationChanges, getDocumentEditChanges } from './document-translation.utils';
import { refreshDocumentPositions } from './document-changes.helper';
import useDocumentChanges from './use-document-changes';
import { copyInheritedDocuments } from './document-inheritance.helper';
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
        'mediaDefaultFolderService',
    ],

    props: {
        disabled: { type: Boolean, required: false, default: false },
    },

    setup() {
        return useDocumentChanges();
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
        };
    },

    computed: {
        parentProduct(): Entity<'product'> {
            return Shopware.Store.get('swProductDetail').parentProduct;
        },

        documents(): EntityCollection<'product_document'> {
            return this.product.documents as EntityCollection<'product_document'>;
        },

        pageOffset(): number {
            return (this.page - 1) * this.limit;
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

        uploadTag(): string {
            return `product-documents-${this.product.id}-${this.languageId}`;
        },

        canEdit(): boolean {
            const isLanguageReady = this.isDefaultLanguage || this.documentContext.language?.id === this.languageId;
            return !this.disabled && this.acl.can('product.editor') && isLanguageReady;
        },

        canAddDocuments(): boolean {
            return this.canEdit && this.isDefaultLanguage;
        },

        contentLanguageName(): string {
            const language = this.documentContext.language;
            const code = language?.locale?.code.split('-')[0];
            const locale = Shopware.Store.get('session').currentLocale || 'en-GB';
            const names = new Intl.DisplayNames([locale], { type: 'language' });
            return code ? names.of(code) || '' : language?.name || '';
        },

        documentTitlePlaceholder(): string {
            const document = this.editingDocument;
            if (!document || this.isDefaultLanguage) {
                return '';
            }
            const changes = getDocumentTranslationChanges(document, { title: null }, this.documentContext);
            return (changes.translated ?? document.translated)?.title || this.getMedia(document)?.fileName || '';
        },

        editingMedia(): Entity<'media'> | undefined {
            const document = this.editingDocument;
            const showSavedFile = document && !this.isDocumentFileRemoved && (this.isDefaultLanguage || document.mediaId);
            return this.replacementMedia || (showSavedFile ? this.getMedia(document) : undefined);
        },

        canApplyDocumentEdit(): boolean {
            const hasRequiredFields = !!this.documentTitle.trim() && !!this.editingMedia;
            return this.canEdit && !this.isApplyingDocumentEdit && (!this.isDefaultLanguage || hasRequiredFields);
        },

        documentNameError(): { code: string; detail: string } | null {
            return this.isDefaultLanguage && this.hasSubmittedDocumentEdit && !this.documentTitle.trim()
                ? { code: 'PRODUCT_DOCUMENT_NAME_REQUIRED', detail: this.$t('sw-product.documentForm.errorNameRequired') }
                : null;
        },

        documentFileError(): { code: string; detail: string } | null {
            return this.isDefaultLanguage && this.hasSubmittedDocumentEdit && !this.editingMedia
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

        allowedExtensions(): string[] {
            return Shopware.Store.get('context').app.config.settings?.productDocuments?.allowedExtensions ?? [];
        },

        allowedMimeTypesByExtension(): Record<string, string[]> {
            const config = Shopware.Store.get('context').app.config.settings?.productDocuments;
            return config?.allowedMimeTypesByExtension ?? {};
        },

        maxFileSize(): number {
            return Shopware.Store.get('context').app.config.settings?.productDocuments?.maxFileSize ?? 0;
        },

        uploadHint(): string {
            return this.$t('sw-product.documentForm.uploadHint', {
                extensions: this.allowedExtensions.join(', ').toUpperCase(),
                size: Shopware.Utils.format.fileSize(this.maxFileSize),
            });
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

        getPaginatedDocuments(documents: EntityCollection<'product_document'>): Entity<'product_document'>[] {
            return documents.slice(this.pageOffset, this.pageOffset + this.limit);
        },

        getDocumentSelectionFilter(isInherited: boolean): (document: Entity<'product_document'>) => boolean {
            return (document) => this.canEdit && !isInherited && !this.isMissingLanguageVersion(document);
        },

        canMoveDocument(document: Entity<'product_document'>, offset: number): boolean {
            const index = this.documents.indexOf(document);
            const newIndex = index + offset;
            return index >= 0 && newIndex >= 0 && newIndex < this.documents.length;
        },

        onPageChange({ page, limit }: { page: number; limit: number }): void {
            this.page = page;
            this.limit = limit;
        },

        canChangeDocuments(): boolean {
            const inheritance = this.$refs.documentInheritance as DocumentInheritance | undefined;
            return this.canEdit && inheritance?.isInherited === false;
        },

        getTitle(document: Entity<'product_document'>): string {
            return document.title || document.translated?.title || this.getMedia(document)?.fileName || '';
        },

        getFileSize(document: Entity<'product_document'>): string {
            const size = this.getMedia(document)?.fileSize;
            return size == null ? '' : Shopware.Utils.format.fileSize(size);
        },

        getFileName(document: Entity<'product_document'>): string {
            const media = this.getMedia(document);
            return media?.fileName ? [media.fileName, media.fileExtension].filter(Boolean).join('.') : '';
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

        getDocumentValidationError(
            media: Entity<'media'>,
            editingDocument: Entity<'product_document'> | null,
        ): string | null {
            if (media.private) {
                return this.$t('sw-product.documentForm.errorPrivateMedia');
            }

            const isAllowedExtension = this.allowedExtensions.includes(media.fileExtension?.toLowerCase() ?? '');
            if (!isAllowedExtension || typeof media.fileSize !== 'number' || media.fileSize > this.maxFileSize) {
                return this.uploadHint;
            }

            const isDuplicate = this.documents.some(
                (document) =>
                    document !== editingDocument && (document.mediaId || document.translated?.mediaId) === media.id,
            );
            return isDuplicate ? this.$t('sw-product.documentForm.errorDocumentDuplicated') : null;
        },

        addDocuments(mediaItems: Entity<'media'>[], editingDocument: Entity<'product_document'> | null = null): void {
            if (!this.canChangeDocuments() || (!editingDocument && !this.isDefaultLanguage)) {
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

                const document = this.documentRepository.create(Shopware.Context.api);
                document.productId = this.product.id;
                document.mediaId = media.id;
                document.title = media.fileName;
                document.position = this.documents.length;
                Shopware.Store.get('swProductDetail').setProductDocumentMedia(media);
                this.documents.add(document);
            }
            this.showMediaModal = false;
        },

        moveDocument(document: Entity<'product_document'>, offset: number): void {
            if (!this.canChangeDocuments() || !this.canMoveDocument(document, offset)) {
                return;
            }

            const oldIndex = this.documents.indexOf(document);
            const newIndex = oldIndex + offset;

            this.documents.moveItem(oldIndex, newIndex);
            refreshDocumentPositions(this.documents);
        },

        openDocumentEditor(document: Entity<'product_document'>): void {
            if (!this.canChangeDocuments()) {
                return;
            }

            this.documentEditSession += 1;
            this.editingDocument = document;
            this.documentTitle = this.isDefaultLanguage ? this.getTitle(document) : document.title || '';
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
            const { changes, variantChanges } = getDocumentEditChanges(document, this, this.getTitle(document), context);
            const matchingMediaId = this.applyToVariants && this.hasVariants ? originalMediaId : null;
            this.isApplyingDocumentEdit = true;

            try {
                if (replacementMedia) {
                    Shopware.Store.get('swProductDetail').setProductDocumentMedia(replacementMedia);
                }
                let updatedVariantIds: string[] = [];
                if (matchingMediaId) {
                    const variants = await this.loadDocumentVariants(
                        this.productRepository,
                        product.id,
                        matchingMediaId,
                        context,
                    );
                    if (!isCurrentEdit()) {
                        return;
                    }
                    updatedVariantIds = this.stageVariantDocumentChanges(variants, document, variantChanges);
                }
                this.applyDocumentChanges(document, changes);
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

        onDocumentSelectionChange(selection: Record<string, Entity<'product_document'>>): void {
            this.selectedDocuments = Object.values(selection);
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

        closeRemoveDocumentModal(): void {
            this.documentRemovalSession += 1;
            this.documentsToRemove = [];
            this.isRemovingDocuments = false;
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
            const mediaIds =
                this.removeFromVariants && this.hasVariants
                    ? [
                          ...new Set(
                              documents
                                  .map((document) => document.mediaId || document.translated?.mediaId)
                                  .filter((id) => typeof id === 'string'),
                          ),
                      ]
                    : [];
            this.isRemovingDocuments = true;
            try {
                const variants = mediaIds.length
                    ? await this.loadDocumentVariants(this.productRepository, product.id, mediaIds, context)
                    : [];
                if (!isCurrentRemoval()) {
                    return;
                }
                const name = this.getTitle(documents[0]);
                const removedVariantIds = new Set<string>();
                documents.forEach((document) => {
                    if (mediaIds.length && (document.mediaId || document.translated?.mediaId)) {
                        this.stageVariantDocumentRemovals(variants, document).forEach((id) => removedVariantIds.add(id));
                    }
                    if (this.isDefaultLanguage) {
                        this.documents.remove(document.id);
                    } else {
                        this.resetDocumentLanguageVersion(document);
                    }
                });
                if (this.isDefaultLanguage) {
                    refreshDocumentPositions(this.documents);
                }
                const message =
                    documents.length > 1
                        ? this.$t(
                              this.isDefaultLanguage
                                  ? 'sw-product.documentForm.bulkRemoveSuccess'
                                  : 'sw-product.documentForm.bulkRemoveLanguageVersionSuccess',
                              {
                                  count: documents.length,
                                  language: this.contentLanguageName,
                              },
                          )
                        : this.isDefaultLanguage
                          ? this.$t('sw-product.documentForm.removeSuccess', { name, count: removedVariantIds.size })
                          : this.$t('sw-product.documentForm.removeLanguageVersionSuccess', {
                                language: this.contentLanguageName,
                                name,
                            });
                useSnackbar().addSnackbar({ variant: 'success', message });
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

        removeDocumentInheritance(
            inheritedDocuments: EntityCollection<'product_document'>,
        ): EntityCollection<'product_document'> {
            if (!this.canEdit) {
                return this.documents;
            }

            (this.$refs.documentInheritance as DocumentInheritance).forceInheritanceRemove = true;
            copyInheritedDocuments(
                inheritedDocuments,
                this.product.id,
                this.documentRepository,
                this.entityFactory as EntityFactory,
            ).forEach((document) => this.documents.add(document));

            return this.documents;
        },

        onShowRestoreInheritanceModal(): void {
            this.showRestoreInheritanceModal = true;
        },

        onCloseRestoreInheritanceModal(): void {
            this.showRestoreInheritanceModal = false;
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

        isMissingLanguageVersion(document: Entity<'product_document'>): boolean {
            return !this.isDefaultLanguage && !document.title && !document.mediaId;
        },

        getEditActionLabel(document: Entity<'product_document'>): string {
            if (this.isMissingLanguageVersion(document)) {
                return this.$t('sw-product.documentForm.addLanguageVersion', { language: this.contentLanguageName });
            }

            return this.$t('global.default.edit');
        },

        getRemoveActionLabel(): string {
            return this.isDefaultLanguage
                ? this.$t('global.default.remove')
                : this.$t('sw-product.documentForm.removeLanguageVersion', { language: this.contentLanguageName });
        },
    },
});
