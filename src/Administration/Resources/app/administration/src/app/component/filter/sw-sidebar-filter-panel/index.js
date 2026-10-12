/**
 * @sw-package framework
 */

import template from './sw-sidebar-filter-panel.html.twig';
import './sw-sidebar-filter-panel.scss';

/**
 * @private
 */
export default {
    template,

    inject: {
        parentRegisterSidebarItem: {
            from: 'registerSidebarItem',
            default: null,
        },
    },

    provide() {
        return {
            registerSidebarItem: this.registerSidebarItem,
        };
    },

    shortcuts: {
        OF: 'openFilterPanel',
    },

    props: {
        activeFilterNumber: {
            type: Number,
            required: true,
        },
    },

    data() {
        return {
            filterSidebarItem: null,
        };
    },

    methods: {
        registerSidebarItem(sidebarItem) {
            this.filterSidebarItem = sidebarItem;
            this.parentRegisterSidebarItem?.(sidebarItem);
        },

        openFilterPanel() {
            if (!this.filterSidebarItem?.openContent) {
                return;
            }

            this.filterSidebarItem.openContent();
        },

        resetAll() {
            this.$refs.filterPanel.resetAll();
        },
    },
};
