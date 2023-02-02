/*
 * @package inventory
 */

import template from './sw-product-category-form.html.twig';
import './sw-product-category-form.scss';

const { Context, Mixin } = Shopware;
const { EntityCollection, Criteria } = Shopware.Data;
const { mapPropertyErrors, mapState, mapGetters } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'systemConfigApiService', 'feature'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
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
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'localMode',
            'loading',
        ]),

        ...mapGetters('swProductDetail', [
            'isChild',
            'showModeSetting',
        ]),

        ...mapPropertyErrors('product', [
            'tags',
            'active',
        ]),

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
                Shopware.Context.api,
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
                const visibilities = this.productVisibilityRepository.create(Context.api);

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
