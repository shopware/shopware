import template from './sw-product-category-form.html.twig';
import './sw-product-category-form.scss';

const { Component, Context } = Shopware;
const { EntityCollection, Criteria } = Shopware.Data;
const { mapPropertyErrors, mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-product-category-form', {
    template,

    inject: ['repositoryFactory'],

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true
        }
    },

    data() {
        return {
            displayVisibilityDetail: false,
            multiSelectVisible: true,
            salesChannel: null
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'localMode',
            'loading'
        ]),

        ...mapGetters('swProductDetail', [
            'isChild',
            'showModeSetting'
        ]),

        ...mapPropertyErrors('product', [
            'tags',
            'active'
        ]),

        hasSelectedVisibilities() {
            if (this.product && this.product.visibilities) {
                return this.product.visibilities.length > 0;
            }
            return false;
        },

        productVisibilityRepository() {
            return this.repositoryFactory.create(this.product.visibilities.entity);
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.salesChannel = new EntityCollection(
                '/sales-channel',
                'sales_channel',
                Shopware.Context.api,
                new Criteria()
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
                const visibilities = this.productVisibilityRepository.create(Context.api);

                Object.assign(visibilities, {
                    productId: this.product.id,
                    productVersionId,
                    salesChannelId,
                    salesChannel,
                    visibility
                });

                this.product.visibilities.push(visibilities);
            });

            this.$refs.productVisibilitiesInheritance.forceInheritanceRemove = true;

            return this.product.visibilities;
        }
    }
});
