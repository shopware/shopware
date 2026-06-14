/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-modal-grid.html.twig';
import './sw-sales-channel-modal-grid.scss';

const { Defaults } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    emits: [
        'grid-channel-add',
        'grid-detail-open',
    ],

    props: {
        productStreamsExist: {
            type: Boolean,
            required: false,
            default: true,
        },

        productStreamsLoading: {
            type: Boolean,
            required: false,
            default: false,
        },

        addChannelAction: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            salesChannelTypes: [],
            isLoading: false,
            total: 0,
        };
    },

    computed: {
        /** @deprecated tag:v6.8.0 - Will be removed */
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        salesChannelTypeRepository() {
            return this.repositoryFactory.create('sales_channel_type');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            const context = {
                ...Shopware.Context.api,
                languageId: Shopware.Store.get('session').languageId,
            };
            const criteria = new Criteria(1, 500);

            /**
             * @deprecated tag:v6.8.0 - keep only the type search of the promise callback.
             *
             * Only show the option to create a new agentic commerce sales channel
             * if one had been created using a previous release and still exists
             * OR the SwagAgenticCommerce plugin is installed
             */
            this.showAgenticCommerceType().then((showAgenticCommerceType) => {
                if (!showAgenticCommerceType) {
                    criteria.addFilter(Criteria.not('AND', [Criteria.equals('id', Defaults.agenticCommerceTypeId)]));
                }

                this.salesChannelTypeRepository.search(criteria, context).then((response) => {
                    this.total = response.total;
                    this.salesChannelTypes = response;
                    this.isLoading = false;
                });
            });
        },

        onAddChannel(id) {
            this.$emit('grid-channel-add', id);
        },

        onOpenDetail(id) {
            const detailType = this.salesChannelTypes.find((salesChannelType) => salesChannelType.id === id);
            this.$emit('grid-detail-open', detailType);
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        isAgenticCommerceSalesChannelType(salesChannelTypeId) {
            return salesChannelTypeId === Defaults.agenticCommerceTypeId;
        },

        isProductComparisonSalesChannelType(salesChannelTypeId) {
            return salesChannelTypeId === Defaults.productComparisonTypeId;
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        showAgenticCommerceType() {
            if (Shopware.Context.app.config.bundles?.SwagAgenticCommerce) {
                return Promise.resolve(true);
            }

            const criteria = new Criteria(1, 1);
            criteria.addAssociation('type');
            criteria.addFilter(Criteria.equals('type.id', Defaults.agenticCommerceTypeId));

            return this.salesChannelRepository.searchIds(criteria).then((response) => Promise.resolve(response.total > 0));
        },
    },
};
