import template from './sw-bulk-edit-form-field-renderer.html.twig';

const { Component } = Shopware;

Component.extend('sw-bulk-edit-form-field-renderer', 'sw-form-field-renderer', {
    template,

    computed: {
        suffixLabel() {
            return this.config?.suffixLabel ? this.config.suffixLabel : null;
        },
    },
});
