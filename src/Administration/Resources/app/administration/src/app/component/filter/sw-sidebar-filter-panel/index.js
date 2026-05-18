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

    shortcuts: {
        OF: 'openFilterPanel',
    },

    props: {
        activeFilterNumber: {
            type: Number,
            required: true,
        },
    },

    computed: {},

    methods: {
        openFilterPanel() {
            this.$refs.filterSidebarItem.openContent();
        },

        resetAll() {
            this.$refs.filterPanel.resetAll();
        },
    },
};
