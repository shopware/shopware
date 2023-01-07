import template from './sw-media-display-options.html.twig';
import './sw-media-display-options.scss';

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        presentation: {
            type: String,
            required: false,
            default: 'medium-preview',
            validValues: ['small-preview', 'medium-preview', 'large-preview', 'list-preview'],
            validator(value) {
                return ['small-preview', 'medium-preview', 'large-preview', 'list-preview'].includes(value);
            },
        },

        sorting: {
            type: Object,
            required: false,
            default: () => {
                return {
                    sortBy: 'createdAt',
                    sortDirection: 'asc',
                };
            },
        },

        hidePresentation: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        sortingConCat() {
            return `${this.sorting.sortBy}:${this.sorting.sortDirection}`;
        },

        sortOptions() {
            return [
                { value: 'createdAt:asc', name: this.$tc('sw-media.sorting.labelSortByCreatedAsc') },
                { value: 'createdAt:desc', name: this.$tc('sw-media.sorting.labelSortByCreatedDsc') },
                { value: 'fileName:asc', name: this.$tc('sw-media.sorting.labelSortByNameAsc') },
                { value: 'fileName:desc', name: this.$tc('sw-media.sorting.labelSortByNameDsc') },
                { value: 'fileSize:asc', name: this.$tc('sw-media.sorting.labelSortBySizeAsc') },
                { value: 'fileSize:desc', name: this.$tc('sw-media.sorting.labelSortBySizeDsc') },
            ];
        },

        previewOptions() {
            return [
                { value: 'small-preview', name: this.$tc('sw-media.presentation.labelPresentationSmall') },
                { value: 'medium-preview', name: this.$tc('sw-media.presentation.labelPresentationMedium') },
                { value: 'large-preview', name: this.$tc('sw-media.presentation.labelPresentationLarge') },
                { value: 'list-preview', name: this.$tc('sw-media.presentation.labelPresentationList') },
            ];
        },
    },

    methods: {
        onSortingChanged(value) {
            const parts = value.split(':');
            this.$emit('media-sorting-change', {
                sortBy: parts[0],
                sortDirection: parts[1],
            });
        },

        onPresentationChanged(value) {
            this.$emit('media-presentation-change', value);
        },
    },
};
