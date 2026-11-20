/**
 * @sw-package inventory
 */
import useSnackbar from 'src/app/composables/use-snackbar';
import type EntityFactory from 'src/core/data/entity-factory.data';
import useNotification from 'src/app/composables/use-notification';
import type { DocumentChanges } from 'src/module/sw-product/service/product-document-variant.service';
import type { DocumentEdit, DocumentUploadRules } from './sw-product-document-edit-modal';
import type { DocumentRemoval } from './sw-product-document-remove-modal';
import { getDocumentMedia, setDocumentMedia } from './product-document.helper';
import template from './sw-product-document-form.html.twig';
import './sw-product-document-form.scss';

type DocumentGrid = { resetSelection: () => void };

type DocumentInheritance = { restoreInheritance: () => void };

type DocumentUploadResult = { targetId: EntityKey<'media'>; successAmount?: number; totalAmount?: number };

const columns = [
    { property: 'number', label: '#', width: '56px', align: 'center' },
    { property: 'position', label: 'sw-product.documentForm.columnPosition', width: '110px', align: 'center' },
    { property: 'title', label: 'sw-product.documentForm.columnTitle', primary: true, width: '300px' },
    { property: 'fileName', label: 'sw-product.documentForm.columnFileName', primary: true, width: '300px' },
    { property: 'fileType', label: 'sw-product.documentForm.columnFileType', visible: false, width: '104px' },
    {
        property: 'fileSize',
        label: 'sw-product.documentForm.columnFileSize',
        width: '100px',
        visible: false,
        align: 'right',
    },
];

const documentExtensions = [
    'pdf',
    'docx',
    'jpg',
    'jpeg',
    'png',
];

const documentMaxFileSize = 20 * 1024 * 1024;

const { Context, Store, Utils } = Shopware;
const { cloneDeep, pick } = Utils.object;

const documentCopyFields = [
    'position',
    'title',
    'mediaId',
    'customFields',
    'translated',
] as const;

