/**
 * @package sales-channel
 */

import template from './sw-sales-channel-modal-grid.html.twig';
import './sw-sales-channel-modal-grid.scss';

const { Defaults } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    props: {
        productStreamsExist: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
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
            const context = { ...Shopware.Context.api, languageId: Shopware.State.get('session').languageId };
            this.salesChannelTypeRepository.search(new Criteria(1, 500), context).then((response) => {
                this.total = response.total;
                this.salesChannelTypes = response;
                this.isLoading = false;
            });
        },

        onAddChannel(id) {
            this.$emit('grid-channel-add', id);
        },

        onOpenDetail(id) {
            const detailType = this.salesChannelTypes.find(salesChannelType => salesChannelType.id === id);
            this.$emit('grid-detail-open', detailType);
        },

        isProductComparisonSalesChannelType(salesChannelTypeId) {
            return salesChannelTypeId === Defaults.productComparisonTypeId;
        },
    },
};
