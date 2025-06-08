import template from './sw-extension-my-extensions-listing-controls.html.twig';
import './sw-extension-my-extensions-listing-controls.scss';

/**
 * @sw-package checkout
 * @private
 */
export default {
    template,

    emits: [
        'update:active-state',
        'update:sorting-option',
    ],

    data() {
        return {
            filterByActiveState: false,
            selectedSortingOption: 'updated-at',
            sortingOptions: [
                {
                    id: 1,
                    value: 'updated-at',
                    label: this.$tc('sw-extension.my-extensions.listing.controls.filterOptions.last-updated'),
                },
                {
                    id: 2,
                    value: 'name-asc',
                    label: this.$tc('sw-extension.my-extensions.listing.controls.filterOptions.name-asc'),
                },
                {
                    id: 3,
                    value: 'name-desc',
                    label: this.$tc('sw-extension.my-extensions.listing.controls.filterOptions.name-desc'),
                },
            ],
        };
    },

    watch: {
        filterByActiveState(value) {
            this.$emit('update:active-state', value);
        },

        selectedSortingOption(value) {
            this.$emit('update:sorting-option', value);
        },
    },
};
