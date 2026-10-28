/**
 * @sw-package inventory
 */
import Criteria from '@shopware-ag/meteor-admin-sdk/es/data/Criteria';
import template from './sw-product-document-form.html.twig';
import './sw-product-document-form.scss';
import type Repository from '../../../../core/data/repository.data';

const { Mixin } = Shopware;
const { format } = Shopware.Utils;

type ProductDocument = Entity<'product_document'>;
type DocumentTranslation = Entity<'product_document_translation'>;
type Media = Entity<'media'>;
type VersionField = 'title' | 'mediaId';

// Limits of the epic; the validation issue moves them into the server config.
const MAX_DOCUMENTS = 10;
const MAX_FILE_SIZE = 20 * 1024 * 1024;

/**
 * @private
 * @description Documents card of the product detail page: one list per product,
 * with a title and a file per language. Other languages fall back to the default language.
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        isInherited: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    // A removed document of the parent goes back to an unlinked variant as a copy with all language versions.
    emits: ['add-back'],

    data(): {
        addedMedia: Record<string, Media>;
        showMediaModal: boolean;
        replaceDocumentId: string | null;
        defaultFolderId: string | null;
    } {
        return {
            addedMedia: {},
            showMediaModal: false,
            replaceDocumentId: null,
            defaultFolderId: null,
        };
    },

    computed: {
        product(): Entity<'product'> | null {
            const store = Shopware.Store.get('swProductDetail');

            return (this.isInherited ? store.parentProduct : store.product) as Entity<'product'> | null;
        },

        documents(): EntityCollection<'product_document'> | null {
            return this.product?.documents ?? null;
        },

        sortedDocuments(): ProductDocument[] {
            return [...(this.documents ?? [])].sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
        },

        mediaRepository(): Repository<'media'> {
            return this.repositoryFactory.create('media');
        },

        productDocumentRepository(): Repository<'product_document'> {
            return this.repositoryFactory.create('product_document');
        },

        mediaDefaultFolderRepository(): Repository<'media_default_folder'> {
            return this.repositoryFactory.create('media_default_folder');
        },

        contentLanguageId(): string {
            return Shopware.Context.api.languageId ?? '';
        },

        isDefaultLanguage(): boolean {
            return Shopware.Context.api.languageId === Shopware.Context.api.systemLanguageId;
        },

        canEdit(): boolean {
            return !this.disabled && this.acl.can('product.editor');
        },

        isAtLimit(): boolean {
            return this.sortedDocuments.length >= MAX_DOCUMENTS;
        },

        canAdd(): boolean {
            return this.canEdit && this.isDefaultLanguage && !this.isAtLimit;
        },

        maxDocuments(): number {
            return MAX_DOCUMENTS;
        },

        maxFileSize(): number {
            return MAX_FILE_SIZE;
        },

        uploadTag(): string {
            return `product-documents-${this.product?.id ?? ''}`;
        },

        /**
         * Files of every loaded language version, of the parent too: copies on a variant
         * point to the parent's files before they are saved.
         */
        knownMedia(): Record<string, Media> {
            const store = Shopware.Store.get('swProductDetail');
            const media: Record<string, Media> = {};

            [
                store.parentProduct,
                store.product,
            ].forEach((product) => {
                (product as Entity<'product'> | null)?.documents?.forEach((document) => {
                    document.translations?.forEach((translation) => {
                        if (translation.media) {
                            media[translation.media.id] = translation.media;
                        }
                    });
                });
            });

            return { ...media, ...this.addedMedia };
        },

        isUnlinkedVariant(): boolean {
            return !this.isInherited && !!this.product?.parentId;
        },

        parentDocuments(): ProductDocument[] {
            const parentProduct = Shopware.Store.get('swProductDetail').parentProduct as Entity<'product'> | null;

            return [...(parentProduct?.documents ?? [])].sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
        },

        parentDefaults(): Record<VersionField, string[]> {
            return this.defaultsOf(this.parentDocuments);
        },

        /** Documents of the parent an unlinked variant doesn't have. They are listed after its own ones. */
        removedDocuments(): ProductDocument[] {
            if (!this.isUnlinkedVariant) {
                return [];
            }

            const ownDefaults = this.defaultsOf(this.sortedDocuments);

            return this.parentDocuments.filter((document) => !this.matches(document, ownDefaults));
        },

        rows(): ProductDocument[] {
            return [
                ...this.sortedDocuments,
                ...this.removedDocuments,
            ];
        },

        columns(): { property: string; label: string; primary?: boolean; sortable: boolean; width?: string }[] {
            return [
                {
                    property: 'title',
                    label: this.$t('sw-product.documentForm.columnTitle'),
                    primary: true,
                    sortable: false,
                    width: '300px',
                },
                { property: 'file', label: this.$t('sw-product.documentForm.columnFile'), sortable: false },
                { property: 'type', label: this.$t('sw-product.documentForm.columnType'), sortable: false },
                { property: 'size', label: this.$t('sw-product.documentForm.columnSize'), sortable: false },
                { property: 'uploadedAt', label: this.$t('sw-product.documentForm.columnUploadedAt'), sortable: false },
            ];
        },
    },

    created(): void {
        this.createdComponent();
    },

    methods: {
        createdComponent(): void {
            void this.loadDefaultFolderId();
        },

        async loadDefaultFolderId(): Promise<void> {
            const criteria = new Criteria(1, 1);
            criteria.addAssociation('folder');
            criteria.addFilter(Criteria.equals('entity', 'product_document'));

            const result = await this.mediaDefaultFolderRepository.search(criteria);
            this.defaultFolderId = result.first()?.folder?.id ?? null;
        },

        translationOf(document: ProductDocument, languageId: string): DocumentTranslation | undefined {
            return document.translations?.find((translation) => translation.languageId === languageId);
        },

        /**
         * Holds the document's own values in the content language: the API reads and writes the fields
         * in that language. Copies on a variant keep the versions taken over from the parent
         * in `translations` until they are saved. `null` clears a value, so the default language applies.
         */
        versionOf(document: ProductDocument): Partial<Record<VersionField, string | null>> {
            const copiedVersion = document.isNew() ? this.translationOf(document, this.contentLanguageId) : undefined;

            return copiedVersion ?? document;
        },

        ownValue(document: ProductDocument, field: VersionField): string | null {
            return this.versionOf(document)[field] || null;
        },

        defaultValue(document: ProductDocument, field: VersionField): string | null {
            const systemLanguageId = Shopware.Context.api.systemLanguageId ?? '';

            return (
                this.translationOf(document, systemLanguageId)?.[field] ||
                (document.translated?.[field] as string | undefined) ||
                null
            );
        },

        /** The value shoppers get in the content language: its own version, else the default language's. */
        effectiveValue(document: ProductDocument, field: VersionField): string | null {
            return this.ownValue(document, field) ?? this.defaultValue(document, field);
        },

        isFallback(document: ProductDocument, field: VersionField): boolean {
            return !this.isDefaultLanguage && this.ownValue(document, field) === null;
        },

        defaultLanguageValue(document: ProductDocument, field: VersionField): string | null {
            return this.isDefaultLanguage ? this.effectiveValue(document, field) : this.defaultValue(document, field);
        },

        defaultsOf(documents: ProductDocument[]): Record<VersionField, string[]> {
            const valuesOf = (field: VersionField) =>
                documents
                    .map((document) => this.defaultLanguageValue(document, field))
                    .filter((value): value is string => value !== null);

            return { title: valuesOf('title'), mediaId: valuesOf('mediaId') };
        },

        /**
         * Without a link between a copy and its parent document, documents of a variant and its parent
         * match when they share their default-language title or file.
         */
        matches(document: ProductDocument, defaults: Record<VersionField, string[]>): boolean {
            return (['title', 'mediaId'] as VersionField[]).some((field) =>
                defaults[field].includes(this.defaultLanguageValue(document, field) ?? ''),
            );
        },

        isVariantOnly(document: ProductDocument): boolean {
            return this.isUnlinkedVariant && !this.matches(document, this.parentDefaults);
        },

        isRemoved(document: ProductDocument): boolean {
            return this.removedDocuments.some((removed) => removed.id === document.id);
        },

        setValue(document: ProductDocument, field: VersionField, value: string | null): void {
            this.versionOf(document)[field] = value;
        },

        mediaOf(document: ProductDocument): Media | null {
            const mediaId = this.effectiveValue(document, 'mediaId');

            return mediaId ? (this.knownMedia[mediaId] ?? null) : null;
        },

        fileName(media: Media | null): string {
            if (!media) {
                return '';
            }

            return media.fileExtension ? `${media.fileName}.${media.fileExtension}` : (media.fileName ?? '');
        },

        fileType(media: Media | null): string {
            return media?.fileExtension?.toUpperCase() ?? '';
        },

        fileSize(media: Media | null): string {
            return media?.fileSize ? format.fileSize(media.fileSize) : '';
        },

        uploadedAt(media: Media | null): string {
            const date = media?.uploadedAt ?? media?.createdAt;

            return date ? format.date(date) : '';
        },

        onTitleInput(document: ProductDocument, value: string): void {
            this.setValue(document, 'title', value || null);
        },

        onTitleBlur(document: ProductDocument): void {
            if (!this.isDefaultLanguage || this.ownValue(document, 'title') !== null) {
                return;
            }

            this.setValue(document, 'title', this.mediaOf(document)?.fileName ?? null);
        },

        onMove(document: ProductDocument, offset: number): void {
            const ordered = this.sortedDocuments;
            const from = ordered.indexOf(document);
            const to = from + offset;

            if (to < 0 || to >= ordered.length) {
                return;
            }

            ordered.splice(to, 0, ...ordered.splice(from, 1));
            ordered.forEach((item, index) => {
                item.position = index;
            });
        },

        onRemove(document: ProductDocument): void {
            this.documents?.remove(document.id);
            this.sortedDocuments.forEach((item, index) => {
                item.position = index;
            });
        },

        openMediaModal(document: ProductDocument | null = null): void {
            this.replaceDocumentId = document?.id ?? null;
            this.showMediaModal = true;
        },

        closeMediaModal(): void {
            this.showMediaModal = false;
            this.replaceDocumentId = null;
        },

        async onUploadFinish({ targetId }: { targetId: string }): Promise<void> {
            const media = await this.mediaRepository.get(targetId as EntityKey<'media'>);
            if (media) {
                this.addDocument(media);
            }
        },

        onMediaSelection(selection: Media[]): void {
            const replaced = this.documents?.get(this.replaceDocumentId ?? '');
            if (!replaced) {
                selection.forEach((media) => this.addDocument(media));

                return;
            }

            const [media] = selection;
            if (!media || this.rejectsDuplicate(media, replaced)) {
                return;
            }

            this.addedMedia = { ...this.addedMedia, [media.id]: media };
            this.setValue(replaced, 'mediaId', media.id);
        },

        addDocument(media: Media): void {
            if (!this.canAdd || !this.documents || !this.product || this.rejectsDuplicate(media)) {
                return;
            }

            this.addedMedia = { ...this.addedMedia, [media.id]: media };

            const lastDocument = this.sortedDocuments[this.sortedDocuments.length - 1];
            const document = this.productDocumentRepository.create();
            document.productId = this.product.id;
            document.position = (lastDocument?.position ?? -1) + 1;
            document.title = media.fileName;
            document.mediaId = media.id;

            this.documents.add(document);
        },

        /** A file can be the document of a product only once, in each language. */
        rejectsDuplicate(media: Media, replaced: ProductDocument | null = null): boolean {
            const isDuplicate = this.sortedDocuments.some(
                (document) => document.id !== replaced?.id && this.effectiveValue(document, 'mediaId') === media.id,
            );

            if (isDuplicate) {
                this.createNotificationError({
                    message: this.$t('sw-product.documentForm.errorDuplicate', { fileName: this.fileName(media) }),
                });
            }

            return isDuplicate;
        },
    },
});
