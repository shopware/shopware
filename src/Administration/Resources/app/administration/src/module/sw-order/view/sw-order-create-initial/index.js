import template from './sw-order-create-initial.html.twig';

/**
 * @package checkout
 */

const { State } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const { customer } = this.$route.params;

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
