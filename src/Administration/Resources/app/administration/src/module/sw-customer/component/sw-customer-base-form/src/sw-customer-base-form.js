import template from './sw-customer-base-form.html.twig';
import { Criteria, CUSTOMER, errorConfig, featureService, mapPropertyErrors } from '../dependencies';

/**
 * @sw-package checkout
 */

const { Defaults } = Shopware;

/**
 * @public
 */
export default {
    template,

    inject: [featureService],

    emits: ['sales-channel-change'],

    props: {
        customer: {
            type: Object,
            required: true,
        },
    },

    computed: {
        ...mapPropertyErrors('customer', errorConfig['sw.customer.detail.base'].customer),

        salutationCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.not('or', [Criteria.equals('id', Defaults.defaultSalutationId)]));

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
        onSalesChannelChange(salesChannelId) {
            this.$emit('sales-channel-change', salesChannelId);
        },
    },
};
