/**
 * @sw-package discovery
 */

import template from './sw-agentic-commerce-tracking-config.html.twig';

/** @deprecated tag:v6.8.0 - Will be removed */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['change'],

    props: {
        salesChannel: {
            type: Object,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        trackingConfig() {
            if (!this.salesChannel.configuration) {
                this.salesChannel.configuration = {};
            }

            return this.salesChannel.configuration;
        },
    },

    methods: {
        onAffiliateCodeChange(value) {
            this.trackingConfig.affiliateCode = value;
            this.$emit('change', { ...this.trackingConfig });
        },

        onCampaignCodeChange(value) {
            this.trackingConfig.campaignCode = value;
            this.$emit('change', { ...this.trackingConfig });
        },
    },
};
