import template from './sw-category-link-settings.html.twig';
import './sw-category-link-settings.scss';

const { Component } = Shopware;

Component.register('sw-category-link-settings', {
    template,

    inject: ['acl'],

    props: {
        category: {
            type: Object,
            required: true
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    }
});
