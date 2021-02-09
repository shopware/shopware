import template from './sw-settings-search-searchable-content-genera.html.twig';

const { Component } = Shopware;

Component.register('sw-settings-search-searchable-content-general', {
    template,

    props: {
        isEmpty: {
            type: Boolean,
            required: true,
            default: true
        }
    },

    methods: {
        onAddField() {
            // TODO: NEXT-13010 - Implement "Searchable content" card with API integration
        }
    }
});
