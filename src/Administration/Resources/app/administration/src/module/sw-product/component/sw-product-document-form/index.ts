/**
 * @sw-package inventory
 */
import type EntityFactory from 'src/core/data/entity-factory.data';
import useNotification from 'src/app/composables/use-notification';
import documentColumns, { allowedExtensions, maxFileSize, maxDocuments } from './document-form.config';
import {
    resetDocumentTranslation,
    getDocumentTranslationChanges,
    getEditChanges,
    type DocumentChanges,
} from './document-translation.utils';
import { loadDocumentVariants, getDocumentVariantCandidates } from './document-variant.helper';
import { copyInheritedDocuments } from './document-inheritance.helper';
import { updateDocumentPositions } from './document-position.helper';
import { getHintParams, getFileAccept, isInvalidDocumentFile } from './document-upload.helper';
import template from './sw-product-document-form.html.twig';
import './sw-product-document-form.scss';

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
        'mediaService',
    ],

    props: {
        disabled: { type: Boolean, required: false, default: false },
    },

    data() {
        return {
            showUploadModal: false,
            showMediaModal: false,
            showRestoreInheritanceModal: false,
            mediaFolderId: undefined as string | null | undefined,
            editingDocument: null as Entity<'product_document'> | null,
            removingDocument: null as Entity<'product_document'> | null,
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

        documentContext() {
            return { ...Shopware.Context.api, language: Shopware.Store.get('swProductDetail').documentLanguage };
        },

        documents(): EntityCollection<'product_document'> {
            return this.product.documents as EntityCollection<'product_document'>;
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
            return this.canEdit && this.isDefaultLanguage && this.documents.length < this.maxDocuments;
        },

        isDefaultLanguage(): boolean {
            return this.languageId === this.systemLanguageId;
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
        product() {
            this.showUploadModal = false;
            this.onCloseRestoreInheritanceModal();
            this.closeDocumentEditor();
            this.closeRemoveDocumentModal();
        },
    },

    async created() {
        try {
            this.mediaFolderId = await this.mediaService.getDefaultFolderId('product_document');
        } catch {
            this.mediaFolderId = null;
        }
    },

    beforeUnmount() {
        this.closeDocumentEditor();
        this.closeRemoveDocumentModal();
    },

    methods: {
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
            { targetId }: { targetId: EntityKey<'media'> },
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
                    this.addDocuments([media], editingDocument);
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

        moveDocument(document: Entity<'product_document'>, offset: number): void {
            if (!this.canChangeDocuments()) {
                return;
            }

            const oldIndex = this.documents.indexOf(document);
            const newIndex = oldIndex + offset;
            if (oldIndex < 0 || newIndex < 0 || newIndex >= this.documents.length) {
                return;
            }

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
                const store = Shopware.Store.get('swProductDetail');
                store.queueDocumentSaveNotification(document.id, this.getTitle(document), updatedVariantIds);
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

        openRemoveDocumentModal(document: Entity<'product_document'>): void {
            if (!this.canChangeDocuments() || (!this.isDefaultLanguage && !document.title && !document.mediaId)) {
                return;
            }

            this.removingDocument = document;
            this.removeFromVariants = this.hasVariants;
            this.removeModalVersion += 1;
        },

        closeRemoveDocumentModal(): void {
            this.removeModalVersion += 1;
            this.removingDocument = null;
            this.isRemovingDocument = false;
        },

        async removeDocument(): Promise<void> {
            if (!this.canChangeDocuments() || !this.removingDocument || this.isRemovingDocument) {
                return;
            }

            const product = this.product;
            const document = this.removingDocument;
            const modalVersion = this.removeModalVersion;
            const context = { ...this.documentContext, inheritance: false };
            const isCurrentRemoval = () =>
                modalVersion === this.removeModalVersion &&
                product === this.product &&
                document === this.removingDocument &&
                context.languageId === this.languageId;
            const mediaId =
                this.removeFromVariants && this.hasVariants ? document.mediaId || document.translated?.mediaId : null;
            this.isRemovingDocument = true;
            try {
                const variants = mediaId
                    ? await loadDocumentVariants(this.productRepository, product.id, mediaId, context)
                    : [];
                if (!isCurrentRemoval()) {
                    return;
                }
                if (mediaId) {
                    this.stageVariantDocumentRemovals(variants, document);
                }
                if (this.isDefaultLanguage) {
                    this.documents.remove(document.id);
                    updateDocumentPositions(this.documents);
                } else {
                    this.resetDocumentLanguageVersion(document);
                }
                const store = Shopware.Store.get('swProductDetail');
                store.documentSaveNotifications = store.documentSaveNotifications.filter(
                    (entry) => entry.documentId !== document.id,
                );
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

        applyDocumentChanges(document: Entity<'product_document'>, changes: DocumentChanges): void {
            Object.assign(document, changes);
            const translation = document.translations?.find((entry) => entry.languageId === this.languageId);
            if (!translation?.isNew()) {
                return;
            }
            if (changes.title !== undefined) {
                Object.assign(translation, { title: changes.title });
            }
            if (changes.mediaId !== undefined) {
                const media = changes.mediaId ? this.getMedia(document) : null;
                Object.assign(translation, { mediaId: changes.mediaId, media });
            }
        },

        resetDocumentLanguageVersion(document: Entity<'product_document'>): void {
            const { language, systemLanguageId } = this.documentContext;
            this.applyDocumentChanges(document, resetDocumentTranslation(document, language, systemLanguageId));
        },

        stageVariantDocumentChanges(
            variants: Entity<'product'>[],
            original: Entity<'product_document'>,
            changes: DocumentChanges,
        ): string[] {
            const children =
                this.product.children || new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api);
            const candidates = getDocumentVariantCandidates(variants, children, original);
            const updatedVariantIds: string[] = [];
            const context = this.documentContext;
            candidates.forEach(({ variant, matching }) => {
                let changed = false;
                matching.forEach((copy) => {
                    const update = getDocumentTranslationChanges(copy, changes, context, this.isDefaultLanguage);
                    this.applyDocumentChanges(copy, update);
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
            this.product.children = children;
            return updatedVariantIds;
        },

        stageVariantDocumentRemovals(variants: Entity<'product'>[], original: Entity<'product_document'>): void {
            const children =
                this.product.children || new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api);
            const origin = this.product.getOrigin();
            const originChildren =
                origin.children || new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api);
            const candidates = getDocumentVariantCandidates(variants, children, original);
            candidates.forEach(({ variant, documents, matching }) => {
                // Register loaded children in the origin so the changeset includes nested document deletions.
                if (!variant.isNew() && !originChildren.has(variant.id)) {
                    originChildren.add(variant);
                }
                if (!children.has(variant.id)) {
                    children.add(variant);
                }
                if (this.isDefaultLanguage) {
                    matching.forEach((copy) => documents.remove(copy.id));
                    updateDocumentPositions(documents);
                } else {
                    matching.forEach((copy) => this.resetDocumentLanguageVersion(copy));
                }
            });
            this.product.children = children;
            origin.children = originChildren;
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
            (this.$refs.documentInheritance as DocumentInheritance).restoreInheritance();
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
