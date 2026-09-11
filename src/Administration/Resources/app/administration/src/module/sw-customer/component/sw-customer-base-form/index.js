import template from './sw-customer-base-form.html.twig';
import errorConfig from '../../error-config.json';
import companyNamesRequired from '../../helper/company-name-fields.helper';

/**
 * @sw-package checkout
 */

const { Defaults } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();
const { Criteria } = Shopware.Data;
const { CUSTOMER } = Shopware.Constants;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: {
        feature: {},
        // Defaults to null so an extending component that does not provide it still mounts.
        systemConfigApiService: {
            default: null,
        },
    },

    emits: ['sales-channel-change'],

    props: {
        customer: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            // Required until the settings resolve, so a slow request leaves the form strict rather
            // than claiming a field is optional that the routes still reject.
            companyNamesRequired: true,
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        ...mapPropertyErrors('customer', errorConfig['sw.customer.detail.base'].customer),

        salutationCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(
                Criteria.not('or', [
                    Criteria.equals('id', Defaults.defaultSalutationId),
                ]),
            );

            return criteria;
        },

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

        contactPersonRequired() {
            return !this.isBusinessAccountType || this.companyNamesRequired;
        },

        languageCriteria() {
            const criteria = new Criteria(1, 25);

            if (this.customer?.salesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannels.id', this.customer.salesChannelId));
            }

            return criteria;
        },
    },

    watch: {
        'customer.salesChannelId'() {
            // The three settings are switchable per sales channel, so moving the customer to another
            // one can change whether the contact person is required.
            this.createdComponent();
        },
        'customer.guest'(newVal) {
            if (newVal) {
                this.customer.password = null;
            }
        },
    },

    methods: {
        async createdComponent() {
            const salesChannelId = this.customer?.salesChannelId;
            const required = await companyNamesRequired(this.systemConfigApiService, salesChannelId);

            // A slower request for the channel the user has already left must not decide the rule
            // for the one they are on now.
            if (this.customer?.salesChannelId !== salesChannelId) {
                return;
            }

            this.companyNamesRequired = required;
        },

        onSalesChannelChange(salesChannelId) {
            this.$emit('sales-channel-change', salesChannelId);
        },
    },
};
