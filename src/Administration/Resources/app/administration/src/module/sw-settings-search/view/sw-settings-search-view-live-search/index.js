/**
 * @package buyers-experience
 */
import template from './sw-settings-search-view-live-search.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

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

        listeners() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_LISTENERS')) {
                return this.$listeners;
            }

            return {};
        },
    },
};
