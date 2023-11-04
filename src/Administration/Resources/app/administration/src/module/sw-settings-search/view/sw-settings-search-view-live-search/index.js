/**
 * @package system-settings
 */
import template from './sw-settings-search-view-live-search.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        currentSalesChannelId: {
            type: String,
            required: false,
            default: null,
        },

        searchTerms: {
            type: String,
            required: false,
            default: null,
        },

        searchResults: {
            type: Object,
            required: false,
            default() {
                return null;
            },
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        storefrontEsEnable() {
            return Shopware.Context.app.storefrontEsEnable ?? false;
        },
    },
};
