/**
 * @sw-package inventory
 */
import template from './sw-settings-search-view-general.html.twig';

/**
 * @public
 */
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
        productSearchConfigs(newValue) {
            this.searchConfigId = newValue.id || '';
        },
    },

    computed: {},

    methods: {
        loadData() {
            this.$emit('excluded-search-terms-load');
        },
    },
};
