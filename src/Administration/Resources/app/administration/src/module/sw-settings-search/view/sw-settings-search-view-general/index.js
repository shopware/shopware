/**
 * @package system-settings
 */
import template from './sw-settings-search-view-general.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        productSearchConfigs: {
            type: Object,
            required: false,
            default: () => {},
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            searchConfigId: '',
        };
    },

    watch: {
        productSearchConfigs(newValue) {
            this.searchConfigId = newValue.id || '';
        },
    },

    methods: {
        loadData() {
            this.$emit('excluded-search-terms-load');
        },
    },
};
