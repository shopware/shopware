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

            this.salesChannelTypeRepository.search(criteria, context).then((response) => {
                this.total = response.total;
                this.salesChannelTypes = response;
                this.isLoading = false;
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

        getTooltip(item) {
            const isDisabledAgenticCommerceType =
                !this.showAgenticCommerceType() && this.isAgenticCommerceSalesChannelType(item.id);
            const messageKey = isDisabledAgenticCommerceType
                ? 'sw-sales-channel.modal.messageAgenticCommerce'
                : 'sw-sales-channel.modal.messageNoProductStreams';

            return {
                message: this.$t(messageKey),
                showOnDisabledElements: true,
                disabled: !this.isDisabled(item),
            };
        },

        isDisabled(item) {
            return (
                this.addChannelAction.disabled(item.id) ||
                (!this.showAgenticCommerceType() && this.isAgenticCommerceSalesChannelType(item.id))
            );
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        showAgenticCommerceType() {
            return !!Shopware.Context.app.config.bundles?.SwagAgenticCommerce;
        },
    },
};
