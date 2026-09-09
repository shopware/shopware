import template from './sw-customer-base-form.html.twig';
import errorConfig from '../../error-config.json';

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

    inject: [
        'feature',
        'systemConfigApiService',
    ],

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
        'customer.guest'(newVal) {
            if (newVal) {
                this.customer.password = null;
            }
        },
    },

    methods: {
        async createdComponent() {
            // An Administration write carries no sales channel, so the routes read the global values
            // too. A hidden name field is never required, hence both flags have to be on.
            const values = await this.systemConfigApiService.getValues('core.loginRegistration', null);

            const selectable = Boolean(values?.['core.loginRegistration.showAccountTypeSelection']);
            const shown = values?.['core.loginRegistration.showNameFieldsForCompanyAccounts'] ?? true;
            const required = values?.['core.loginRegistration.nameFieldsRequiredForCompanyAccounts'] ?? true;

            this.companyNamesRequired = !selectable || (Boolean(shown) && Boolean(required));
        },

        onSalesChannelChange(salesChannelId) {
            this.$emit('sales-channel-change', salesChannelId);
        },
    },
};
