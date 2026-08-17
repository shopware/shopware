import template from './sw-extension-my-extensions-listing-controls.html.twig';
import './sw-extension-my-extensions-listing-controls.scss';

/**
 * @sw-package checkout
 * @private
 */
export default {
    template,

    props: {
        sortingOption: {
            type: String,
            default: 'updated-at',
        },
    },

    emits: [
        'update:active-state',
        'update:sorting-option',
    ],

    data() {
        return {
            filterByActiveState: false,
            selectedSortingOption: this.sortingOption,
            sortingOptions: [
                {
                    id: 1,
                    value: 'updated-at',
                    label: this.$t('sw-extension.my-extensions.listing.controls.filterOptions.last-updated'),
                },
                {
                    id: 2,
                    value: 'name-asc',
                    label: this.$t('sw-extension.my-extensions.listing.controls.filterOptions.name-asc'),
                },
                {
                    id: 3,
                    value: 'name-desc',
                    label: this.$t('sw-extension.my-extensions.listing.controls.filterOptions.name-desc'),
                },
            ],
        };
    },

    watch: {
        sortingOption(value) {
            this.selectedSortingOption = value;
        },

        filterByActiveState(value) {
            this.$emit('update:active-state', value);
        },

        selectedSortingOption(value) {
            if (value !== this.sortingOption) {
                this.$emit('update:sorting-option', value);
            }
        },
    },
};
