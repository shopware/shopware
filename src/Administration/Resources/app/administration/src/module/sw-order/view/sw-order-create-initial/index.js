import template from './sw-order-create-initial.html.twig';

/**
 * @sw-package checkout
 */

const { Store, Data, Service } = Shopware;
const { Criteria } = Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    data() {
        return {
            routeCustomerReady: false,
        };
    },

    computed: {
        customerRepository() {
            return Service('repositoryFactory').create('customer');
        },

        customerCriteria() {
            const criteria = new Criteria(1, 25);
            criteria
                .addAssociation('addresses')
                .addAssociation('group')
                .addAssociation('salutation')
                .addAssociation('salesChannel')
                .addAssociation('lastPaymentMethod')
                .addAssociation('defaultBillingAddress.country')
                .addAssociation('defaultBillingAddress.countryState')
                .addAssociation('defaultBillingAddress.salutation')
                .addAssociation('defaultShippingAddress.country')
                .addAssociation('defaultShippingAddress.countryState')
                .addAssociation('defaultShippingAddress.salutation')
                .addAssociation('tags');

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            const customerId = this.$route.query?.customerId;
            const orderStore = Store.get('swOrder');

            // Reset so grid never sees a stale customer (with or without customerId)
            orderStore.$reset();

            if (!customerId) {
                this.routeCustomerReady = true;
                return;
            }

            try {
                const customer = await this.customerRepository.get(customerId, Shopware.Context.api, this.customerCriteria);
                if (customer) orderStore.setCustomer(customer);
            } finally {
                this.routeCustomerReady = true;
            }
        },

        onCloseCreateModal() {
            this.$nextTick(() => {
                this.$router.push({ name: 'sw.order.index' });
            });
        },

        onPreviewOrder() {
            this.$nextTick(() => {
                this.$router.push({ name: 'sw.order.create.general' });
            });
        },
    },
};
