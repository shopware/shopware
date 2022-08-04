import template from './sw-order-create-initial.html.twig';

const { Component, State } = Shopware;

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
