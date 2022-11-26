/*
 * @package inventory
 */

import template from './sw-product-restriction-selection.html.twig';
import './sw-product-restriction-selection.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        groupsWithOptions: {
            type: Array,
            required: true,
        },

        restriction: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            optionLoading: true,
            selectedGroup: '',
            optionStore: {},
            selectedOptionStore: {},
            options: {},
            selectedOptions: [],
        };
    },

    computed: {
        availableGroups() {
            return this.groupsWithOptions;
        },
    },

    watch: {
        selectedGroup() {
            this.optionLoading = true;

            // get all options for the group
            const optionsForGroup = this.groupsWithOptions.find((groupWithOption) => {
                return groupWithOption.group.id === this.selectedGroup;
            }).options;

            // get all selected options for the group
            const existedOptions = optionsForGroup.reduce((result, option) => {
                const optionIndex = this.restriction.options.indexOf(option.optionId);

                if (optionIndex >= 0) {
                    result.push(this.restriction.options[optionIndex]);
                }

                return result;
            }, []);

            this.options = optionsForGroup;
            this.selectedOptions = existedOptions;

            // update the group id in the store
            this.restriction.group = this.selectedGroup;

            this.optionLoading = false;
        },

        'selectedOptions'() {
            const selectedOptionArray = this.selectedOptions !== null ? this.selectedOptions : [];

            this.restriction.options = selectedOptionArray;
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            if (!this.groupsWithOptions && this.groupsWithOptions.length <= 0) {
                return;
            }

            this.selectedGroup = this.restriction.group;
        },

        deleteRestriction() {
            this.$emit('restriction-delete', this.restriction);
        },
    },
};
