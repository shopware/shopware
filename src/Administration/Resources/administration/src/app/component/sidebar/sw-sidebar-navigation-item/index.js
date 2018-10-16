import { Component } from 'src/core/shopware';
import template from './sw-sidebar-navigation-item.html.twig';
import './sw-sidebar-navigation-item.less';

Component.register('sw-sidebar-navigation-item', {
    template,

    props: {
        sidebarItem: {
            type: Object,
            required: true
        }
    },

    methods: {
        emitButtonClicked() {
            this.$emit('sw-sidebar-navigation-item-clicked', this.sidebarItem);
        }
    }
});
