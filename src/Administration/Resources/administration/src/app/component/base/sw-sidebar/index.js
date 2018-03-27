import { Component } from 'src/core/shopware';
import template from './sw-sidebar.html.twig';
import './sw-sidebar.less';

Component.register('sw-sidebar', {
    template,

    data() {
        return {
            items: {},
            currentlyExpanded: null
        };
    },

    created() {
        this.$on('closeNonExpandedContentPanels', this.closeNonExpandedContentPanels);
    },

    methods: {
        closeNonExpandedContentPanels(activePanelId) {
            Object.keys(this.items).forEach((key) => {
                const panel = this.items[key];

                if (activePanelId !== key) {
                    panel.isExpanded = false;
                }
            });

            this.currentlyExpanded = this.items[activePanelId];
        }
    }
});
