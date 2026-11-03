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

        documentColumns() {
            return [
                { property: 'number', label: '#', width: '56px', align: 'center' },
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
    },
});
