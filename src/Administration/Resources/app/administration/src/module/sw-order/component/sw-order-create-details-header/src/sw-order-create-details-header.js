import template from './sw-order-create-details-header.html.twig';
import { Criteria, getCurrencyFilter } from '../dependencies';

/**
 * @sw-package checkout
 */

/**
 * @deprecated tag:v6.8.0 - will be removed, is not used anymore
 *
 * @public
 */
export default {
    template,

    emits: ['on-select-existing-customer'],

    props: {
        customer: {
            type: Object,
        },

        orderDate: {
            type: String,
            required: true,
        },

        cartPrice: {
            type: Object,
        },

        currency: {
            type: Object,
        },
    },

    data() {
        return {
            showNewCustomerModal: false,
        };
    },

    computed: {
        customerId: {
            get() {
                return this.customer ? this.customer.id : '';
            },

            set(customerId) {
                if (this.customer) this.customer.id = customerId;
            },
        },

        customerCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('defaultBillingAddress.country');

            return criteria;
        },

        currencyFilter() {
            return getCurrencyFilter();
        },
    },

    methods: {
        onSelectExistingCustomer(customerId) {
            // Keep the current value when user tries to delete it
            if (!customerId) {
                return;
            }

            this.$emit('on-select-existing-customer', customerId);
        },

        onShowNewCustomerModal() {
            this.showNewCustomerModal = true;
        },

        onCloseNewCustomerModal() {
            this.showNewCustomerModal = false;
        },
    },
};
