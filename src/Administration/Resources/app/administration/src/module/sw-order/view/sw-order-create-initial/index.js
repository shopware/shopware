import template from './sw-order-create-initial.html.twig';

/**
 * @package customer-order
 */

const { Component, State } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-order-create-initial', {
    template,

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
});
