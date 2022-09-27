import template from './sw-customer-login-as-customer-modal.html.twig';
import './sw-customer-login-as-customer-modal.scss';
import ApiService from '../../../../core/service/api.service';

const { Component, Service, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-customer-login-as-customer-modal', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        customer: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            salesChannelDomains: [],
        };
    },

    computed: {
        modalTitle() {
            return this.$tc('sw-customer.loginAsCustomerModal.modalTitle');
        },

        salesChannelDomainRepository() {
            return this.repositoryFactory.create('sales_channel_domain');
        },

        salesChannelDomainCriteria() {
            const criteria = new Criteria();

            if (this.customer && this.customer.boundSalesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannelId', this.customer.boundSalesChannelId));
            }

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.fetchSalesChannelDomains();
        },

        async onSalesChannelDomainMenuItemClick(salesChannelId) {
            await Service('contextStoreService').loginAsCustomerTokenGenerate(
                this.customer.id,
                salesChannelId,
            ).then((response) => {
                const handledResponse = ApiService.handleResponse(response);

                window.open(handledResponse.redirectUrl);
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('sw-customer.detail.notificationLoginAsCustomerErrorMessage'),
                });
            });
        },

        onCancel() {
            this.$emit('modal-close');
        },

        fetchSalesChannelDomains() {
            this.salesChannelDomainRepository.search(
                this.salesChannelDomainCriteria,
                Shopware.Context.api,
            ).then((loadedDomains) => {
                this.salesChannelDomains = loadedDomains;
            });
        },
    },
});
