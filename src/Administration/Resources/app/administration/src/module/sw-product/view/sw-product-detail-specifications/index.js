/*
 * @sw-package inventory
 */

import Criteria from 'src/core/data/criteria.data';
import template from './sw-product-detail-specifications.html.twig';

const { Context, Mixin, Utils } = Shopware;
const { isEmpty } = Utils.types;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
        'feature',
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            showMediaModal: false,
            showDocumentMediaModal: false,
            documentDefaultFolderId: null,
        };
    },

    computed: {
        product() {
            return Shopware.Store.get('swProductDetail').product;
        },

        parentProduct() {
            return Shopware.Store.get('swProductDetail').parentProduct;
        },

        loading() {
            return Shopware.Store.get('swProductDetail').loading;
        },

        isLoading() {
            return Shopware.Store.get('swProductDetail').isLoading;
        },

        customFieldSets() {
            return Shopware.Store.get('swProductDetail').customFieldSets;
        },

        showModeSetting() {
            return Shopware.Store.get('swProductDetail').showModeSetting;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, use `productType` instead.
         */
        productStates() {
            return Shopware.Store.get('swProductDetail').productStates;
        },

        productType() {
            return Shopware.Store.get('swProductDetail').productType;
        },

        isDigitalProduct() {
            return this.productType === 'digital' || this.productStates.includes('is-download');
        },

        customFieldsExists() {
            return !this.customFieldSets.length <= 0;
        },

        showCustomFieldsCard() {
            return this.showProductCard('custom_fields') && !this.isLoading && this.customFieldsExists;
        },

        productDocumentsFormVisible() {
            return !this.loading.product && !this.loading.parentProduct;
        },

        productDocumentRepository() {
            return this.repositoryFactory.create('product_document');
        },

        productDocuments: {
            get() {
                if (!this.product.productDocuments) {
                    this.product.productDocuments = this.product.getAssociation('productDocuments');
                }

                return this.product.productDocuments;
            },

            set(productDocuments) {
                this.product.productDocuments = productDocuments;
            },
        },

        mediaDefaultFolderRepository() {
            return this.repositoryFactory.create('media_default_folder');
        },

        documentDefaultFolderCriteria() {
            const criteria = new Criteria(1, 1);

            criteria.addAssociation('folder');
            criteria.addFilter(Criteria.equals('entity', 'product_document'));

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getDocumentDefaultFolderId().then((documentDefaultFolderId) => {
                this.documentDefaultFolderId = documentDefaultFolderId;
            });
        },

        showProductCard(key) {
            return Shopware.Store.get('swProductDetail').showProductCard(key);
        },

        getDocumentDefaultFolderId() {
            return this.mediaDefaultFolderRepository
                .search(this.documentDefaultFolderCriteria, Context.api)
                .then((mediaDefaultFolder) => {
                    const defaultFolder = mediaDefaultFolder.first();

                    if (defaultFolder?.folder?.id) {
                        return defaultFolder.folder.id;
                    }

                    return null;
                });
        },

        productDocumentRemoveInheritanceFunction(newValue) {
            this.productDocuments.getIds().forEach((productDocumentId) => {
                this.productDocuments.remove(productDocumentId);
            });

            newValue.forEach(({ media, mediaId, position, title }) => {
                const productDocument = this.productDocumentRepository.create(Context.api);
                Object.assign(productDocument, {
                    media,
                    mediaId,
                    position,
                    productId: this.product.id,
                    title,
                });

                this.productDocuments.add(productDocument);
            });

            this.$refs.productDocumentInheritance.forceInheritanceRemove = true;

            return this.productDocuments;
        },

        productDocumentRestoreInheritanceFunction() {
            this.$refs.productDocumentInheritance.forceInheritanceRemove = false;

            this.productDocuments.getIds().forEach((productDocumentId) => {
                this.productDocuments.remove(productDocumentId);
            });

            return this.productDocuments;
        },

        onOpenDocumentMediaModal() {
            this.showDocumentMediaModal = true;
        },

        onCloseDocumentMediaModal() {
            this.showDocumentMediaModal = false;
        },

        onAddDocuments(media) {
            if (isEmpty(media)) {
                return;
            }

            media.forEach((item) => {
                this.addDocument(item).catch(({ fileName }) => {
                    this.createNotificationError({
                        message: this.$t('sw-product.documentForm.errorDocumentDuplicated', { fileName }, 0),
                    });
                });
            });
        },

        addDocument(media) {
            if (this.isExistingDocument(media)) {
                return Promise.reject(media);
            }

            const productDocument = this.productDocumentRepository.create(Context.api);
            productDocument.productId = this.product.id;
            productDocument.mediaId = media.id;
            productDocument.media = media;
            productDocument.title = null;
            productDocument.position = this.productDocuments.length;

            this.productDocuments.add(productDocument);
            this.updateProductDocumentPositions();

            return Promise.resolve();
        },

        isExistingDocument(media) {
            return this.productDocuments.some(({ id, mediaId }) => {
                return id === media.id || mediaId === media.id;
            });
        },

        updateProductDocumentPositions() {
            this.productDocuments.forEach((productDocument, index) => {
                productDocument.position = index;
            });
        },
    },
};
