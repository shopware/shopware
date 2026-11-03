/**
 * @sw-package inventory
 */
import template from './sw-bulk-edit-product-visibility.html.twig';

import { getApiContext, repositoryFactoryService, useProductDetailStore } from '../dependencies';

/**
 * @public
 */
export default {
    template,

    inject: [repositoryFactoryService],

    props: {
        bulkEditProduct: {
            type: Object,
            required: true,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            displayVisibilityDetail: false,
        };
    },

    computed: {
        product() {
            return useProductDetailStore().product;
        },

        productVisibilityRepository() {
            return this.repositoryFactory.create(this.product.visibilities.entity);
        },
    },

    methods: {
        visibilitiesRemoveInheritanceFunction(newValue) {
            newValue.forEach(({ productVersionId, salesChannelId, salesChannel, visibility }) => {
                const visibilities = this.productVisibilityRepository.create(getApiContext());

                Object.assign(visibilities, {
                    productId: this.product.id,
                    productVersionId,
                    salesChannelId,
                    salesChannel,
                    visibility,
                });

                this.product.visibilities.push(visibilities);
            });

            this.$refs.productVisibilitiesInheritance.forceInheritanceRemove = true;

            return this.product.visibilities;
        },
        openModal() {
            this.displayVisibilityDetail = true;
        },

        closeModal() {
            this.displayVisibilityDetail = false;
        },
    },
};
