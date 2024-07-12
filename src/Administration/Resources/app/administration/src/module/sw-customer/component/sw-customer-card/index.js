import template from './sw-customer-card.html.twig';
import './sw-customer-card.scss';
import errorConfig from '../../error-config.json';
import CUSTOMER from '../../constant/sw-customer.constant';
import ApiService from '../../../../core/service/api.service';

/**
 * @package checkout
 */

const { Mixin, Defaults } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
        'contextStoreService',
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('salutation'),
    ],

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

            return Object.values(name).filter(item => item !== null).join(' - ').trim();
        },

        salutationCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.not('or', [
                Criteria.equals('id', Defaults.defaultSalutationId),
            ]));

            return criteria;
        },

        ...mapPropertyErrors(
            'customer',
            [...errorConfig['sw.customer.detail.base'].customer],
        ),

        accountTypeOptions() {
            return [{
                value: CUSTOMER.ACCOUNT_TYPE_PRIVATE, label: this.$tc('sw-customer.customerType.labelPrivate'),
            }, {
                value: CUSTOMER.ACCOUNT_TYPE_BUSINESS, label: this.$tc('sw-customer.customerType.labelBusiness'),
            }];
        },

        isBusinessAccountType() {
            return this.customer?.accountType === CUSTOMER.ACCOUNT_TYPE_BUSINESS;
        },

        canUseCustomerImitation() {
            if (this.customer.boundSalesChannel) {
                if (this.customer.boundSalesChannel.typeId !== Defaults.storefrontSalesChannelTypeId) {
                    return false;
                }

                if (!this.customer.boundSalesChannel.domains?.length) {
                    return false;
                }
            }

            return this.acl.can('api_proxy_imitate-customer');
        },

        hasSingleBoundSalesChannelUrl() {
            return this.customer.boundSalesChannel?.domains?.length === 1;
        },

        currentUser() {
            return Shopware.State.get('session').currentUser;
        },
    },

    watch: {
        'customer.accountType'(value) {
            if (value === CUSTOMER.ACCOUNT_TYPE_BUSINESS || !this.customerCompanyError) {
                return;
            }

            Shopware.State.dispatch(
                'error/removeApiError',
                {
                    expression: `customer.${this.customer.id}.company`,
                },
            );
        },
    },

    methods: {
        getMailTo(mail) {
            return `mailto:${mail}`;
        },

        async onImitateCustomer() {
            if (this.hasSingleBoundSalesChannelUrl) {
                this.contextStoreService.generateImitateCustomerToken(
                    this.customer.id,
                    this.customer.boundSalesChannel.id,
                ).then((response) => {
                    const handledResponse = ApiService.handleResponse(response);

                    this.contextStoreService.redirectToSalesChannelUrl(
                        this.customer.boundSalesChannel.domains.first().url,
                        handledResponse.token,
                        this.customer.id,
                        this.currentUser?.id,
                    );
                }).catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-customer.detail.notificationImitateCustomerErrorMessage'),
                    });
                });
                return;
            }

            this.showImitateCustomerModal = true;
        },

        onCloseImitateCustomerModal() {
            this.showImitateCustomerModal = false;
        },
    },
};
