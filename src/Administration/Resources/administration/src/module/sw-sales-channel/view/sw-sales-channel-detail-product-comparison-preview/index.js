import template from './sw-sales-channel-detail-product-comparison-preview.html.twig';

const { Component } = Shopware;

Component.register('sw-sales-channel-detail-product-comparison-preview', {
    template,

    props: {
        content: {
            type: String,
            required: false
        }
    },

    computed: {
        editorConfig() {
            return {
                readOnly: true
            };
        }
    },

    methods: {
        onModalClose() {
            this.content = null;
            this.$emit('close');
        }
    }
});
