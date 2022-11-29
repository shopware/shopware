import template from './sw-customer-base-form.html.twig';
import './sw-customer-base-form.scss';
import errorConfig from '../../error-config.json';

import CUSTOMER from '../../constant/sw-customer.constant';

/**
 * @package customer-order
 */

const { Defaults } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

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

            criteria.addFilter(Criteria.not('or', [
                Criteria.equals('id', Defaults.defaultSalutationId),
            ]));

            return criteria;
        },

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
