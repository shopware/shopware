import { Component } from 'src/core/shopware';
import template from './sw-configuration-option-detail.html.twig';

Component.register('sw-configuration-option-detail', {
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
