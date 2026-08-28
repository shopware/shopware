/**
 * @sw-package checkout
 */

import ApiService from 'src/core/service/api.service';
import template from './sw-customer-imitate-customer-modal.html.twig';
import './sw-customer-imitate-customer-modal.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'contextStoreService',
    ],

    emits: ['modal-close'],

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
            imitateCustomerTokens: {},
            isLoading: true,
        };
    },

    computed: {
        modalTitle() {
            return this.$t('sw-customer.imitateCustomerModal.modalTitle', {
                firstname: this.customer.firstName,
                lastname: this.customer.lastName,
            });
        },

        modalDescription() {
            return this.$t('sw-customer.imitateCustomerModal.modalDescription', {
                firstname: this.customer.firstName,
                lastname: this.customer.lastName,
            });
        },

        salesChannelDomainRepository() {
            return this.repositoryFactory.create('sales_channel_domain');
        },

        currentUser() {
            return Shopware.Store.get('session').currentUser;
        },

        salesChannelDomainCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('salesChannel');
            criteria.addFilter(Criteria.equals('salesChannel.typeId', Shopware.Defaults.storefrontSalesChannelTypeId));
            criteria.addFilter(Criteria.equals('salesChannel.active', true));
            criteria.addSorting(Criteria.sort('salesChannel.name', 'ASC'));
            criteria.addSorting(Criteria.sort('languageId', 'DESC'));

            if (this.customer.boundSalesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannelId', this.customer.boundSalesChannelId));
            }

            return criteria;
        },

        hasSalesChannelDomains() {
            return this.salesChannelDomains !== null && this.salesChannelDomains.length > 0;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;

            try {
                await this.fetchSalesChannelDomains();
                await this.fetchImitateCustomerTokens();
            } finally {
                this.isLoading = false;
            }
        },

        onSalesChannelDomainMenuItemClick(salesChannelId, salesChannelDomainUrl) {
            const token = this.imitateCustomerTokens[salesChannelId];

            if (!token) {
                this.createNotificationError({
                    message: this.$t('sw-customer.detail.notificationImitateCustomerErrorMessage'),
                });

                return;
            }

            this.contextStoreService.redirectToSalesChannelUrl(
                salesChannelDomainUrl,
                token,
                this.customer.id,
                this.currentUser?.id,
            );
        },

        onCancel() {
            this.$emit('modal-close');
        },

        fetchSalesChannelDomains() {
            return this.salesChannelDomainRepository
                .search(this.salesChannelDomainCriteria, Shopware.Context.api)
                .then((loadedDomains) => {
                    this.salesChannelDomains = loadedDomains;
                });
        },

        fetchImitateCustomerTokens() {
            const salesChannelIds = [...new Set(this.salesChannelDomains.map((domain) => domain.salesChannelId))];

            return Promise.all(
                salesChannelIds.map((salesChannelId) =>
                    this.contextStoreService
                        .generateImitateCustomerToken(this.customer.id, salesChannelId)
                        .then((response) => {
                            this.imitateCustomerTokens[salesChannelId] = ApiService.handleResponse(response).token;
                        })
                        .catch(() => {}),
                ),
            );
        },
    },
};
