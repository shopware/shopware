import { Component } from 'src/core/shopware';
import template from './sw-settings-snippet-sidebar.html.twig';

Component.register('sw-settings-snippet-sidebar', {
    template,

    props: {
        filterItems: {
            type: Array,
            required: true
        }
    },

    methods: {
        closeContent() {
            if (this.filterSidebarIsOpen) {
                this.$refs.filterSideBar.closeContent();
                this.filterSidebarIsOpen = false;
                return;
            }

            this.$refs.filterSideBar.openContent();
            this.filterSidebarIsOpen = true;
        },

        onChange(field) {
            this.$emit('change', field);
        },

        onRefresh() {
            this.$emit('sw-sidebar-collaps-refresh-grid');
        }
    }
});
