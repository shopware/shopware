/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-detail-agentic-ai-integration.html.twig';
import './sw-sales-channel-detail-agentic-ai-integration.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl'],

    props: {
        // eslint-disable-next-line vue/require-prop-types
        salesChannel: {
            required: true,
        },

        // eslint-disable-next-line vue/require-prop-types
        productExport: {
            required: true,
        },

        productComparisonAccessUrl: {
            type: String,
            default: '',
        },

        isLoading: {
            type: Boolean,
            default: false,
        },
    },

    computed: {
        providerName() {
            return this.productExport?.provider || 'open-ai';
        },

        isOpenAi() {
            return this.providerName === 'open-ai';
        },

        feedUrl() {
            return this.productComparisonAccessUrl || '';
        },

        integrationSnippetPrefix() {
            return `sw-sales-channel.detail.agenticAi.integration.providers.${this.providerName}`;
        },
    },
};
