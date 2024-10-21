/**
 * @package buyers-experience
 */
import template from './sw-settings-search-view-general.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

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

    computed: {
        listeners() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_LISTENERS')) {
                return this.$listeners;
            }

            return {};
        },
    },

    methods: {
        loadData() {
            this.$emit('excluded-search-terms-load');
        },
    },
};
