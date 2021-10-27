import template from './sw-order-document-settings-invoice-modal.html.twig';

const { Component, Mixin } = Shopware;

Component.extend('sw-order-document-settings-invoice-modal', 'sw-order-document-settings-modal', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        addAdditionalInformationToDocument() {
            this.documentConfig.custom.invoiceNumber = this.documentConfig.documentNumber;
        },

        onPreview() {
            this.$emit('loading-preview');
            this.documentConfig.custom.invoiceNumber = this.documentConfig.documentNumber;
            this.$super('onPreview');
        },
    },
});
