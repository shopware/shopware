import template from './sw-sales-channel-detail-product-comparison-preview.html.twig';
import './sw-sales-channel-detail-product-comparison-preview.scss';

const { Component } = Shopware;

Component.register('sw-sales-channel-detail-product-comparison-preview', {
    template,

    props: {
        content: {
            type: String,
            required: false,
            default: null,
        },
        errors: {
            type: Array,
            required: false,
            default: () => [],
        },
    },

    computed: {
        editorConfig() {
            return {
                readOnly: true,
            };
        },

        displayErrors() {
            return this.errors.length > 0;
        },
    },

    methods: {
        onModalClose() {
            this.content = null;
            this.errors = null;
            this.$emit('close');
        },

        navigateToLine(line) {
            if (!line) {
                return;
            }

            this.$refs.previewEditor.editor.scrollToLine(line, true, true, () => {});
            this.$refs.previewEditor.editor.gotoLine(line, 0, true);
        },
    },
});
