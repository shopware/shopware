import { Component } from 'src/core/shopware';
import './sw-settings-logging-mail-sent-info.scss';
import template from './sw-settings-logging-mail-sent-info.html.twig';

Component.extend('sw-settings-logging-mail-sent-info', 'sw-settings-logging-entry-info', {
    template,

    computed: {
        recipientString() {
            let recipients = '';
            const addresses = Object.keys(this.logEntry.context.additionalData.recipients);
            addresses.slice(0, 4).forEach((address) => {
                recipients += `${address} `;
            });

            if (addresses.length >= 5) {
                recipients += '...';
            }

            return recipients;
        }
    },

    methods: {
        createdComponent() {
            this.$super.createdComponent();
            this.activeTab = 'html';
        }
    }
});
