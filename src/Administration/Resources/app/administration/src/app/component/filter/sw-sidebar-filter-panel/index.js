import template from './sw-sidebar-filter-panel.html.twig';
import './sw-sidebar-filter-panel.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-sidebar-filter-panel', {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        activeFilterNumber: {
            type: Number,
            required: true,
        },
    },

    computed: {
        listeners() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_LISTENERS')) {
                return this.$listeners;
            }

            return {};
        },
    },

    methods: {
        resetAll() {
            this.$refs.filterPanel.resetAll();
        },
    },
});
