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
        'entityFactory',
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
            editingDocument: null as Entity<'product_document'> | null,
            removingDocument: null as Entity<'product_document'> | null,
            documentTitle: '',
            documentMedia: {} as Record<string, Entity<'media'>>,
            documentEditSession: 0,
            replacementMedia: null as Entity<'media'> | null,
            documentFileRemoved: false,
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
            const language = this.documentContext.language;
            const languageLoaded = this.isDefaultLanguage || language?.id === Shopware.Context.api.languageId;
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
            const title = (changes.translated ?? document.translated)?.title;
            return title || this.getMedia(document)?.fileName || '';
        },

        editingMedia(): Entity<'media'> | undefined {
            const document = this.editingDocument;
            const showSavedFile = document && !this.documentFileRemoved && (this.isDefaultLanguage || document.mediaId);
            return this.replacementMedia || (showSavedFile ? this.getMedia(document) : undefined);
        },

        canSaveDocument(): boolean {
            const hasRequiredFields = !!this.documentTitle.trim() && !!this.editingMedia;
            return this.canEdit && (!this.isDefaultLanguage || hasRequiredFields);
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
            this.closeDocumentEditor();
            this.closeRemoveDocumentModal();
            this.documentMedia = {};
        },
    },

    created() {
        void this.createdComponent();
    },

    beforeUnmount() {
        this.closeDocumentEditor();
        this.closeRemoveDocumentModal();
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
                ? this.documentMedia[mediaId] || document?.translations?.find((item) => item.mediaId === mediaId)?.media
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
                this.documentMedia[media.id] = media;
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
            this.updatePositions();
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
            this.documentValidationSubmitted = false;
        },

        removeEditingFile(): void {
            this.replacementMedia = null;
            this.documentFileRemoved = true;
        },

        applyDocumentEdit(): void {
            this.documentValidationSubmitted = true;
            if (!this.canChangeDocuments() || !this.editingDocument || !this.canSaveDocument) return;
            const document = this.editingDocument;
            if (this.replacementMedia) this.documentMedia[this.replacementMedia.id] = this.replacementMedia;
            const { changes } = getEditChanges(document, this, this.getTitle(document), this.documentContext);
            this.applyDocumentChanges(document, changes);
            this.closeDocumentEditor();
        },

        closeDocumentEditor(): void {
            this.documentEditSession += 1;
            this.editingDocument = null;
            this.replacementMedia = null;
            this.showMediaModal = false;
        },

        openRemoveDocumentModal(document: Entity<'product_document'>): void {
            if (!this.canChangeDocuments() || (!this.isDefaultLanguage && !document.title && !document.mediaId)) return;
            this.removingDocument = document;
        },

        closeRemoveDocumentModal(): void {
            this.removingDocument = null;
        },

        removeDocument(): void {
            if (!this.canChangeDocuments() || !this.removingDocument) return;
            if (this.isDefaultLanguage) {
                this.documents.remove(this.removingDocument.id);
                this.updatePositions();
            } else {
                this.resetDocumentLanguageVersion(this.removingDocument);
            }
            this.closeRemoveDocumentModal();
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

        documentRemoveInheritanceFunction(
            inheritedDocuments: EntityCollection<'product_document'>,
        ): EntityCollection<'product_document'> {
            if (!this.canEdit) {
                return this.documents;
            }

            (this.$refs.documentInheritance as DocumentInheritance).forceInheritanceRemove = true;
            inheritedDocuments.forEach((source) => {
                const document = this.documentRepository.create(Shopware.Context.api);
                document.productId = this.product.id;
                document.position = source.position;
                document.title = source.title;
                document.mediaId = source.mediaId;
                document.customFields = Shopware.Utils.object.cloneDeep(source.customFields);
                document.translated = Shopware.Utils.object.cloneDeep(source.translated);

                source.translations?.forEach((sourceTranslation) => {
                    const translation = (this.entityFactory as EntityFactory).create(
                        'product_document_translation',
                        null,
                        Shopware.Context.api,
                    ) as Entity<'product_document_translation'>;
                    translation.productDocumentId = document.id;
                    translation.languageId = sourceTranslation.languageId;
                    translation.mediaId = sourceTranslation.mediaId;
                    translation.title = sourceTranslation.title;
                    translation.customFields = Shopware.Utils.object.cloneDeep(sourceTranslation.customFields);
                    document.translations.add(translation);
                    translation.media = sourceTranslation.media;
                });
                this.documents.add(document);
            });

            return this.documents;
        },

        updatePositions(): void {
            this.documents.forEach((document, index) => {
                document.position = index;
            });
        },
    },
});
