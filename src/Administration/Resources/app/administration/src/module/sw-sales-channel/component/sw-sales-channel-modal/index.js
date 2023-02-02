/**
 * @package sales-channel
 */

import template from './sw-sales-channel-modal.html.twig';
import './sw-sales-channel-modal.scss';

const { Defaults } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            detailType: null,
            productStreamsExist: false,
            productStreamsLoading: false,
        };
    },

    computed: {
        modalTitle() {
            if (this.detailType) {
                return this.$tc('sw-sales-channel.modal.titleDetailPrefix', 0, { name: this.detailType.name });
            }

            return this.$tc('sw-sales-channel.modal.title');
        },

        productStreamRepository() {
            return this.repositoryFactory.create('product_stream');
        },

        addChannelAction() {
            return {
                loading: (salesChannelTypeId) => {
                    return this.isProductComparisonSalesChannelType(salesChannelTypeId) &&
                        this.productStreamsLoading;
                },

                disabled: (salesChannelTypeId) => {
                    return this.isProductComparisonSalesChannelType(salesChannelTypeId) &&
                        !this.productStreamsExist;
                },
            };
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.productStreamsLoading = true;
            this.productStreamRepository.search(new Criteria(1, 1)).then((result) => {
                if (result.total > 0) {
                    this.productStreamsExist = true;
                }
                this.productStreamsLoading = false;
            });
        },

        onGridOpenDetails(detailType) {
            this.detailType = detailType;
        },

        onCloseModal() {
            this.$emit('modal-close');
        },

        onAddChannel(id) {
            this.onCloseModal();

            if (id) {
                this.$router.push({ name: 'sw.sales.channel.create', params: { typeId: id } });
            }
        },

        openRoute(route) {
            this.onCloseModal();

            this.$router.push(route);
        },

        isProductComparisonSalesChannelType(salesChannelTypeId) {
            return salesChannelTypeId === Defaults.productComparisonTypeId;
        },
    },
};
