import template from './sw-sidebar-filter-panel.html.twig';
import './sw-sidebar-filter-panel.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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