const translationCopyFields = [
    'languageId',
    'mediaId',
    'title',
    'customFields',
] as const;

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

    data() {
        return {
            page: 1,
            limit: 10,
            showUploadModal: false,
            showMediaModal: false,
            showRestoreInheritanceModal: false,
            isInheritanceRemoved: false,
            mediaFolderId: undefined as string | null | undefined,
            editingDocument: null as Entity<'product_document'> | null,
            selectedDocuments: [] as Entity<'product_document'>[],
            documentsToRemove: [] as Entity<'product_document'>[],
        };
    },

    computed: {
        product(): Entity<'product'> {
            return Store.get('swProductDetail').product;
        },

        parentProduct(): Entity<'product'> {
            return Store.get('swProductDetail').parentProduct;
        },

        documentRepository() {
            return this.repositoryFactory.create('product_document');
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        languageId() {
            return Store.get('context').api.languageId;
        },

        isSystemDefaultLanguage(): boolean {
            return Store.get('context').isSystemDefaultLanguage;
        },

        contextLanguage(): Entity<'language'> | null {
            return Store.get('context').api.language as Entity<'language'> | null;
        },

        documents(): EntityCollection<'product_document'> {
            return this.product.documents as EntityCollection<'product_document'>;
        },

        isInherited(): boolean {
            return !!this.product.parentId && !this.isInheritanceRemoved && !this.documents.length;
        },

        currentDocuments(): EntityCollection<'product_document'> {
            return this.isInherited
                ? (this.parentProduct.documents as EntityCollection<'product_document'>)
                : this.documents;
        },

        pageOffset(): number {
            return (this.page - 1) * this.limit;
        },

        paginatedDocuments(): Entity<'product_document'>[] {
            return this.currentDocuments.slice(this.pageOffset, this.pageOffset + this.limit);
        },

        uploadTag(): string {
            return `product-documents-${this.product.id}-${this.languageId}`;
        },

        canEdit(): boolean {
            const isLanguageReady = this.isSystemDefaultLanguage || this.contextLanguage?.id === this.languageId;
            return this.acl.can('product.editor') && isLanguageReady;
        },

        canAddDocuments(): boolean {
            return this.canEditDocuments && this.isSystemDefaultLanguage;
        },

        canEditDocuments(): boolean {
            return this.canEdit && !this.isInherited;
        },

        isDocumentSelectable(): (document: Entity<'product_document'>) => boolean {
            return (document) => this.canEditDocuments && !this.isMissingLanguageVersion(document);
        },

        languageCode(): string {
            const language = this.contextLanguage;
            if (this.isSystemDefaultLanguage || language?.id !== this.languageId) {
                return '';
            }

            return language.locale?.code.split('-')[0].toUpperCase() ?? '';
        },

        contentLanguageName(): string {
            const language = this.contextLanguage;
            const code = language?.locale?.code.split('-')[0];
            const locale = Store.get('session').currentLocale || 'en-GB';
            const names = new Intl.DisplayNames([locale], { type: 'language' });
            return code ? names.of(code) || '' : language?.name || '';
        },

        documentColumns() {
            return columns.map(({ label, ...column }) => ({
                ...column,
                label: label === '#' ? label : this.$t(label),
                sortable: false,
                allowResize: !['number', 'position'].includes(column.property),
            }));
        },

        removeActionLabel(): string {
            return this.isSystemDefaultLanguage
                ? this.$t('global.default.remove')
                : this.$t('sw-product.documentForm.removeLanguageVersion', { language: this.contentLanguageName });
        },

        uploadRules(): DocumentUploadRules {
            const mimeTypes = Object.entries(this.fileValidationService.extensionByType);
            const allowedMimeTypesByExtension = Object.fromEntries(
                documentExtensions.map((extension) => [
                    extension,
                    mimeTypes.filter(([, extensions]) => extensions.includes(extension)).map(([mimeType]) => mimeType),
                ]),
            );

            return {
                allowedExtensions: documentExtensions,
                allowedMimeTypesByExtension,
                maxFileSize: documentMaxFileSize,
                fileAccept: [...new Set(Object.values(allowedMimeTypesByExtension).flat())].join(','),
            };
        },

        uploadHint(): string {
            return this.$t('sw-product.documentForm.uploadHint', {
                extensions: this.uploadRules.allowedExtensions.join(', ').toUpperCase(),
                size: Utils.format.fileSize(this.uploadRules.maxFileSize),
            });
        },

        editingTitlePlaceholder(): string {
            if (!this.editingDocument || this.isSystemDefaultLanguage) {
                return '';
            }

            return this.getFallbackTranslation(this.editingDocument, 'title')?.title || '';
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
            this.showRestoreInheritanceModal = false;
            this.isInheritanceRemoved = false;
            this.editingDocument = null;
            this.documentsToRemove = [];
            this.selectedDocuments = [];
        },
    },

    async created() {
        try {
            this.mediaFolderId = await this.mediaDefaultFolderService.getDefaultFolderId('product_document');
        } catch {
            this.mediaFolderId = null;
        }
    },

    methods: {
        getDocumentMedia,

        getFileName(document: Entity<'product_document'>): string {
            const media = getDocumentMedia(document);
            return media?.fileName ? [media.fileName, media.fileExtension].filter(Boolean).join('.') : '';
        },

        isFallbackValue(document: Entity<'product_document'>, field: 'title' | 'mediaId'): boolean {
            return !this.isSystemDefaultLanguage && !document[field];
        },

        isVariantOnly(document: Entity<'product_document'>): boolean {
            const mediaId = document.translated?.mediaId;
            return (
                !!this.product.parentId &&
                !!mediaId &&
                !this.parentProduct.documents?.some(
                    (parentDocument) =>
                        parentDocument.translated?.mediaId === mediaId ||
                        parentDocument.translations?.some((translation) => translation.mediaId === mediaId),
                )
            );
        },

        getFileSize(document: Entity<'product_document'>): string {
            const fileSize = getDocumentMedia(document)?.fileSize;
            return typeof fileSize === 'number' ? Utils.format.fileSize(fileSize) : '';
        },

        canMoveDocument(document: Entity<'product_document'>, offset: number): boolean {
            const index = this.documents.indexOf(document);
            return index >= 0 && !!this.documents[index + offset];
        },

        onPageChange({ page, limit }: { page: number; limit: number }): void {
            this.page = page;
            this.limit = limit;
        },

        refreshPositions(documents: EntityCollection<'product_document'>): void {
            documents.forEach((document, index) => {
                document.position = index;
            });
        },

        async onUploadFinished({ targetId, successAmount = 1, totalAmount = 1 }: DocumentUploadResult): Promise<void> {
            const productId = this.product.id;
            try {
                const media = await this.mediaRepository.get(targetId, Context.api);
                if (media && Store.get('swProductDetail').product.id === productId) {
                    const documentCount = this.documents.length;
                    this.addDocuments([media]);
                    if (this.documents.length > documentCount && successAmount === totalAmount) {
                        this.showUploadModal = false;
                    }
                }
            } catch {
                useNotification().createNotificationError({
                    message: this.$t('sw-product.documentForm.errorLoadMedia'),
                });
            }
        },

        addDocuments(documentFiles: Entity<'media'>[]): void {
            if (!this.canAddDocuments) {
                return;
            }

            documentFiles
                .filter((media) => this.isValidMedia(media, null))
                .forEach((media) => {
                    const document = this.documentRepository.create(Context.api);
                    document.productId = this.product.id;
                    document.mediaId = media.id;
                    document.title = media.fileName ?? '';
                    document.translated = { title: document.title, mediaId: media.id };
                    document.position = Math.max(-1, ...this.documents.map((item) => item.position ?? -1)) + 1;
                    setDocumentMedia(document, media);
                    this.documents.add(document);
                });
            this.showMediaModal = false;
        },

        moveDocument(document: Entity<'product_document'>, offset: number): void {
            if (!this.canEditDocuments || !this.canMoveDocument(document, offset)) {
                return;
            }

            const oldIndex = this.documents.indexOf(document);
            this.documents.moveItem(oldIndex, oldIndex + offset);
            this.refreshPositions(this.documents);
        },

        openDocumentEditor(document: Entity<'product_document'>): void {
            if (this.canEditDocuments) {
                this.editingDocument = document;
            }
        },

        getFallbackTranslation(
            document: Entity<'product_document'>,
            field: 'title' | 'mediaId',
        ): Entity<'product_document_translation'> | undefined {
            const { language, systemLanguageId } = Store.get('context').api;

            return [language?.parentId, systemLanguageId]
                .map((languageId) => document.translations?.find((translation) => translation.languageId === languageId))
                .find((translation) => translation && translation[field] !== null);
        },

        isValidMedia(media: Entity<'media'>, exceptDocument: Entity<'product_document'> | null): boolean {
            const { allowedExtensions, maxFileSize } = this.uploadRules;
            const extension = media.fileExtension?.toLowerCase() ?? '';
            const isAllowedFile = allowedExtensions.includes(extension) && (media.fileSize ?? Infinity) <= maxFileSize;
            const isDuplicate = this.documents.some(
                (document) => document !== exceptDocument && document.translated?.mediaId === media.id,
            );
            if (isAllowedFile && !isDuplicate) {
                return true;
            }

            const message = isAllowedFile ? this.$t('sw-product.documentForm.errorDocumentDuplicated') : this.uploadHint;
            useSnackbar().addSnackbar({ variant: 'error', message });
            return false;
        },

        setDocumentValues(document: Entity<'product_document'>, changes: DocumentChanges, media: Entity<'media'> | null) {
            const translated = Object.fromEntries(
                (['title', 'mediaId'] as const)
                    .filter((field) => changes[field] !== undefined)
                    .map((field) => [
                        field,
                        changes[field] ?? this.getFallbackTranslation(document, field)?.[field] ?? null,
                    ]),
            );
            Object.assign(document, changes);
            document.translated = { ...document.translated, ...translated };
            if (changes.mediaId !== undefined) {
                setDocumentMedia(document, changes.mediaId ? media : null);
            }

            const translation = document.translations?.find((entry) => entry.languageId === this.languageId);
            if (translation?.isNew()) {
                Object.assign(translation, changes);
            }
        },

        applyDocumentEdit({ changes, media, operation, message }: DocumentEdit): void {
            const document = this.editingDocument;
            this.editingDocument = null;
            if (!document) {
                return;
            }

            this.setDocumentValues(document, changes, media);
            if (operation) {
                Store.get('swProductDetail').documentVariantOperations[document.id] = operation;
            }
            if (message) {
                useSnackbar().addSnackbar({ variant: 'success', message });
            }
        },

        onDocumentSelectionChange(selection: Record<string, Entity<'product_document'>>): void {
            this.selectedDocuments = Object.values(selection);
        },

        openRemoveDocumentModal(document: Entity<'product_document'> | Entity<'product_document'>[]): void {
            if (!this.canEditDocuments) {
                return;
            }

            const documents = Array.isArray(document) ? document : [document];
            this.documentsToRemove = documents.filter((item) => !this.isMissingLanguageVersion(item));
        },

        removeDocuments({ operations, message }: DocumentRemoval): void {
            if (this.isSystemDefaultLanguage) {
                this.documentsToRemove.forEach((document) => this.documents.remove(document.id));
                this.refreshPositions(this.documents);
            } else {
                this.documentsToRemove.forEach((document) =>
                    this.setDocumentValues(document, { title: null, mediaId: null }, null),
                );
            }
            (this.$refs.documentGrid as DocumentGrid | undefined)?.resetSelection();
            this.selectedDocuments = [];
            this.documentsToRemove = [];
            Object.assign(Store.get('swProductDetail').documentVariantOperations, operations);
            useSnackbar().addSnackbar({ variant: 'success', message });
        },

        removeDocumentInheritance(
            inheritedDocuments: EntityCollection<'product_document'>,
        ): EntityCollection<'product_document'> {
            if (!this.canEdit) {
                return this.documents;
            }

            this.isInheritanceRemoved = true;
            const entityFactory = this.entityFactory as EntityFactory;
            inheritedDocuments.forEach((source) => {
                const document = this.documentRepository.create(Context.api);
                Object.assign(document, cloneDeep(pick(source, documentCopyFields)), { productId: this.product.id });
                source.translations?.forEach((sourceTranslation) => {
                    const translation = entityFactory.create(
                        'product_document_translation',
                        null,
                        Context.api,
                    ) as Entity<'product_document_translation'>;
                    Object.assign(translation, cloneDeep(pick(sourceTranslation, translationCopyFields)), {
                        productDocumentId: document.id,
                        media: sourceTranslation.media,
                    });
                    document.translations.add(translation);
                });
                this.documents.add(document);
            });

            return this.documents;
        },

        onConfirmRestoreInheritance(): void {
            this.showRestoreInheritanceModal = false;
            (this.$refs.documentGrid as DocumentGrid | undefined)?.resetSelection();
            this.selectedDocuments = [];
            this.isInheritanceRemoved = false;
            (this.$refs.documentInheritance as DocumentInheritance).restoreInheritance();
            useSnackbar().addSnackbar({
                variant: 'success',
                message: this.$t('sw-product.documentForm.restoreInheritanceSuccess'),
            });
        },

        isMissingLanguageVersion(document: Entity<'product_document'>): boolean {
            return !this.isSystemDefaultLanguage && !document.title && !document.mediaId;
        },

        getEditActionLabel(document: Entity<'product_document'>): string {
            return this.isMissingLanguageVersion(document)
                ? this.$t('sw-product.documentForm.addLanguageVersion', { language: this.contentLanguageName })
                : this.$t('global.default.edit');
        },
    },
});
