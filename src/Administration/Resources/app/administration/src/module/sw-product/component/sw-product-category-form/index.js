import template from './sw-product-category-form.html.twig';
import './sw-product-category-form.scss';

const { Context, Mixin } = Shopware;
const { EntityCollection, Criteria } = Shopware.Data;
const { isEmpty } = Shopware.Utils.types;
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


        // @deprecated tag:v6.5.0 - Will be removed
        fetchSalesChannelSystemConfig() {
            if (typeof this.product.isNew !== 'function' || !this.product.isNew()) {
                return Promise.resolve();
            }

            return this.systemConfigApiService.getValues('core.defaultSalesChannel').then(async (configData) => {
                if (isEmpty(configData)) {
                    return;
                }

                const defaultSalesChannelIds = configData?.['core.defaultSalesChannel.salesChannel'];
                const defaultVisibilities = configData?.['core.defaultSalesChannel.visibility'];
                this.product.active = !!configData?.['core.defaultSalesChannel.active'];

                if (!defaultSalesChannelIds || defaultSalesChannelIds.length <= 0) {
                    return;
                }

                const salesChannels = await this.fetchSalesChannelByIds(defaultSalesChannelIds);

                if (!salesChannels.length) {
                    return;
                }

                salesChannels.forEach((salesChannel) => {
                    const visibilities = this.createProductVisibilityEntity(defaultVisibilities, salesChannel);
                    this.product.visibilities.push(visibilities);
                });
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('sw-product.visibility.errorMessage'),
                });
            });
        },

        // @deprecated tag:v6.5.0 - Will be removed
        fetchSalesChannelByIds(ids) {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equalsAny('id', ids));

            return this.salesChannelRepository.search(criteria);
        },

        // @deprecated tag:v6.5.0 - Will be removed
        createProductVisibilityEntity(visibility, salesChannel) {
            const visibilities = this.productVisibilityRepository.create(Context.api);

            Object.assign(visibilities, {
                visibility: visibility[salesChannel.id] || this.defaultVisibility,
                productId: this.product.id,
                salesChannelId: salesChannel.id,
                salesChannel: salesChannel,
            });

            return visibilities;
        },
    },
};
