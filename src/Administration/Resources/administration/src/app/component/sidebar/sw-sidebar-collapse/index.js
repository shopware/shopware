import { Component } from 'src/core/shopware';
import template from './sw-sidebar-collapse.html.twig';
import './sw-sidebar-collaps.scss';

Component.extend('sw-sidebar-collapse', 'sw-collapse', {
    template,

    computed: {
        expandButtonClass() {
            return {
                'is--hidden': this.expanded
            };
        },

        collapseButtonClass() {
            return {
                'is--hidden': !this.expanded
            };
        }
    },

    methods: {
        collapseItem() {
            this.$super.collapseItem();
            this.$emit('sw-sidebar-collaps-change-expanded', { isExpanded: this.expanded });
        }
    }
});
