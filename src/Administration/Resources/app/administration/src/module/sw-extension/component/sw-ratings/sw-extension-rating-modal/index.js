import template from './sw-extension-rating-modal.html.twig';
import './sw-extension-rating-modal.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.extend('sw-extension-rating-modal', 'sw-extension-review-creation', {
    template,

    methods: {
        emitClose() {
            this.$emit('modal-close');
        },
    },
});
