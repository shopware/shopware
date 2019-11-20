import template from './sw-sales-channel-detail.html.twig';

const { Component } = Shopware;

Component.override('sw-sales-channel-detail', {
    template,

    computed: {
        salesChannelCriteria: function () {
            return this.$super('salesChannelCriteria')
                .addAssociation('themes');
        },
    },
});
