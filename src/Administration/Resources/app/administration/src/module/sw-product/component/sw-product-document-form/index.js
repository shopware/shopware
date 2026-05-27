/*
 * @sw-package inventory
 */

import template from './sw-product-document-form.html.twig';
import './sw-product-document-form.scss';

const { Context } = Shopware;
const { format } = Shopware.Utils;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'configService',
    ],

    emits: ['media-open'],

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

    data() {
        return {
            isMediaLoading: false,
            fileAcceptedExtensions: [],
        };
    },

    computed: {
        product() {
            const state = Shopware.Store.get('swProductDetail');

            if (this.isInherited) {
                return state.parentProduct;
            }

            return state.product;
        },

        isStoreLoading() {
            return Shopware.Store.get('swProductDetail').isLoading;
        },

        isLoading() {
            return this.isMediaLoading || this.isStoreLoading;
        },

        productDocumentRepository() {
            return this.repositoryFactory.create('product_document');
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        productDocuments() {
            if (!this.product) {
                return [];
            }

            if (!this.product.productDocuments) {
                this.product.productDocuments = this.product.getAssociation('productDocuments');
            }

            return this.product.productDocuments;
        },

        hasDocuments() {
            return this.productDocuments.length > 0;
        },

        uploadTag() {
            return `product-documents-${this.product.id}`;
        },

        fileAccept() {
            return this.fileAcceptedExtensions.join(', ');
        },

        documentColumns() {
            return [
                {
                    property: 'title',
                    dataIndex: 'title',
                    label: this.$t('sw-product.documentForm.columnTitle'),
                    primary: true,
                    sortable: false,
                },
                {
                    property: 'fileName',
                    dataIndex: 'media.fileName',
                    label: this.$t('sw-product.documentForm.columnFileName'),
                    sortable: false,
                },
                {
                    property: 'fileType',
                    dataIndex: 'media.fileExtension',
                    label: this.$t('sw-product.documentForm.columnFileType'),
                    sortable: false,
                },
                {
                    property: 'fileSize',
                    dataIndex: 'media.fileSize',
                    label: this.$t('sw-product.documentForm.columnFileSize'),
                    sortable: false,
                },
                {
                    property: 'uploadedAt',
                    dataIndex: 'media.uploadedAt',
                    label: this.$t('sw-product.documentForm.columnUploadedAt'),
                    sortable: false,
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.configService.getConfig().then((result) => {
                this.fileAcceptedExtensions = result.settings.private_allowed_extensions;
            });
        },

        onOpenMedia() {
            this.$emit('media-open');
        },

        getFileSize(productDocument) {
            if (!productDocument.media?.fileSize) {
                return null;
            }

            return format.fileSize(productDocument.media.fileSize);
        },

        getFileName(productDocument) {
            if (!productDocument.media) {
                return '';
            }

            if (productDocument.media.fileExtension) {
                return `${productDocument.media.fileName}.${productDocument.media.fileExtension}`;
            }

            return productDocument.media.fileName;
        },

        getFileType(productDocument) {
            return productDocument.media?.fileExtension || productDocument.media?.mimeType || null;
        },

        createdAt(productDocument) {
            const date = productDocument.media?.uploadedAt || productDocument.media?.createdAt;

            if (!date) {
                return null;
            }

            return format.date(date, {
                month: 'numeric',
            });
        },

        onChangeTitle(productDocument, title) {
            productDocument.title = title || null;
        },

        onRemoveDocument(productDocument) {
            this.productDocuments.remove(productDocument.id);
            this.updateDocumentPositions();
        },

        async successfulUpload({ targetId }) {
            if (this.isExistingDocument(targetId)) {
                return;
            }

            const productDocument = this.createDocumentAssociation(targetId);
            productDocument.media = await this.mediaRepository.get(targetId, Context.api);

            this.productDocuments.add(productDocument);
            this.updateDocumentPositions();
        },

        createDocumentAssociation(targetId) {
            const productDocument = this.productDocumentRepository.create(Context.api);

            productDocument.productId = this.product.id;
            productDocument.mediaId = targetId;
            productDocument.title = null;
            productDocument.position = this.productDocuments.length;

            return productDocument;
        },

        onUploadFailed() {
            this.isMediaLoading = false;
        },

        onMoveDocument(productDocument, offset) {
            const oldIndex = this.productDocuments.indexOf(productDocument);
            const newIndex = oldIndex + offset;

            if (newIndex < 0 || newIndex >= this.productDocuments.length) {
                return;
            }

            this.productDocuments.moveItem(oldIndex, newIndex);
            this.updateDocumentPositions();
        },

        isFirstDocument(productDocument) {
            return this.productDocuments.indexOf(productDocument) === 0;
        },

        isLastDocument(productDocument) {
            return this.productDocuments.indexOf(productDocument) === this.productDocuments.length - 1;
        },

        isExistingDocument(mediaId) {
            return this.productDocuments.some((productDocument) => productDocument.mediaId === mediaId);
        },

        updateDocumentPositions() {
            this.productDocuments.forEach((productDocument, index) => {
                productDocument.position = index;
            });
        },
    },
};
