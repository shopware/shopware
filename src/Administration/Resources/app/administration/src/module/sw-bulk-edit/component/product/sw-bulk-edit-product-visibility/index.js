/**
 * @package system-settings
 */
import template from './sw-bulk-edit-product-visibility.html.twig';

const { Context } = Shopware;
const { mapState } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

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
        ...mapState('swProductDetail', [
            'product',
        ]),

        productVisibilityRepository() {
            return this.repositoryFactory.create(this.product.visibilities.entity);
        },
    },

    methods: {
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
        openModal() {
            this.displayVisibilityDetail = true;
        },

        closeModal() {
            this.displayVisibilityDetail = false;
        },
    },
};
