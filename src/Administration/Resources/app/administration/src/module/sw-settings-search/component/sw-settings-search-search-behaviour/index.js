/**
 * @sw-package inventory
 */
import template from './sw-settings-search-search-behaviour.html.twig';
import './sw-settings-search-search-behaviour.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
    ],

    props: {
        searchBehaviourConfigs: {
            type: Object,
            required: false,
            default() {
                return null;
            },
        },

        isLoading: {
            type: Boolean,
            default: false,
        },
    },

    data: () => {
        return {
            min: 1,
            max: 20,
        };
    },

    computed: {
        conditionsOptions() {
            return [
                {
                    name: this.$t('sw-settings-search.generalTab.labelSearchAndCondition'),
                    value: true,
                    description: this.$t('sw-settings-search.generalTab.textSearchAndConditionExplain'),
                },
                {
                    name: this.$t('sw-settings-search.generalTab.labelSearchOrCondition'),
                    value: false,
                    description: this.$t('sw-settings-search.generalTab.textSearchOrConditionExplain'),
                },
            ];
        },
    },
};
