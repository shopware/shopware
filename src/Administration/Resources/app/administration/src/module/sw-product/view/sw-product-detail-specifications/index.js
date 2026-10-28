/*
 * @sw-package inventory
 */

import template from './sw-product-detail-specifications.html.twig';
import './sw-product-detail-specifications.scss';

const { Context } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl', 'feature', 'repositoryFactory'],

    data() {
        return {
            showMediaModal: false,
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

        productDocumentRepository() {
            return this.repositoryFactory.create('product_document');
        },

        productDocumentTranslationRepository() {
            return this.repositoryFactory.create('product_document_translation');
        },
    },

    methods: {
        showProductCard(key) {
            return Shopware.Store.get('swProductDetail').showProductCard(key);
        },

        /**
         * Copies a document of the parent to the variant with every language version.
         * The copy is saved with the product.
         */
        copyParentDocument(parentDocument) {
            const document = this.productDocumentRepository.create(Context.api);
            document.productId = this.product.id;
            document.position = parentDocument.position;

            parentDocument.translations?.forEach(({ languageId, title, mediaId }) => {
                const translation = this.productDocumentTranslationRepository.create(Context.api);
                Object.assign(translation, { languageId, title, mediaId });
                document.translations.add(translation);
            });

            return document;
        },

        /**
         * Unlinking copies the parent's documents to the variant, like images.
         */
        documentsRemoveInheritanceFunction(parentDocuments) {
            parentDocuments.forEach((parentDocument) => {
                this.product.documents.add(this.copyParentDocument(parentDocument));
            });

            this.$refs.productDocumentInheritance.forceInheritanceRemove = true;

            return this.product.documents;
        },

        onDocumentAddBack(parentDocument) {
            const positions = [...this.product.documents].map((document) => document.position ?? 0);
            const document = this.copyParentDocument(parentDocument);
            document.position = Math.max(-1, ...positions) + 1;

            this.product.documents.add(document);
        },

        documentsRestoreInheritanceFunction() {
            this.$refs.productDocumentInheritance.forceInheritanceRemove = false;

            this.product.documents.getIds().forEach((documentId) => {
                this.product.documents.remove(documentId);
            });

            return this.product.documents;
        },
    },
};
