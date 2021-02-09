import template from './sw-sidebar-filter-panel.html.twig';
import './sw-sidebar-filter-panel.scss';

const { Component } = Shopware;

Component.register('sw-sidebar-filter-panel', {
    template,

    data() {
        return {
            activeFilterNumber: 0
        };
    },

    methods: {
        resetAll() {
            this.$refs.filterPanel.resetAll();
        },

        onActiveFilterNumberUpdate(value) {
            this.activeFilterNumber = value;
        }
    }
});
