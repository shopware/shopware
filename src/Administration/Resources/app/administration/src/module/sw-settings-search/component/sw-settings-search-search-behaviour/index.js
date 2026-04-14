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
        strictnessOptions() {
            return [
                {
                    name: this.$tc('sw-settings-search.generalTab.strictnessLevel1Label'),
                    value: 0,
                    description: this.$tc('sw-settings-search.generalTab.strictnessLevel1Description'),
                },
                {
                    name: this.$tc('sw-settings-search.generalTab.strictnessLevel2Label'),
                    value: 33,
                    description: this.$tc('sw-settings-search.generalTab.strictnessLevel2Description'),
                },
                {
                    name: this.$tc('sw-settings-search.generalTab.strictnessLevel3Label'),
                    value: 50,
                    description: this.$tc('sw-settings-search.generalTab.strictnessLevel3Description'),
                },
                {
                    name: this.$tc('sw-settings-search.generalTab.strictnessLevel4Label'),
                    value: 66,
                    description: this.$tc('sw-settings-search.generalTab.strictnessLevel4Description'),
                },
                {
                    name: this.$tc('sw-settings-search.generalTab.strictnessLevel5Label'),
                    value: 100,
                    description: this.$tc('sw-settings-search.generalTab.strictnessLevel5Description'),
                },
            ];
        },
    },
};
