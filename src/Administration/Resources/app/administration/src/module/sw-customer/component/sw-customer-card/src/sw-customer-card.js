import template from './sw-customer-card.html.twig';
import './sw-customer-card.scss';
import {
    aclService,
    ApiService,
    contextStoreService,
    Criteria,
    CUSTOMER,
    errorConfig,
    getDecodeIdnEmailFilter,
    mapPropertyErrors,
    notificationMixin,
    repositoryFactoryService,
    salutationMixin,
    useErrorStore,
    useSessionStore,
} from '../dependencies';

/**
 * @sw-package checkout
 */

const { Defaults } = Shopware;

/**
 * @public
 */
export default {
    template,

    inject: [aclService, contextStoreService, repositoryFactoryService],

    mixins: [notificationMixin, salutationMixin],

    props: {
        customer: {
            type: Object,
            required: true,
        },
        title: {
            type: String,
            required: true,
        },
        editMode: {
            type: Boolean,
            required: false,
            default: false,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            showImitateCustomerModal: false,
            showConvertCustomerModal: false,
        };
    },

    computed: {
        hasActionSlot() {
            return !!this.$slots.actions?.[0];
        },

        hasAdditionalDataSlot() {
            return !!this.$slots['data-additional']?.[0];
        },

        hasSummarySlot() {
            return !!this.$slots.summary?.[0];
        },

        moduleColor() {
            if (!this.$route.meta.$module) {
                return '';
            }
            return this.$route.meta.$module.color;
        },

        fullName() {
            const name = {
                name: this.salutation(this.customer),
                company: this.customer.company,
            };

            return Object.values(name)
                .filter((item) => item !== null)
                .join(' - ')
                .trim();
        },

        salutationCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.not('or', [Criteria.equals('id', Defaults.defaultSalutationId)]));

            return criteria;
        },

        ...mapPropertyErrors('customer', [...errorConfig['sw.customer.detail.base'].customer]),

        accountTypeOptions() {
            return [
                {
                    value: CUSTOMER.ACCOUNT_TYPE_PRIVATE,
                    label: this.$t('sw-customer.customerType.labelPrivate'),
                },
                {
                    value: CUSTOMER.ACCOUNT_TYPE_BUSINESS,
                    label: this.$t('sw-customer.customerType.labelBusiness'),
                },
            ];
        },

        isBusinessAccountType() {
            return this.customer?.accountType === CUSTOMER.ACCOUNT_TYPE_BUSINESS;
        },

        canUseCustomerImitation() {
            if (this.customer.guest) {
                return false;
            }

            if (!this.customer.active) {
                return false;
            }

            if (this.customer.boundSalesChannel) {
                if (!this.customer.boundSalesChannel.active) {
                    return false;
                }

                if (this.customer.boundSalesChannel.typeId !== Defaults.storefrontSalesChannelTypeId) {
                    return false;
                }

                if (!this.customer.boundSalesChannel.domains?.length) {
                    return false;
                }
            }

            return this.acl.can('api_proxy_imitate-customer');
        },

        canUseConvertCustomer() {
            return this.customer.guest && this.acl.can('customer.editor');
        },

        customerImitationWarning() {
            if (this.customer.guest) {
                return this.$t('sw-customer.card.tooltipImitateCustomerGuest');
            }

            if (!this.customer.active) {
                return this.$t('sw-customer.card.tooltipImitateCustomerInactive');
            }

            if (this.customer.boundSalesChannel) {
                if (!this.customer.boundSalesChannel.active) {
                    return this.$t('sw-customer.card.tooltipImitateCustomerInactiveSalesChannel');
                }

                if (this.customer.boundSalesChannel.typeId !== Defaults.storefrontSalesChannelTypeId) {
                    return this.$t('sw-customer.card.tooltipImitateCustomerNoStorefront');
                }

                if (!this.customer.boundSalesChannel.domains?.length) {
                    return this.$t('sw-customer.card.tooltipImitateCustomerNoDomain');
                }
            }

            return this.$t('sw-privileges.tooltip.warning');
        },

        hasSingleBoundSalesChannelUrl() {
            return this.customer.boundSalesChannel?.domains?.length === 1;
        },

        currentUser() {
            return useSessionStore().currentUser;
        },

        emailIdnFilter() {
            return getDecodeIdnEmailFilter();
        },
    },

    watch: {
        'customer.accountType'(value) {
            if (value === CUSTOMER.ACCOUNT_TYPE_BUSINESS || !this.customerCompanyError) {
                return;
            }

            useErrorStore().removeApiError(`customer.${this.customer.id}.company`);
        },
    },

    methods: {
        getMailTo(mail) {
            return `mailto:${mail}`;
        },

        async onImitateCustomer() {
            if (this.hasSingleBoundSalesChannelUrl) {
                this.contextStoreService
                    .generateImitateCustomerToken(this.customer.id, this.customer.boundSalesChannel.id)
                    .then((response) => {
                        const handledResponse = ApiService.handleResponse(response);

                        this.contextStoreService.redirectToSalesChannelUrl(
                            this.customer.boundSalesChannel.domains.first().url,
                            handledResponse.token,
                            this.customer.id,
                            this.currentUser?.id,
                        );
                    })
                    .catch(() => {
                        this.createNotificationError({
                            message: this.$t('sw-customer.detail.notificationImitateCustomerErrorMessage'),
                        });
                    });
                return;
            }

            this.showImitateCustomerModal = true;
        },

        onCloseImitateCustomerModal() {
            this.showImitateCustomerModal = false;
        },

        onOpenConvertCustomerModal() {
            this.showConvertCustomerModal = true;
        },

        onCloseConvertCustomerModal() {
            this.showConvertCustomerModal = false;
        },
    },
};
