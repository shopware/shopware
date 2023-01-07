/*
 * @package inventory
 */

import template from './sw-product-modal-delivery.html.twig';
import './sw-product-modal-delivery.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    props: {
        product: {
            type: Object,
            required: true,
        },

        selectedGroups: {
            type: Array,
            required: true,
        },
    },

    data() {
        return {
            activeTab: 'order',
            isLoading: false,
        };
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.product.variantListingConfig) {
                this.$set(
                    this.product,
                    'variantListingConfig',
                    { displayParent: null, configuratorGroupConfig: [], mainVariantId: null },
                );
            }
        },

        saveDeliveryConfiguration() {
            this.isLoading = true;

            // Handle variant listing modes (single, expanded) if exists
            this.product = this.handleExpandedListing(this.product);

            // Save the product after generating
            this.productRepository.save(this.product).then(() => {
                this.$emit('configuration-close');
            });
        },

        cancelDeliveryConfiguration() {
            this.$emit('configuration-close');
        },

        handleExpandedListing(product) {
            if (product && product.listingMode === 'expanded') {
                const configuratorGroupConfig = product.variantListingConfig.configuratorGroupConfig ?? [];

                // remove main_variant_id and display_parent from configuratorGroupConfig
                product.variantListingConfig.mainVariantId = null;
                product.variantListingConfig.displayParent = null;
                product.variantListingConfig.configuratorGroupConfig = configuratorGroupConfig;
            }

            delete product.listingMode;

            return product;
        },
    },
};
