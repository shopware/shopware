/**
 * @package services-settings
 */

import './sw-settings-logging-mail-sent-info.scss';
import template from './sw-settings-logging-mail-sent-info.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
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
};
