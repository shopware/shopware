import { mount } from '@vue/test-utils';

const createWrapper = async (customOptions) => {
    return mount(await wrapTestComponent('sw-media-display-options', { sync: true }), {
        global: {
            stubs: {},
        },
        ...customOptions,
    });
};

describe('src/module/sw-media/component/sw-media-display-options', () => {
    it('should return the correct presentation options', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.previewOptions).toEqual([
            expect.objectContaining({
                value: 'small-preview',
                name: 'sw-media.presentation.labelPresentationSmall',
                icon: 'regular-view-grid',
            }),
            expect.objectContaining({
                value: 'medium-preview',
                name: 'sw-media.presentation.labelPresentationMedium',
                icon: 'regular-image',
            }),
            expect.objectContaining({
                value: 'list-preview',
                name: 'sw-media.presentation.labelPresentationList',
                icon: 'regular-image-text',
            }),
        ]);
        expect(wrapper.vm.presentationActionLabel).toBe('sw-media.presentation.labelPresentationMedium');
        expect(wrapper.vm.presentationActionAriaLabel).toBe(
            'sw-media.presentation.labelPresentationLayout: sw-media.presentation.labelPresentationMedium',
        );
    });

    it('should emit presentation changes', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onPresentationChanged('list-preview');

        expect(wrapper.emitted('media-presentation-change')).toEqual([['list-preview']]);
    });

    it('should disable the presentation action when disabled prop is true', async () => {
        const wrapper = await createWrapper({
            props: {
                disabled: true,
            },
        });
        await flushPromises();

        const presentationButton = wrapper.find('.sw-media-display-options__presentation-button');
        expect(presentationButton.attributes('disabled')).toBeDefined();

        const sortingButton = wrapper.find('.sw-media-display-options__sorting-button');
        expect(sortingButton.attributes('disabled')).toBeDefined();
    });

    it('should include one option per sortable media field', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.sortOptions).toHaveLength(4);
        expect(wrapper.vm.sortOptions).toEqual([
            expect.objectContaining({
                value: 'createdAt',
                sortBy: 'createdAt',
                label: 'sw-media.sorting.labelSortCreatedAt',
                icon: 'regular-calendar',
                defaultDirection: 'desc',
            }),
            expect.objectContaining({
                value: 'fileName',
                sortBy: 'fileName',
                label: 'sw-media.sorting.labelSortName',
                icon: 'regular-list-alphabetical-xs',
                defaultDirection: 'asc',
            }),
            expect.objectContaining({
                value: 'fileSize',
                sortBy: 'fileSize',
                label: 'sw-media.sorting.labelSortSize',
                icon: 'regular-balance-scale',
                defaultDirection: 'desc',
            }),
            expect.objectContaining({
                value: 'fileExtension',
                sortBy: 'fileExtension',
                label: 'sw-media.sorting.labelSortFileType',
                icon: 'regular-file',
                defaultDirection: 'asc',
            }),
        ]);
        expect(wrapper.vm.activeSortOption).toEqual(
            expect.objectContaining({
                value: 'createdAt',
                label: 'sw-media.sorting.labelSortCreatedAt',
            }),
        );
        expect(wrapper.vm.sortingActionAriaLabel).toBe(
            'sw-media.sorting.labelSort: sw-media.sorting.labelSortByCreatedDsc',
        );
        expect(wrapper.vm.activeSortDirectionIcon).toBe('regular-long-arrow-down');
        expect(wrapper.vm.canResetSorting).toBe(false);
    });

    it('should toggle the active sort option direction', async () => {
        const wrapper = await createWrapper({
            props: {
                sorting: {
                    sortBy: 'fileName',
                    sortDirection: 'asc',
                },
            },
        });

        wrapper.vm.onSortOptionChanged(wrapper.vm.sortOptions[1]);

        expect(wrapper.emitted('media-sorting-change')).toEqual([[
            {
                sortBy: 'fileName',
                sortDirection: 'desc',
            },
        ]]);
    });

    it('should use a sensible default direction when changing sort option', async () => {
        const wrapper = await createWrapper({
            props: {
                sorting: {
                    sortBy: 'fileName',
                    sortDirection: 'asc',
                },
            },
        });

        wrapper.vm.onSortOptionChanged(wrapper.vm.sortOptions[0]);

        expect(wrapper.emitted('media-sorting-change')).toEqual([[
            {
                sortBy: 'createdAt',
                sortDirection: 'desc',
            },
        ]]);
    });

    it('should show the active sort direction as contextual detail', async () => {
        const wrapper = await createWrapper({
            props: {
                sorting: {
                    sortBy: 'fileName',
                    sortDirection: 'desc',
                },
            },
        });

        expect(wrapper.vm.getSortOptionContextualDetail(wrapper.vm.sortOptions[1])).toBe(
            'sw-media.sorting.labelSortDirectionDescAlpha',
        );
        expect(wrapper.vm.sortingActionAriaLabel).toBe(
            'sw-media.sorting.labelSort: sw-media.sorting.labelSortByNameDsc',
        );
        expect(wrapper.vm.getSortOptionIcon(wrapper.vm.sortOptions[1])).toBe('regular-list-alphabetical-xs');
        expect(wrapper.vm.canResetSorting).toBe(true);
        expect(wrapper.find('.sw-media-display-options__sorting-button').classes()).toContain('is--active');
    });

    it('should reset sorting to the default sort order', async () => {
        const wrapper = await createWrapper({
            props: {
                sorting: {
                    sortBy: 'fileName',
                    sortDirection: 'asc',
                },
            },
        });

        wrapper.vm.resetSorting();

        expect(wrapper.emitted('media-sorting-change')).toEqual([[
            {
                sortBy: 'createdAt',
                sortDirection: 'desc',
            },
        ]]);
    });

    it('should emit media type filter changes', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onTypeFilterChanged('video');

        expect(wrapper.emitted('media-type-filter-change')).toEqual([[['video']]]);
    });

    it('should expose supported media type filter options with icons', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.typeFilterOptions).toEqual([
            expect.objectContaining({ value: 'folder', icon: 'regular-folder' }),
            expect.objectContaining({ value: 'image', icon: 'regular-image' }),
            expect.objectContaining({ value: 'video', icon: 'regular-video' }),
            expect.objectContaining({ value: 'document', icon: 'regular-file-text' }),
            expect.objectContaining({ value: 'audio', icon: 'regular-volume-up' }),
            expect.objectContaining({ value: 'spatial', icon: 'regular-3d' }),
        ]);
    });

    it('should toggle multiple media type filters', async () => {
        const wrapper = await createWrapper({
            props: {
                typeFilter: ['folder'],
            },
        });

        wrapper.vm.onToggleTypeFilter('image');

        expect(wrapper.emitted('media-type-filter-change')).toEqual([[[
            'folder',
            'image',
        ]]]);
    });

    it('should select all media type filters', async () => {
        const wrapper = await createWrapper({
            props: {
                typeFilter: ['folder'],
            },
        });

        wrapper.vm.onToggleAllTypeFilters();

        expect(wrapper.emitted('media-type-filter-change')).toEqual([[[
            'folder',
            'image',
            'video',
            'document',
            'audio',
            'spatial',
        ]]]);
    });

    it('should default to no selected media type filters', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.normalizedTypeFilter).toEqual([]);
        expect(wrapper.vm.hasActiveTypeFilter).toBe(false);
        expect(wrapper.vm.typeFilterActionLabel).toBe('sw-media.filter.labelTypeAll');

        wrapper.vm.onSelectAllTypeFilters();

        expect(wrapper.emitted('media-type-filter-change')).toEqual([[[
            'folder',
            'image',
            'video',
            'document',
            'audio',
            'spatial',
        ]]]);
    });

    it('should mark type filter as active when at least one type is selected', async () => {
        const wrapper = await createWrapper({
            props: {
                typeFilter: ['image'],
            },
        });

        expect(wrapper.vm.hasActiveTypeFilter).toBe(true);
        expect(wrapper.find('.sw-media-display-options__type-filter-button').classes()).toContain('is--active');
    });

    it('should clear selected media type filters back to the default state', async () => {
        const wrapper = await createWrapper({
            props: {
                typeFilter: [
                    'folder',
                    'image',
                    'video',
                    'document',
                    'audio',
                    'spatial',
                ],
            },
        });

        expect(wrapper.vm.typeFilterActionLabel).toBe('sw-media.filter.labelTypeClearSelection');

        wrapper.vm.onSelectAllTypeFilters();

        expect(wrapper.emitted('media-type-filter-change')).toEqual([[[]]]);
    });
});
