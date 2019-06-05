import { Component } from 'src/core/shopware';
import template from './sw-settings-logging-mail-dispatched-info.html.twig';

Component.extend('sw-settings-logging-mail-dispatched-info', 'sw-settings-logging-entry-info', {
    template,

    methods: {
        createdComponent() {
            this.$super.createdComponent();
            this.activeTab = 'html';
        }
    }
});
