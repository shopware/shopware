import template from './sw-product-category-form.html.twig';
import './sw-product-category-form.scss';

const { Component, Context, Mixin } = Shopware;
const { EntityCollection, Criteria } = Shopware.Data;
const { isEmpty } = Shopware.Utils.types;
const { mapPropertyErrors, mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-product-category-form', {
    template,

    inject: ['repositoryFactory', 'systemConfigApiService', 'feature'],

    mixins: [
        Mixin.getByName('notification'),
    ],

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
                new Criteria(),
            );

            if (this.feature.isActive('FEATURE_NEXT_12437')) {
                this.fetchSalesChannelSystemConfig();
            }
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

        fetchSalesChannelSystemConfig() {
            if (!this.product.isNew()) {
                return Promise.reject();
            }

            return this.systemConfigApiService.getValues('core.defaultSalesChannel')
                .then(configData => {
                    if (isEmpty(configData)) {
                        return Promise.resolve();
                    }

                    const defaultSalesChannelIds = configData?.['core.defaultSalesChannel.salesChannel'];
                    const defaultVisibilities = configData?.['core.defaultSalesChannel.visibility'];
                    this.product.active = !!configData?.['core.defaultSalesChannel.active'];

                    return this.fetchSalesChannelByIds(defaultSalesChannelIds).then(res => {
                        if (!res.length) {
                            return Promise.resolve();
                        }

                        res.forEach(el => {
                            const visibilities = this.createProductVisibilityEntity(defaultVisibilities, el);
                            this.product.visibilities.push(visibilities);
                        });

                        return Promise.resolve();
                    });
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-product.visibility.errorMessage'),
                    });
                });
        },

        fetchSalesChannelByIds(ids) {
            const criteria = new Criteria();

            criteria.addFilter(Criteria.equalsAny('id', ids));

            return this.salesChannelRepository.search(criteria);
        },

        createProductVisibilityEntity(visibility, salesChannel) {
            const visibilities = this.productVisibilityRepository.create(Context.api);

            Object.assign(visibilities, {
                visibility: visibility[salesChannel.id],
                productId: this.product.id,
                salesChannelId: salesChannel.id,
                salesChannel: salesChannel,
            });

            return visibilities;
        },
    },
});
