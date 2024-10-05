import template from './sw-order-create-initial.html.twig';

/**
 * @package checkout
 */

const { State } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['repositoryFactory'],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const customerRepository = this.repositoryFactory.create('customer');
            const { customer, customerId }     = this.$route.params;

            if(customerId) {
                customerRepository.get(customerId).then(response => {
                    State.commit('swOrder/setCustomer', response);
                });
            }

            if (!customer) {
                return;
            }

            State.commit('swOrder/setCustomer', customer);
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
