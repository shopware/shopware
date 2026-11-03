/**
 * @sw-package inventory
 */
import template from './sw-settings-search-view-live-search.html.twig';
import { getAppContext } from '../dependencies';

/**
 * @public
 */
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
            return getAppContext().storefrontEsEnable ?? false;
        },
    },
};
