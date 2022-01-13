import template from './sw-order-create-initial.html.twig';

const { Component } = Shopware;

Component.register('sw-order-create-initial', {
    template,

    methods: {
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
