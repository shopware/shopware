/*
 * @sw-package inventory
 */

import template from './sw-product-category-form.html.twig';
import './sw-product-category-form.scss';
import {
    Criteria,
    EntityCollection,
    featureService,
    getApiContext,
    mapPropertyErrors,
    notificationMixin,
    repositoryFactoryService,
    systemConfigApiService,
    useProductDetailStore,
} from '../dependencies';

/**
 * @public
 */
export default {
    template,

    inject: [repositoryFactoryService, systemConfigApiService, featureService],

    mixins: [notificationMixin],

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            displayVisibilityDetail: false,
            multiSelectVisible: true,
            salesChannel: null,
            defaultVisibility: 30,
        };
    },

    computed: {
        product() {
            return useProductDetailStore().product;
        },

        parentProduct() {
            return useProductDetailStore().parentProduct;
        },

        loading() {
            return useProductDetailStore().loading;
        },

        isChild() {
            return useProductDetailStore().isChild;
        },

        showModeSetting() {
            return useProductDetailStore().showModeSetting;
        },

        ...mapPropertyErrors('product', ['tags', 'active']),

        hasSelectedVisibilities() {
            if (this.product && this.product.visibilities) {
                return this.product.visibilities.length > 0;
            }
            return false;
        },

        productVisibilityRepository() {
            return this.repositoryFactory.create(this.product.visibilities.entity);
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.salesChannel = new EntityCollection(
                '/sales-channel',
                'sales_channel',
                getApiContext(),
                new Criteria(1, 25),
            );
        },

        displayAdvancedVisibility() {
            this.displayVisibilityDetail = true;
        },

        closeAdvancedVisibility() {
            this.displayVisibilityDetail = false;
        },

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
    },
};
