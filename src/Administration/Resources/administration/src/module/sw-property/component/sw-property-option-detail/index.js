import { Component } from 'src/core/shopware';
import template from './sw-property-option-detail.html.twig';

Component.register('sw-property-option-detail', {
    template,

    props: {
        currentOption: {
            type: Object,
            default() {
                return {};
            }
        }
    },

    methods: {
        onCancel() {
            if (this.currentOption !== null) {
                this.currentOption.discardChanges();
            }
            this.$emit('cancel-option-edit', this.currentOption);
        },

        onSave() {
            this.$emit('save-option-edit', this.currentOption);
        }
    }
});
