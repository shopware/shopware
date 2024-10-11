import template from './sw-order-create-initial.html.twig';

/**
 * @package checkout
 */

const { State, Data, Service } = Shopware;
const { Criteria } = Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['feature'],

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

            if (!this.feature.isActive('v6.7.0.0')) {
                criteria.addAssociation('defaultPaymentMethod');
            }

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            const { customerId } = this.$route.query;

            if (!customerId) {
                return;
            }

            const customer = await this.customerRepository.get(customerId, Shopware.Context.api, this.customerCriteria);
            if (customer) {
                State.commit('swOrder/setCustomer', customer);
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
