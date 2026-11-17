/**
 * @sw-package inventory
 */
import useSnackbar from 'src/app/composables/use-snackbar';
import type EntityFactory from 'src/core/data/entity-factory.data';
import useNotification from 'src/app/composables/use-notification';
import documentColumns, { allowedExtensions, maxFileSize } from './document-form.config';
import { getDocumentTranslationChanges, getEditChanges } from './document-translation.utils';
import { loadDocumentVariants } from './document-variant.helper';
import useDocumentChanges from './use-document-changes';
import { copyInheritedDocuments } from './document-inheritance.helper';
import { updateDocumentPositions } from './document-position.helper';
import { getHintParams, getFileAccept, isInvalidDocumentFile } from './document-upload.helper';
import template from './sw-product-document-form.html.twig';
import './sw-product-document-form.scss';

type DocumentGrid = { resetSelection: () => void };

type DocumentInheritance = { isInherited: boolean; forceInheritanceRemove: boolean; restoreInheritance: () => void };

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    emits: ['language-change'],

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
            removingDocuments: [] as Entity<'product_document'>[],
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
            allowedExtensions: [...allowedExtensions],
            maxFileSize,
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
            const languageLoaded = this.isDefaultLanguage || this.documentContext.language?.id === this.languageId;
            return !this.disabled && this.acl.can('product.editor') && languageLoaded;
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
            const showSavedFile = document && !this.documentFileRemoved && (this.isDefaultLanguage || document.mediaId);
            return this.replacementMedia || (showSavedFile ? this.getMedia(document) : undefined);
        },

        canSaveDocument(): boolean {
            const hasRequiredFields = !!this.documentTitle.trim() && !!this.editingMedia;
            return this.canEdit && !this.isSavingDocument && (!this.isDefaultLanguage || hasRequiredFields);
        },

        documentNameError(): { code: string; detail: string } | null {
            return this.isDefaultLanguage && this.documentValidationSubmitted && !this.documentTitle.trim()
                ? { code: 'PRODUCT_DOCUMENT_NAME_REQUIRED', detail: this.$t('sw-product.documentForm.errorNameRequired') }
                : null;
        },

        documentFileError(): { code: string; detail: string } | null {
            return this.isDefaultLanguage && this.documentValidationSubmitted && !this.editingMedia
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
            return this.$t('sw-product.documentForm.uploadHint', getHintParams(this.allowedExtensions, this.maxFileSize));
        },

        fileAccept(): string {
            return getFileAccept(this.fileValidationService.extensionByType, this.allowedExtensions);
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
            return (document) =>
                this.canEdit && !isInherited && (this.isDefaultLanguage || !!document.title || !!document.mediaId);
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

        async successfulUpload(
            {
                targetId,
                successAmount = 1,
                totalAmount = 1,
            }: {
                targetId: EntityKey<'media'>;
                successAmount?: number;
                totalAmount?: number;
            },
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

                if (isInvalidDocumentFile(media, this.allowedExtensions, this.maxFileSize)) {
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

        moveDocument(document: Entity<'product_document'>, offset: number): void {
            if (!this.canChangeDocuments() || !this.canMoveDocument(document, offset)) {
                return;
            }

            const oldIndex = this.documents.indexOf(document);
            const newIndex = oldIndex + offset;

            this.documents.moveItem(oldIndex, newIndex);
            updateDocumentPositions(this.documents);
        },

        openDocumentEditor(document: Entity<'product_document'>): void {
            if (!this.canChangeDocuments()) {
                return;
            }

            this.documentEditSession += 1;
            this.editingDocument = document;
            this.documentTitle = this.isDefaultLanguage ? this.getTitle(document) : document.title || '';
            this.replacementMedia = null;
            this.documentFileRemoved = false;
            this.applyToVariants = this.hasVariants;
            this.documentValidationSubmitted = false;
        },

        removeEditingFile(): void {
            this.replacementMedia = null;
            this.documentFileRemoved = true;
        },

        async applyDocumentEdit(): Promise<void> {
            this.documentValidationSubmitted = true;
            if (!this.canChangeDocuments() || !this.editingDocument || !this.canSaveDocument) {
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
            const { changes, variantChanges } = getEditChanges(document, this, this.getTitle(document), context);
            const matchingMediaId = this.applyToVariants && this.hasVariants ? originalMediaId : null;
            this.isSavingDocument = true;

            try {
                if (replacementMedia) {
                    Shopware.Store.get('swProductDetail').cacheDocumentMedia(replacementMedia);
                }
                let updatedVariantIds: string[] = [];
                if (matchingMediaId) {
                    const variants = await loadDocumentVariants(
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
                    this.isSavingDocument = false;
                    useNotification().createNotificationError({
                        message: this.$t('sw-product.documentForm.errorLoadVariants'),
                    });
                }
            }
        },

        closeDocumentEditor(): void {
            this.documentEditSession += 1;
            this.isSavingDocument = false;
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

            this.removingDocuments = (Array.isArray(document) ? document : [document]).filter(
                (item) => this.isDefaultLanguage || item.title || item.mediaId,
            );
            this.removeFromVariants = this.hasVariants;
            this.removeModalVersion += 1;
        },

        closeRemoveDocumentModal(): void {
            this.removeModalVersion += 1;
            this.removingDocuments = [];
            this.isRemovingDocument = false;
        },

        async removeDocument(): Promise<void> {
            if (!this.canChangeDocuments() || !this.removingDocuments.length || this.isRemovingDocument) {
                return;
            }

            const product = this.product;
            const documents = this.removingDocuments;
            const modalVersion = this.removeModalVersion;
            const context = { ...this.documentContext, inheritance: false };
            const isCurrentRemoval = () =>
                modalVersion === this.removeModalVersion &&
                product === this.product &&
                documents === this.removingDocuments &&
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
            this.isRemovingDocument = true;
            try {
                const variants = mediaIds.length
                    ? await loadDocumentVariants(this.productRepository, product.id, mediaIds, context)
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
                    updateDocumentPositions(this.documents);
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
                    this.isRemovingDocument = false;
                    useNotification().createNotificationError({
                        message: this.$t('sw-product.documentForm.errorLoadVariants'),
                    });
                }
            }
        },

        documentRemoveInheritanceFunction(
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

        getEditActionLabel(document: Entity<'product_document'>): string {
            if (!this.isDefaultLanguage && !document.title && !document.mediaId) {
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
