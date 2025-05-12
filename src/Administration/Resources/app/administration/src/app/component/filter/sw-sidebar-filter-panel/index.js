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

    props: {
        activeFilterNumber: {
            type: Number,
            required: true,
        },
    },

    computed: {},

    methods: {
        resetAll() {
            this.$refs.filterPanel.resetAll();
        },
    },
};
