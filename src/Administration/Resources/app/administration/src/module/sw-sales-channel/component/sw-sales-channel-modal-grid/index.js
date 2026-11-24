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

    emits: ['grid-channel-add', 'grid-detail-open'],

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
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-sales-channel-modal-grid.isAgenticCommerceSalesChannelType() is deprecated. Will be removed.',
            );

            return salesChannelTypeId === Defaults.agenticCommerceTypeId;
        },

        /**
         * @private
         * @deprecated tag:v6.8.0 - Will be removed with `isAgenticCommerceSalesChannelType`
         *
         * Calls `isAgenticCommerceSalesChannelType` for core with its deprecation silenced,
         * so an override of it keeps working.
         * Returns `false` with the 6.8 flag, as if the member were removed.
         */
        isAgenticCommerceSalesChannelTypeDeprecated(salesChannelTypeId) {
            if (Shopware.Feature.isActive('V6_8_0_0')) {
                return false;
            }

            return Shopware.Feature.silence('V6_8_0_0', () => this.isAgenticCommerceSalesChannelType(salesChannelTypeId));
        },

        /**
         * @private
         * @deprecated tag:v6.8.0 - Will be removed with `showAgenticCommerceType`
         *
         * Calls `showAgenticCommerceType` for core with its deprecation silenced,
         * so an override of it keeps working.
         * Returns `false` with the 6.8 flag, as if the member were removed.
         */
        showAgenticCommerceTypeDeprecated() {
            if (Shopware.Feature.isActive('V6_8_0_0')) {
                return false;
            }

            return Shopware.Feature.silence('V6_8_0_0', () => this.showAgenticCommerceType());
        },

        isProductComparisonSalesChannelType(salesChannelTypeId) {
            return salesChannelTypeId === Defaults.productComparisonTypeId;
        },

        getTooltip(item) {
            const isDisabledAgenticCommerceType =
                !this.showAgenticCommerceTypeDeprecated() && this.isAgenticCommerceSalesChannelTypeDeprecated(item.id);
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
                (!this.showAgenticCommerceTypeDeprecated() && this.isAgenticCommerceSalesChannelTypeDeprecated(item.id))
            );
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        showAgenticCommerceType() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-sales-channel-modal-grid.showAgenticCommerceType() is deprecated. Will be removed.',
            );

            return !!Shopware.Context.app.config.bundles?.SwagAgenticCommerce;
        },
    },
};
