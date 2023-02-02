import template from './sw-sales-channel-create-base.html.twig';

const { Component } = Shopware;

Component.extend('sw-sales-channel-create-base', 'sw-sales-channel-detail-base', {
    template,

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.onGenerateKeys();
            if (this.isProductComparison) {
                this.onGenerateProductExportKey(false);
            }
        },
    },
});
