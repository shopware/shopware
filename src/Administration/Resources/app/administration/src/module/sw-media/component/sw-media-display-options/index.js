import template from './sw-media-display-options.html.twig';

const DEFAULT_SORTING = {
    sortBy: 'createdAt',
    sortDirection: 'desc',
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
        'media-type-filter-change',
    ],

    props: {
        presentation: {
            type: String,
            required: false,
            default: 'medium-preview',
            validValues: [
                'small-preview',
                'medium-preview',
                'list-preview',
            ],
            validator(value) {
                return [
                    'small-preview',
                    'medium-preview',
                    'list-preview',
                ].includes(value);
            },
        },

        sorting: {
            type: Object,
            required: false,
            default: () => {
                return { ...DEFAULT_SORTING };
            },
        },

        typeFilter: {
            type: [
                String,
                Array,
            ],
            required: false,
            default: () => [],
            validator(value) {
                const validTypes = [
                    'all',
                    'folder',
                    'image',
                    'document',
                    'video',
                    'audio',
                    'spatial',
                ];

                if (Array.isArray(value)) {
                    return value.every((type) => validTypes.includes(type));
                }

                return [
                    'all',
                    'folder',
                    'image',
                    'document',
                    'video',
                    'audio',
                    'spatial',
                ].includes(value);
            },
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
        sortOptions() {
            return [
                {
                    value: 'createdAt',
                    sortBy: 'createdAt',
                    label: this.$t('sw-media.sorting.labelSortCreatedAt'),
                    icon: 'regular-calendar',
                    defaultDirection: 'desc',
                    alternateDirection: 'asc',
                    contextualDetails: {
                        asc: this.$t('sw-media.sorting.labelSortDirectionOldestFirst'),
                        desc: this.$t('sw-media.sorting.labelSortDirectionNewestFirst'),
                    },
                    labels: {
                        asc: this.$t('sw-media.sorting.labelSortByCreatedAsc'),
                        desc: this.$t('sw-media.sorting.labelSortByCreatedDsc'),
                    },
                },
                {
                    value: 'fileName',
                    sortBy: 'fileName',
                    label: this.$t('sw-media.sorting.labelSortName'),
                    icon: 'regular-list-alphabetical-xs',
                    defaultDirection: 'asc',
                    alternateDirection: 'desc',
                    contextualDetails: {
                        asc: this.$t('sw-media.sorting.labelSortDirectionAscAlpha'),
                        desc: this.$t('sw-media.sorting.labelSortDirectionDescAlpha'),
                    },
                    labels: {
                        asc: this.$t('sw-media.sorting.labelSortByNameAsc'),
                        desc: this.$t('sw-media.sorting.labelSortByNameDsc'),
                    },
                },
                {
                    value: 'fileSize',
                    sortBy: 'fileSize',
                    label: this.$t('sw-media.sorting.labelSortSize'),
                    icon: 'regular-balance-scale',
                    defaultDirection: 'desc',
                    alternateDirection: 'asc',
                    contextualDetails: {
                        asc: this.$t('sw-media.sorting.labelSortDirectionSmallestFirst'),
                        desc: this.$t('sw-media.sorting.labelSortDirectionLargestFirst'),
                    },
                    labels: {
                        asc: this.$t('sw-media.sorting.labelSortBySizeAsc'),
                        desc: this.$t('sw-media.sorting.labelSortBySizeDsc'),
                    },
                },
                {
                    value: 'fileExtension',
                    sortBy: 'fileExtension',
                    label: this.$t('sw-media.sorting.labelSortFileType'),
                    icon: 'regular-file',
                    defaultDirection: 'asc',
                    alternateDirection: 'desc',
                    contextualDetails: {
                        asc: this.$t('sw-media.sorting.labelSortDirectionAscAlpha'),
                        desc: this.$t('sw-media.sorting.labelSortDirectionDescAlpha'),
                    },
                    labels: {
                        asc: this.$t('sw-media.sorting.labelSortByFileTypeAsc'),
                        desc: this.$t('sw-media.sorting.labelSortByFileTypeDsc'),
                    },
                },
            ];
        },

        typeFilterOptions() {
            return [
                {
                    value: 'folder',
                    label: this.$t('sw-media.filter.labelTypeFolders'),
                    icon: 'regular-folder',
                },
                {
                    value: 'image',
                    label: this.$t('sw-media.filter.labelTypeImages'),
                    icon: 'regular-image',
                },
                {
                    value: 'video',
                    label: this.$t('sw-media.filter.labelTypeVideos'),
                    icon: 'regular-video',
                },
                {
                    value: 'document',
                    label: this.$t('sw-media.filter.labelTypeDocuments'),
                    icon: 'regular-file-text',
                },
                {
                    value: 'audio',
                    label: this.$t('sw-media.filter.labelTypeAudio'),
                    icon: 'regular-volume-up',
                },
                {
                    value: 'spatial',
                    label: this.$t('sw-media.filter.labelTypeSpatial'),
                    icon: 'regular-3d',
                },
            ];
        },

        normalizedTypeFilter() {
            return this.normalizeTypeFilter(this.typeFilter);
        },

        isAllTypeFiltersSelected() {
            return this.typeFilterOptions.every((option) => {
                return this.normalizedTypeFilter.includes(option.value);
            });
        },

        isTypeFilterPartiallySelected() {
            return this.normalizedTypeFilter.length > 0 && !this.isAllTypeFiltersSelected;
        },

        hasActiveTypeFilter() {
            return this.normalizedTypeFilter.length > 0;
        },

        typeFilterActionLabel() {
            if (this.isAllTypeFiltersSelected) {
                return this.$t('sw-media.filter.labelTypeClearSelection');
            }

            return this.$t('sw-media.filter.labelTypeAll');
        },

        previewOptions() {
            return [
                {
                    value: 'small-preview',
                    name: this.$t('sw-media.presentation.labelPresentationSmall'),
                    icon: 'regular-view-grid',
                },
                {
                    value: 'medium-preview',
                    name: this.$t('sw-media.presentation.labelPresentationMedium'),
                    icon: 'regular-image',
                },
                {
                    value: 'list-preview',
                    name: this.$t('sw-media.presentation.labelPresentationList'),
                    icon: 'regular-image-text',
                },
            ];
        },

        activePresentationOption() {
            return this.previewOptions.find((option) => option.value === this.presentation) ?? this.previewOptions[1];
        },

        presentationActionLabel() {
            return this.activePresentationOption?.name ?? '';
        },

        presentationActionAriaLabel() {
            return `${this.$t('sw-media.presentation.labelPresentationLayout')}: ${this.presentationActionLabel}`;
        },

        activeSortOption() {
            return this.sortOptions.find((option) => option.sortBy === this.sorting.sortBy) ?? this.sortOptions[1];
        },

        activeSortLabel() {
            return this.activeSortOption.labels[this.sorting.sortDirection] ?? this.activeSortOption.label;
        },

        activeSortDirectionIcon() {
            return this.getSortDirectionIcon(this.sorting.sortDirection);
        },

        canResetSorting() {
            return (
                this.sorting.sortBy !== DEFAULT_SORTING.sortBy ||
                this.sorting.sortDirection !== DEFAULT_SORTING.sortDirection
            );
        },

        sortingActionAriaLabel() {
            return `${this.$t('sw-media.sorting.labelSort')}: ${this.activeSortLabel}`;
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
    },

    methods: {
        onSortingChanged(value) {
            const parts = value.split(':');
            this.$emit('media-sorting-change', {
                sortBy: parts[0],
                sortDirection: parts[1],
            });
        },

        onSortOptionChanged(option) {
            const isActiveOption = option.sortBy === this.sorting.sortBy;
            const sortDirection =
                isActiveOption && this.sorting.sortDirection === option.defaultDirection
                    ? option.alternateDirection
                    : option.defaultDirection;

            this.onSortingChanged(`${option.sortBy}:${sortDirection}`);
        },

        getSortOptionContextualDetail(option) {
            const direction = option.sortBy === this.sorting.sortBy ? this.sorting.sortDirection : option.defaultDirection;

            return option.contextualDetails[direction];
        },

        getSortOptionIcon(option) {
            return option.icon;
        },

        getSortDirectionIcon(direction) {
            return direction === 'desc' ? 'regular-long-arrow-down' : 'regular-long-arrow-up';
        },

        resetSorting() {
            this.onSortingChanged(`${DEFAULT_SORTING.sortBy}:${DEFAULT_SORTING.sortDirection}`);
        },

        onPresentationChanged(value) {
            this.$emit('media-presentation-change', value);
        },

        onTypeFilterChanged(value) {
            this.$emit('media-type-filter-change', this.normalizeTypeFilter(value));
        },

        normalizeTypeFilter(value) {
            const validTypes = this.typeFilterOptions.map((option) => option.value);

            if (value === 'all') {
                return [];
            }

            if (Array.isArray(value)) {
                return value.filter((type) => validTypes.includes(type));
            }

            if (validTypes.includes(value)) {
                return [value];
            }

            return [];
        },

        isTypeFilterSelected(value) {
            return this.normalizedTypeFilter.includes(value);
        },

        onToggleTypeFilter(value) {
            const selectedTypes = new Set(this.normalizedTypeFilter);

            if (selectedTypes.has(value)) {
                selectedTypes.delete(value);
            } else {
                selectedTypes.add(value);
            }

            this.onTypeFilterChanged([...selectedTypes]);
        },

        onTypeFilterSwitchChanged(value, checked) {
            const selectedTypes = new Set(this.normalizedTypeFilter);

            if (checked) {
                selectedTypes.add(value);
            } else {
                selectedTypes.delete(value);
            }

            this.onTypeFilterChanged([...selectedTypes]);
        },

        onToggleAllTypeFilters() {
            if (this.isAllTypeFiltersSelected) {
                this.onTypeFilterChanged([]);
                return;
            }

            this.onTypeFilterChanged(this.typeFilterOptions.map((option) => option.value));
        },

        onSelectAllTypeFilters() {
            if (this.isAllTypeFiltersSelected) {
                this.onTypeFilterChanged([]);
                return;
            }

            this.onTypeFilterChanged(this.typeFilterOptions.map((option) => option.value));
        },
    },
};
