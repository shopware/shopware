import './sw-settings-logging-mail-sent-info.scss';
import template from './sw-settings-logging-mail-sent-info.html.twig';

const { Component } = Shopware;

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
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.activeTab = 'html';
        },
    },
});
