import template from './sw-order-create-initial.html.twig';
import RepositoryType from '../../../../core/data/repository.data';

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

    computed: {
        customerRepository(): RepositoryType<'customer'> {
            return this.repositoryFactory.create('customer');
        },
    },

    methods: {
        createdComponent() {
            const { customerId } = this.$route.params;

            if (!customerId) {
                return;
            }

            this.customerRepository.get(customerId).then(response => {
                State.commit('swOrder/setCustomer', response);
            });
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
