/**
 * @sw-package inventory
 */
import useSnackbar from 'src/app/composables/use-snackbar';
import type EntityFactory from 'src/core/data/entity-factory.data';
import useNotification from 'src/app/composables/use-notification';
import {
    allowedExtensions,
    maxFileSize,
    getUploadHint,
    getFileAccept,
    isValidDocumentMedia,
    getFallbackTranslation,
    getDocumentTitle,
    getTranslated,
    type DocumentChanges,
    type DocumentTranslated,
    type DocumentVariantOperation,
} from './product-document.helper';
import template from './sw-product-document-form.html.twig';
import './sw-product-document-form.scss';

type DocumentGrid = { resetSelection: () => void };

type DocumentUploadResult = { targetId: EntityKey<'media'>; successAmount?: number; totalAmount?: number };

type DocumentInheritance = { isInherited: boolean; forceInheritanceRemove: boolean; restoreInheritance: () => void };

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
            defaultLanguageName: null as string | null,
            allowedExtensions,
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

        documents(): EntityCollection<'product_document'> {
            return this.product.documents as EntityCollection<'product_document'>;
        },

        pageOffset(): number {
            return (this.page - 1) * this.limit;
        },

        documentRepository() {
            return this.repositoryFactory.create('product_document');
        },

        languageId(): string {
            return Shopware.Store.get('context').api.languageId ?? '';
        },

        isDefaultLanguage(): boolean {
            return Shopware.Store.get('context').isSystemDefaultLanguage;
        },

        uploadTag(): string {
            return `product-documents-${this.product.id}-${this.languageId}`;
        },

        canEdit(): boolean {
            const isLanguageReady =
                this.isDefaultLanguage || Shopware.Store.get('context').api.language?.id === this.languageId;
            return !this.disabled && this.acl.can('product.editor') && isLanguageReady;
        },

        canAddDocuments(): boolean {
            return this.canEdit && this.isSystemDefaultLanguage;
        },

        contentLanguageName(): string {
            const language = Shopware.Store.get('context').api.language as Entity<'language'> | null;
            const code = language?.locale?.code.split('-')[0];
            const locale = Shopware.Store.get('session').currentLocale || 'en-GB';
            const names = new Intl.DisplayNames([locale], { type: 'language' });
            return code ? names.of(code) || '' : language?.name || '';
        },

        hasVariants(): boolean {
            return !this.product.parentId && !!this.product.childCount;
        },

        documentColumns() {
            return columns.map(({ label, ...column }) => ({
                ...column,
                label: label === '#' ? label : this.$t(label),
                sortable: false,
                allowResize: !['number', 'position'].includes(column.property),
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
            return getUploadHint();
        },

        fileAccept(): string {
            return getFileAccept(this.fileValidationService.extensionByType);
        },
    },

    watch: {
        'documents.length'() {
            const lastPage = Math.max(1, Math.ceil(this.documents.length / this.limit));
            this.page = Math.min(this.page, lastPage);
        },

        product: {
            immediate: true,
            handler() {
                [...(this.documents ?? []), ...(this.parentProduct.documents ?? [])].forEach((document) => {
                    const translated = getTranslated(document);
                    translated.media ??= document.translations?.find(
                        (translation) => translation.mediaId === translated.mediaId,
                    )?.media;
                });
                this.page = 1;
                this.showUploadModal = false;
                this.showRestoreInheritanceModal = false;
                this.editingDocument = null;
                this.documentsToRemove = [];
                this.selectedDocuments = [];
            },
        },

        isDefaultLanguage: {
            immediate: true,
            async handler(isDefaultLanguage: boolean) {
                if (isDefaultLanguage || this.defaultLanguageName !== null) {
                    return;
                }

                const { systemLanguageId } = Shopware.Context.api;
                try {
                    const language = await this.repositoryFactory
                        .create('language')
                        .get(systemLanguageId, Shopware.Context.api, {
                            cacheKey: ['shared-data', 'language', systemLanguageId],
                        });
                    this.defaultLanguageName = language?.name || '';
                } catch {
                    this.defaultLanguageName = '';
                }
            },
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

        refreshPositions(documents: EntityCollection<'product_document'>): void {
            documents.forEach((document, index) => {
                document.position = index;
            });
        },

        async onUploadFinished({
            targetId,
            successAmount = 1,
            totalAmount = 1,
        }: {
            targetId: EntityKey<'media'>;
            successAmount?: number;
            totalAmount?: number;
        }): Promise<void> {
            try {
                const media = await this.repositoryFactory.create('media').get(targetId, Shopware.Context.api);
                if (media && this.canChangeDocuments()) {
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

        addDocuments(mediaItems: Entity<'media'>[]): void {
            if (!this.canChangeDocuments() || !this.isDefaultLanguage) {
                return;
            }

            mediaItems
                .filter((media) => isValidDocumentMedia(media, null))
                .forEach((media) => {
                    const document = this.documentRepository.create(Shopware.Context.api);
                    document.productId = this.product.id;
                    document.mediaId = media.id;
                    document.title = media.fileName;
                    document.position = this.documents.length;
                    document.translated = { title: media.fileName, mediaId: media.id, media };
                    this.documents.add(document);
                });
            this.showMediaModal = false;
        },

        moveDocument(document: Entity<'product_document'>, offset: number): void {
            if (!this.canChangeDocuments() || !this.canMoveDocument(document, offset)) {
                return;
            }

            const oldIndex = this.documents.indexOf(document);
            this.documents.moveItem(oldIndex, oldIndex + offset);
            this.refreshPositions(this.documents);
        },

        openDocumentEditor(document: Entity<'product_document'>): void {
            if (this.canChangeDocuments()) {
                this.editingDocument = document;
            }
        },

        getTranslatedValues(
            document: Entity<'product_document'>,
            changes: DocumentChanges,
            media: Entity<'media'> | null,
        ): DocumentTranslated {
            const translated: DocumentTranslated = {};
            if (changes.title !== undefined) {
                translated.title = changes.title ?? getFallbackTranslation(document, 'title')?.title ?? null;
            }
            if (changes.mediaId !== undefined) {
                const fallback = changes.mediaId ? undefined : getFallbackTranslation(document, 'mediaId');
                translated.mediaId = changes.mediaId ?? fallback?.mediaId ?? null;
                translated.media = media ?? fallback?.media ?? null;
            }
            return translated;
        },

        setDocumentValues(document: Entity<'product_document'>, changes: DocumentChanges, media: Entity<'media'> | null) {
            Object.assign(getTranslated(document), this.getTranslatedValues(document, changes, media));
            Object.assign(document, changes);

            const translation = document.translations?.find((entry) => entry.languageId === this.languageId);
            if (translation?.isNew()) {
                Object.assign(
                    translation,
                    changes,
                    changes.mediaId === undefined ? {} : { media: changes.mediaId ? media : null },
                );
            }
        },

        applyDocumentEdit({
            changes,
            media,
            variants,
        }: {
            changes: DocumentChanges;
            media: Entity<'media'> | null;
            variants: { operation: DocumentVariantOperation; count: number } | null;
        }): void {
            const document = this.editingDocument;
            if (!document || !this.canChangeDocuments()) {
                return;
            }

            this.setDocumentValues(document, changes, media);

            if (variants) {
                if (Object.keys(changes).length) {
                    Shopware.Store.get('swProductDetail').documentVariantOperations.push(variants.operation);
                }
                useSnackbar().addSnackbar({
                    variant: 'success',
                    message: this.$t('sw-product.documentForm.applySuccess', {
                        name: getDocumentTitle(document),
                        count: variants.count,
                    }),
                });
            }
            this.editingDocument = null;
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
        },

        removeDocuments({ operations, variantCount }: { operations: DocumentVariantOperation[]; variantCount: number }) {
            const documents = this.documentsToRemove;
            if (!this.canChangeDocuments() || !documents.length) {
                return;
            }

            const name = getDocumentTitle(documents[0]);
            documents.forEach((document) => {
                if (this.isDefaultLanguage) {
                    this.documents.remove(document.id);
                } else {
                    this.setDocumentValues(document, { title: null, mediaId: null }, null);
                }
            });
            if (this.isDefaultLanguage) {
                this.refreshPositions(this.documents);
            }
            Shopware.Store.get('swProductDetail').documentVariantOperations.push(...operations);

            const language = this.contentLanguageName;
            let message: string;
            if (documents.length > 1) {
                message = this.$t(
                    this.isDefaultLanguage
                        ? 'sw-product.documentForm.bulkRemoveSuccess'
                        : 'sw-product.documentForm.bulkRemoveLanguageVersionSuccess',
                    { count: documents.length, language },
                );
            } else {
                message = this.isDefaultLanguage
                    ? this.$t('sw-product.documentForm.removeSuccess', { name, count: variantCount })
                    : this.$t('sw-product.documentForm.removeLanguageVersionSuccess', { language, name });
            }
            useSnackbar().addSnackbar({ variant: 'success', message });
            (this.$refs.documentGrid as DocumentGrid | undefined)?.resetSelection();
            this.selectedDocuments = [];
            this.documentsToRemove = [];
        },

        removeDocumentInheritance(
            inheritedDocuments: EntityCollection<'product_document'>,
        ): EntityCollection<'product_document'> {
            if (!this.canEdit) {
                return this.documents;
            }

            (this.$refs.documentInheritance as DocumentInheritance).forceInheritanceRemove = true;
            const entityFactory = this.entityFactory as EntityFactory;
            inheritedDocuments.forEach((source) => {
                const document = this.documentRepository.create(Shopware.Context.api);
                document.productId = this.product.id;
                document.position = source.position;
                document.title = source.title;
                document.mediaId = source.mediaId;
                document.customFields = Shopware.Utils.object.cloneDeep(source.customFields);
                document.translated = { ...source.translated };

                source.translations?.forEach((sourceTranslation) => {
                    const translation = entityFactory.create(
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

        onConfirmRestoreInheritance(): void {
            this.showRestoreInheritanceModal = false;
            (this.$refs.documentGrid as DocumentGrid | undefined)?.resetSelection();
            this.selectedDocuments = [];
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

        getRemoveActionLabel(): string {
            return this.isSystemDefaultLanguage
                ? this.$t('global.default.remove')
                : this.$t('sw-product.documentForm.removeLanguageVersion', { language: this.contentLanguageName });
        },
    },
});
