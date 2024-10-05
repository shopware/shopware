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

        defaultCriteria() {
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

            try {
                const customer = await this.customerRepository.get(customerId, Shopware.Context.api, this.defaultCriteria);
                State.commit('swOrder/setCustomer', customer);
                // eslint-disable-next-line no-empty
            } catch (error) {}
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
