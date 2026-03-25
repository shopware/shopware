/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-detail-agentic-commerce-integration.html.twig';
import './sw-sales-channel-detail-agentic-commerce-integration.scss';

const DEFAULT_PROVIDER = 'open-ai';

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
            return this.productExport?.provider || DEFAULT_PROVIDER;
        },

        isOpenAi() {
            return this.providerName === DEFAULT_PROVIDER;
        },

        feedUrl() {
            return this.productComparisonAccessUrl || '';
        },

        integrationSnippetPrefix() {
            return `sw-sales-channel.detail.agenticCommerce.integration.providers.${this.providerName}`;
        },
    },
};
