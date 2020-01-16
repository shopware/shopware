import template from './sw-sales-channel-modal.html.twig';
import './sw-sales-channel-modal.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-sales-channel-modal', {
    template,

    inject: { repositoryFactory: 'repositoryFactory' },

    data() {
        return {
            detailType: null,
            productStreamsExist: false,
            productStreamsLoading: false
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
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.productStreamsLoading = true;
            this.productStreamRepository.search(new Criteria(1, 1), Shopware.Context.api).then((result) => {
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

        isProductComparisonSalesChannelType(salesChannelTypeId) {
            return salesChannelTypeId === 'ed535e5722134ac1aa6524f73e26881b';
        }
    }
});
