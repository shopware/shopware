import template from './sw-sales-channel-modal-grid.html.twig';

const { Component } = Shopware;

Component.register('sw-sales-channel-modal-grid', {
    template,

    props: {
        salesChannelTypes: {
            type: Array,
            required: true
        }
    },

    methods: {
        onOpenDetail(id) {
            const detailType = this.salesChannelTypes.find(a => a.id === id);
            this.$emit('sw-sales-channel-modal-grid-select-detail', detailType);
        }
    }
});
