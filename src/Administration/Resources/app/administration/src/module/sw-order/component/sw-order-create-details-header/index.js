import template from './sw-order-create-details-header.html.twig';

/**
 * @package customer-order
 */

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        // FIXME: add required attribute and or default value
        // eslint-disable-next-line vue/require-default-prop
        customer: {
            type: Object,
        },

        orderDate: {
            type: String,
            required: true,
        },

        // FIXME: add required attribute and or default value
        // eslint-disable-next-line vue/require-default-prop
        cartPrice: {
            type: Object,
        },

        // FIXME: add required attribute and or default value
        // eslint-disable-next-line vue/require-default-prop
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
