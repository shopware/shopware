import { Component } from 'src/core/shopware';
import template from './sw-product-sidebar.html.twig';
import './sw-product-sidebar.less';

Component.register('sw-product-sidebar', {
    data() {
        return {
            items: {},
            currentlyExpanded: null
        };
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
    },
    template
});
