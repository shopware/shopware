import template from './sw-extension-rating-modal.html.twig';
import './sw-extension-rating-modal.scss';

const { Component } = Shopware;

Component.register('sw-extension-rating-modal', {
    template,
    extendsFrom: 'sw-review-creation',

    methods: {
        emitClose() {
            this.$emit('modal-close');
        }
    }
});
