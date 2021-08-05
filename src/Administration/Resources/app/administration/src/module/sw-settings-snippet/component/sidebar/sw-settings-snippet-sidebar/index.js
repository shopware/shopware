import template from './sw-settings-snippet-sidebar.html.twig';

const { Component } = Shopware;

Component.register('sw-settings-snippet-sidebar', {
    template,

    props: {
        filterItems: {
            type: Array,
            required: true,
        },

        authorFilters: {
            type: Array,
            required: true,
        },
    },

    methods: {
        closeContent() {
            if (this.filterSidebarIsOpen) {
                this.$refs.filterSideBar.closeContent();
                this.filterSidebarIsOpen = false;
                this.$emit('sw-sidebar-close');
                return;
            }

            this.$refs.filterSideBar.openContent();
            this.filterSidebarIsOpen = true;

            this.$emit('sw-sidebar-open');
        },

        onChange(field) {
            this.$emit('change', field);
        },

        onRefresh() {
            this.$emit('sw-sidebar-collaps-refresh-grid');
        },
    },
});
