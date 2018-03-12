import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-product-sidebar-panel.html.twig';
import './sw-product-sidebar-panel.less';

Component.register('sw-product-sidebar-panel', {
    props: {
        title: {
            type: String,
            required: true
        },
        icon: {
            type: String,
            required: true
        }
    },
    data() {
        return {
            panelId: utils.createId(),
            isExpanded: false
        };
    },

    created() {
        this.$parent.items[this.panelId] = this;
    },

    methods: {
        toggleContentPanel() {
            this.isExpanded = !this.isExpanded;
            this.$parent.closeNonExpandedContentPanels(this.panelId);
        }
    },

    template
});
