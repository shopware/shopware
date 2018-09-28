import { Component } from 'src/core/shopware';
import template from './sw-sidebar-button.html.twig';
import './sw-sidebar-button.less';

Component.register('sw-sidebar-button', {
    template,

    props: {
        sidebarItem: {
            type: Object,
            required: true
        }
    },

    methods: {
        emitButtonClicked() {
            this.$emit('sw-sidebar-button-clicked', this.sidebarItem);
        }
    }
});
