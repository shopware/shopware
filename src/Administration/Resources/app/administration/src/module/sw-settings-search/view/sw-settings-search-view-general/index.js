/**
 * @sw-package inventory
 */
import template from './sw-settings-search-view-general.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['excluded-search-terms-load'],

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
        productSearchConfigs: {
            handler(newValue) {
                this.searchConfigId = newValue?.id || '';
            },
            immediate: true,
        },
    },

    computed: {},

    methods: {
        loadData() {
            this.$emit('excluded-search-terms-load');
        },
    },
};
