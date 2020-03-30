import template from './sw-sales-channel-detail-account-disconnect.html.twig';

const { Component } = Shopware;

Component.register('sw-sales-channel-detail-account-disconnect', {
    template,

    methods: {
        onDisconnectToGoogle() {
            this.$emit('on-disconnect-to-google');
        }
    }
});
