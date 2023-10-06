import template from './sw-sidebar-filter-panel.html.twig';
import './sw-sidebar-filter-panel.scss';

const { Component } = Shopware;

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Component.register('sw-sidebar-filter-panel', {
    template,

    props: {
        activeFilterNumber: {
            type: Number,
            required: true,
        },
    },

    methods: {
        resetAll() {
            this.$refs.filterPanel.resetAll();
        },
    },
});
