import template from './sw-media-display-options.html.twig';

const { Feature } = Shopware;

const getDefaultSorting = () => {
    if (Feature.isActive('v6.8.0.0')) {
        return {
            sortBy: 'createdAt',
            sortDirection: 'desc',
        };
    }

    return {
        sortBy: 'createdAt',
        sortDirection: 'asc',
    };
};

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: [
        'media-sorting-change',
        'media-presentation-change',
    ],

    props: {
        presentation: {
            type: String,
            required: false,
            default: 'medium-preview',
            validValues: [
                'small-preview',
                'medium-preview',
                'large-preview',
                'list-preview',
            ],
            validator(value) {
                return [
                    'small-preview',
                    'medium-preview',
                    'large-preview',
                    'list-preview',
                ].includes(value);
            },
        },

        sorting: {
            type: Object,
            required: false,
            default: getDefaultSorting,
        },

        hidePresentation: {
            type: Boolean,
            required: false,
            default: false,
        },

        disabled: {
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
                {
                    value: 'createdAt:asc',
                    name: this.$t('sw-media.sorting.labelSortByCreatedAsc'),
                },
                {
                    value: 'createdAt:desc',
                    name: this.$t('sw-media.sorting.labelSortByCreatedDsc'),
                },
                {
                    value: 'fileName:asc',
                    name: this.$t('sw-media.sorting.labelSortByNameAsc'),
                },
                {
                    value: 'fileName:desc',
                    name: this.$t('sw-media.sorting.labelSortByNameDsc'),
                },
                {
                    value: 'fileSize:asc',
                    name: this.$t('sw-media.sorting.labelSortBySizeAsc'),
                },
                {
                    value: 'fileSize:desc',
                    name: this.$t('sw-media.sorting.labelSortBySizeDsc'),
                },
            ];
        },

        previewOptions() {
            return [
                {
                    value: 'small-preview',
                    name: this.$t('sw-media.presentation.labelPresentationSmall'),
                },
                {
                    value: 'medium-preview',
                    name: this.$t('sw-media.presentation.labelPresentationMedium'),
                },
                {
                    value: 'large-preview',
                    name: this.$t('sw-media.presentation.labelPresentationLarge'),
                },
                {
                    value: 'list-preview',
                    name: this.$t('sw-media.presentation.labelPresentationList'),
                },
            ];
        },

        presentationOptions() {
            return (
                this.previewOptions?.map((item) => {
                    return {
                        id: item.value,
                        value: item.value,
                        label: item.name,
                    };
                }) ?? []
            );
        },

        sortOptionsSelect() {
            return this.sortOptions.map((item) => {
                return {
                    id: item.value,
                    value: item.value,
                    label: item.name,
                };
            });
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
