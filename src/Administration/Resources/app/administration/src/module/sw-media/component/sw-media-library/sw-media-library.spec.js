/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-media/mixin/media-grid-listener.mixin';

const MEDIA_LIBRARY_PREFERENCES_KEY = 'media.library.preferences';

class Repository {
    constructor(entityName, amounts) {
        this.#entityName = entityName;
        this.#amounts = amounts;
    }

    #entityName = '';

    #amounts = [];

    invocation = 0;

    lastUsedCriteria;

    search(criteria) {
        const desiredAmount = this.#amounts[this.invocation];

        this.invocation += 1;
        this.lastUsedCriteria = criteria;

        const data = [];

        if (desiredAmount === null) {
            return Promise.reject();
        }

        for (let i = 0; i < desiredAmount; i += 1) {
            data.push({
                id: `${this.#entityName}-${this.invocation}-${i}`,
                getEntityName: () => this.#entityName,
            });
        }

        return Promise.resolve(data);
    }
}

async function createWrapper(
    { mediaAmount, folderAmount } = { mediaAmount: [5], folderAmount: [5] },
    props = {},
    { userConfigService } = {},
) {
    const mediaRepositoryMock = new Repository('media', mediaAmount);
    const folderRepositoryMock = new Repository('media_folder', folderAmount);
    const userConfigServiceMock = userConfigService ?? {
        search: jest.fn().mockResolvedValue({ data: {} }),
        upsert: jest.fn().mockResolvedValue(),
    };

    return mount(await wrapTestComponent('sw-media-library', { sync: true }), {
        props: {
            selection: [],
            limit: 5,
            ...props,
        },
        global: {
            renderStubDefaultSlot: true,
            mocks: {
                $route: {
                    meta: {
                        $module: {
                            icon: 'regular-folder',
                        },
                    },
                },
            },
            stubs: {
                'sw-media-display-options': true,
                'sw-media-entity-mapper': true,
                'sw-media-grid': true,
                'mt-empty-state': {
                    props: [
                        'headline',
                        'description',
                        'linkText',
                        'linkHref',
                    ],
                    template: `
                        <div class="mt-empty-state">
                            <h2 class="mt-empty-state__headline">{{ headline }}</h2>
                            <p class="mt-empty-state__description">{{ description }}</p>
                            <a
                                v-if="linkText"
                                class="mt-empty-state__link"
                                :href="linkHref"
                            >{{ linkText }}</a>
                        </div>
                    `,
                },
                'sw-skeleton': true,
                'sw-media-folder-item': true,
                'router-link': true,
                'sw-extension-teaser-popover': true,
            },

            provide: {
                repositoryFactory: {
                    create: (repositoryName) => {
                        switch (repositoryName) {
                            case 'media':
                                return mediaRepositoryMock;
                            case 'media_folder':
                                return folderRepositoryMock;
                            case 'media_folder_configuration':
                                return {};
                            default:
                                throw new Error(`No Repository found for ${repositoryName}`);
                        }
                    },
                },
                mediaService: {},
                searchRankingService: {
                    isValidTerm: (term) => {
                        return term && term.trim().length >= 1;
                    },
                },
                userConfigService: userConfigServiceMock,
            },
        },
    });
}

describe('src/module/sw-media/component/sw-media-library/index', () => {
    it('should allow loading of additional folders', async () => {
        const wrapper = await createWrapper({
            folderAmount: [
                5,
                5,
                3,
            ],
            mediaAmount: [
                5,
                3,
            ],
        });
        await flushPromises();

        // Check that it starts with the correct amounts
        expect(wrapper.vm.subFolders).toHaveLength(5);
        expect(wrapper.vm.items).toHaveLength(5);
        expect(wrapper.vm.selectableItems).toHaveLength(10);

        // Check that additional media and folders can be loaded
        expect(wrapper.vm.itemLoaderDone).toBe(false);
        expect(wrapper.vm.folderLoaderDone).toBe(false);

        // Initiate another load
        let loadMoreButton = wrapper.get('.sw-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(true);
        wrapper.vm.loadNextItems();
        await flushPromises();

        // Check that appropriate amounts were loaded
        expect(wrapper.vm.subFolders).toHaveLength(10);
        expect(wrapper.vm.items).toHaveLength(8);
        expect(wrapper.vm.selectableItems).toHaveLength(18);

        // Check that additional folders can be loaded, but not media
        expect(wrapper.vm.itemLoaderDone).toBe(true);
        expect(wrapper.vm.folderLoaderDone).toBe(false);

        // Initiate another load
        loadMoreButton = wrapper.get('.sw-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(true);
        wrapper.vm.loadNextItems();
        await flushPromises();

        // Check that appropriate amounts were loaded
        expect(wrapper.vm.subFolders).toHaveLength(13);
        expect(wrapper.vm.items).toHaveLength(8);
        expect(wrapper.vm.selectableItems).toHaveLength(21);

        // Check that no further media and folders can be loaded
        expect(wrapper.vm.itemLoaderDone).toBe(true);
        expect(wrapper.vm.folderLoaderDone).toBe(true);

        // Check that the 'Load more' button disappeared
        loadMoreButton = wrapper.find('.sw-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(false);
    });

    it('should allow loading of additional media', async () => {
        const wrapper = await createWrapper({
            folderAmount: [
                5,
                3,
            ],
            mediaAmount: [
                5,
                5,
                3,
            ],
        });
        await flushPromises();

        // Check that it starts with the correct amounts
        expect(wrapper.vm.subFolders).toHaveLength(5);
        expect(wrapper.vm.items).toHaveLength(5);
        expect(wrapper.vm.selectableItems).toHaveLength(10);

        // Check that more media and folders can be loaded
        expect(wrapper.vm.itemLoaderDone).toBe(false);
        expect(wrapper.vm.folderLoaderDone).toBe(false);

        // Initiate another load
        let loadMoreButton = wrapper.get('.sw-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(true);
        wrapper.vm.loadNextItems();
        await flushPromises();

        // Check that appropriate amounts were loaded
        expect(wrapper.vm.subFolders).toHaveLength(8);
        expect(wrapper.vm.items).toHaveLength(10);
        expect(wrapper.vm.selectableItems).toHaveLength(18);

        // Check that more media can be loaded, but not folders
        expect(wrapper.vm.itemLoaderDone).toBe(false);
        expect(wrapper.vm.folderLoaderDone).toBe(true);

        // Initiate another load
        loadMoreButton = wrapper.get('.sw-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(true);
        wrapper.vm.loadNextItems();
        await flushPromises();

        // Check that appropriate amounts were loaded
        expect(wrapper.vm.subFolders).toHaveLength(8);
        expect(wrapper.vm.items).toHaveLength(13);
        expect(wrapper.vm.selectableItems).toHaveLength(21);

        // Check that no further media and folders can be loaded
        expect(wrapper.vm.itemLoaderDone).toBe(true);
        expect(wrapper.vm.folderLoaderDone).toBe(true);

        // Check that the 'Load more' button disappeared
        loadMoreButton = wrapper.find('.sw-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(false);
    });

    it('should show items as selected when the selection contains the same media id', async () => {
        const wrapper = await createWrapper({
            folderAmount: [0],
            mediaAmount: [1],
        });
        await flushPromises();

        const mediaItem = wrapper.vm.items[0];
        const selectedMedia = {
            id: mediaItem.id,
            getEntityName: () => 'media',
        };

        await wrapper.setProps({
            selection: [selectedMedia],
        });

        expect(wrapper.vm.showItemSelected(mediaItem)).toBe(true);
    });

    it('should allow setting the list selection start item', async () => {
        const wrapper = await createWrapper({
            folderAmount: [0],
            mediaAmount: [1],
        });
        await flushPromises();

        const mediaItem = wrapper.vm.items[0];
        wrapper.vm.setListSelectionStartItem(mediaItem);

        expect(wrapper.vm.listSelectionStartItem).toBe(mediaItem);
        expect(wrapper.vm.isListSelect).toBe(true);
    });

    it('should limit association loading to 25', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.nextMedia();

        const usedCriteria = wrapper.vm.mediaRepository.lastUsedCriteria;

        expect(wrapper.vm.mediaRepository.invocation).toBe(2);

        [
            'tags',
            'productMedia.product',
            'categories',
            'productManufacturers.products',
            'mailTemplateMedia.mailTemplate',
            'documentBaseConfigs',
            'avatarUsers',
            'paymentMethods',
            'shippingMethods',
            'cmsBlocks.section.page',
            'cmsSections.page',
            'cmsPages',
        ].forEach((association) => {
            const associationParts = association.split('.');

            let path = null;
            associationParts.forEach((currentPart) => {
                path = path ? `${path}.${currentPart}` : currentPart;

                expect(usedCriteria.getAssociation(path).getLimit()).toBe(25);
            });
        });
    });

    it('should show the load more button if the folder request fails', async () => {
        const wrapper = await createWrapper({
            folderAmount: [
                null,
                3,
            ],
            mediaAmount: [
                3,
                undefined,
            ],
        });
        await flushPromises();

        // Check that it starts with the correct amounts
        expect(wrapper.vm.subFolders).toHaveLength(0);
        expect(wrapper.vm.items).toHaveLength(3);
        expect(wrapper.vm.selectableItems).toHaveLength(3);

        // Check that additional media and folders can be loaded
        expect(wrapper.vm.itemLoaderDone).toBe(true);
        expect(wrapper.vm.folderLoaderDone).toBe(false);

        // Initiate another load
        let loadMoreButton = wrapper.get('.sw-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(true);
        wrapper.vm.loadNextItems();
        await flushPromises();

        // Check that appropriate amounts were loaded
        expect(wrapper.vm.subFolders).toHaveLength(3);
        expect(wrapper.vm.items).toHaveLength(3);
        expect(wrapper.vm.selectableItems).toHaveLength(6);

        // Check that additional folders can be loaded, but not media
        expect(wrapper.vm.itemLoaderDone).toBe(true);
        expect(wrapper.vm.folderLoaderDone).toBe(true);

        loadMoreButton = wrapper.find('.sw-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(false);
    });

    it('should show the load more button if the media request fails', async () => {
        const wrapper = await createWrapper({
            folderAmount: [
                3,
                undefined,
            ],
            mediaAmount: [
                null,
                3,
            ],
        });
        await flushPromises();

        // Check that it starts with the correct amounts
        expect(wrapper.vm.subFolders).toHaveLength(3);
        expect(wrapper.vm.items).toHaveLength(0);
        expect(wrapper.vm.selectableItems).toHaveLength(3);

        // Check that additional media and folders can be loaded
        expect(wrapper.vm.itemLoaderDone).toBe(false);
        expect(wrapper.vm.folderLoaderDone).toBe(true);

        // Initiate another load
        let loadMoreButton = wrapper.get('.sw-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(true);
        wrapper.vm.loadNextItems();
        await flushPromises();

        // Check that appropriate amounts were loaded
        expect(wrapper.vm.subFolders).toHaveLength(3);
        expect(wrapper.vm.items).toHaveLength(3);
        expect(wrapper.vm.selectableItems).toHaveLength(6);

        // Check that additional folders can be loaded, but not media
        expect(wrapper.vm.itemLoaderDone).toBe(true);
        expect(wrapper.vm.folderLoaderDone).toBe(true);

        loadMoreButton = wrapper.find('.sw-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(false);
    });

    it('should have a computed property for nextMediaCriteria', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.nextMediaCriteria.parse()).toEqual({
            page: 1,
            limit: 5,
            term: '',
            sort: [{ field: 'createdAt', order: 'desc', naturalSorting: false }],
            associations: {
                tags: { limit: 25, 'total-count-mode': 1 },
                productMedia: {
                    limit: 25,
                    associations: expect.any(Object),
                    'total-count-mode': 1,
                },
                categories: { limit: 25, 'total-count-mode': 1 },
                productManufacturers: {
                    limit: 25,
                    associations: expect.any(Object),
                    'total-count-mode': 1,
                },
                mailTemplateMedia: {
                    limit: 25,
                    associations: expect.any(Object),
                    'total-count-mode': 1,
                },
                documentBaseConfigs: { limit: 25, 'total-count-mode': 1 },
                avatarUsers: { limit: 25, 'total-count-mode': 1 },
                paymentMethods: { limit: 25, 'total-count-mode': 1 },
                shippingMethods: { limit: 25, 'total-count-mode': 1 },
                cmsBlocks: {
                    limit: 25,
                    associations: expect.any(Object),
                    'total-count-mode': 1,
                },
                cmsSections: {
                    limit: 25,
                    associations: expect.any(Object),
                    'total-count-mode': 1,
                },
                cmsPages: { limit: 25, 'total-count-mode': 1 },
            },
            'total-count-mode': 1,
        });
    });

    it('should load saved media library preferences', async () => {
        const userConfigService = {
            search: jest.fn().mockResolvedValue({
                data: {
                    [MEDIA_LIBRARY_PREFERENCES_KEY]: {
                        presentation: 'small-preview',
                        sorting: {
                            sortBy: 'createdAt',
                            sortDirection: 'desc',
                        },
                        typeFilter: [
                            'folder',
                            'video',
                        ],
                    },
                },
            }),
            upsert: jest.fn().mockResolvedValue(),
        };

        const wrapper = await createWrapper(undefined, {}, { userConfigService });
        await flushPromises();

        expect(userConfigService.search).toHaveBeenCalledWith([MEDIA_LIBRARY_PREFERENCES_KEY]);
        expect(wrapper.vm.presentation).toBe('small-preview');
        expect(wrapper.vm.sorting).toEqual({
            sortBy: 'createdAt',
            sortDirection: 'desc',
        });
        expect(wrapper.vm.typeFilter).toEqual([
            'folder',
            'video',
        ]);
        expect(userConfigService.upsert).not.toHaveBeenCalled();
    });

    it('should ignore removed large preview user preferences', async () => {
        const userConfigService = {
            search: jest.fn().mockResolvedValue({
                data: {
                    [MEDIA_LIBRARY_PREFERENCES_KEY]: {
                        presentation: 'large-preview',
                    },
                },
            }),
            upsert: jest.fn().mockResolvedValue(),
        };

        const wrapper = await createWrapper(undefined, {}, { userConfigService });
        await flushPromises();

        expect(wrapper.vm.presentation).toBe('medium-preview');
        expect(userConfigService.upsert).not.toHaveBeenCalled();
    });

    it('should save media library preferences when settings change', async () => {
        const userConfigService = {
            search: jest.fn().mockResolvedValue({ data: {} }),
            upsert: jest.fn().mockResolvedValue(),
        };
        const wrapper = await createWrapper(undefined, {}, { userConfigService });
        await flushPromises();

        userConfigService.upsert.mockClear();
        wrapper.vm.typeFilter = ['audio'];
        await flushPromises();

        expect(userConfigService.upsert).toHaveBeenCalledWith({
            [MEDIA_LIBRARY_PREFERENCES_KEY]: {
                presentation: 'medium-preview',
                sorting: {
                    sortBy: 'createdAt',
                    sortDirection: 'desc',
                },
                typeFilter: ['audio'],
            },
        });

        userConfigService.upsert.mockClear();
        wrapper.vm.sorting = {
            sortBy: 'fileExtension',
            sortDirection: 'desc',
        };
        await flushPromises();

        expect(userConfigService.upsert).toHaveBeenCalledWith({
            [MEDIA_LIBRARY_PREFERENCES_KEY]: expect.objectContaining({
                sorting: {
                    sortBy: 'fileExtension',
                    sortDirection: 'desc',
                },
            }),
        });

        userConfigService.upsert.mockClear();
        wrapper.vm.presentation = 'list-preview';
        await flushPromises();

        expect(userConfigService.upsert).toHaveBeenCalledWith({
            [MEDIA_LIBRARY_PREFERENCES_KEY]: expect.objectContaining({
                presentation: 'list-preview',
            }),
        });
    });

    it('should keep default media library preferences when user config is unavailable', async () => {
        const wrapper = await createWrapper(
            undefined,
            {},
            {
                userConfigService: {
                    search: jest.fn().mockRejectedValue(new Error('User config is unavailable')),
                    upsert: jest.fn().mockResolvedValue(),
                },
            },
        );
        await flushPromises();

        expect(wrapper.vm.presentation).toBe('medium-preview');
        expect(wrapper.vm.sorting).toEqual({
            sortBy: 'createdAt',
            sortDirection: 'desc',
        });
        expect(wrapper.vm.typeFilter).toEqual([]);
        expect(wrapper.vm.items).toHaveLength(5);
    });

    it('should not add media type filters by default', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.typeFilter).toEqual([]);
        expect(wrapper.vm.shouldLoadFolders).toBe(true);
        expect(wrapper.vm.shouldLoadMedia).toBe(true);
        expect(wrapper.vm.nextMediaCriteria.parse().filter).toBeUndefined();
    });

    it.each([
        [
            'image',
            { type: 'prefix', field: 'mimeType', value: 'image/' },
        ],
        [
            'video',
            { type: 'prefix', field: 'mimeType', value: 'video/' },
        ],
        [
            'audio',
            { type: 'prefix', field: 'mimeType', value: 'audio/' },
        ],
        [
            'document',
            expect.objectContaining({
                type: 'equalsAny',
                field: 'fileExtension',
                value: expect.stringContaining('doc|'),
            }),
        ],
    ])('should add the %s file type filter to media criteria', async (typeFilter, expectedFilter) => {
        const wrapper = await createWrapper();

        wrapper.vm.typeFilter = typeFilter;

        expect(wrapper.vm.nextMediaCriteria.parse().filter).toEqual(
            expect.arrayContaining([
                expectedFilter,
            ]),
        );
    });

    it('should add the 3D file type filter to media criteria', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.typeFilter = 'spatial';

        expect(wrapper.vm.nextMediaCriteria.parse().filter).toEqual([
            {
                type: 'multi',
                operator: 'OR',
                queries: [
                    { type: 'prefix', field: 'mimeType', value: 'model/' },
                    expect.objectContaining({
                        type: 'equalsAny',
                        field: 'fileExtension',
                        value: expect.stringContaining('glb|gltf'),
                    }),
                ],
            },
        ]);
    });

    it('should combine multiple media type filters with OR', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.typeFilter = [
            'image',
            'video',
        ];

        expect(wrapper.vm.nextMediaCriteria.parse().filter).toEqual([
            {
                type: 'multi',
                operator: 'OR',
                queries: [
                    { type: 'prefix', field: 'mimeType', value: 'image/' },
                    { type: 'prefix', field: 'mimeType', value: 'video/' },
                ],
            },
        ]);
    });

    it('should skip folder loading when folders are not selected', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.typeFilter = ['image'];

        await expect(wrapper.vm.nextFolders()).resolves.toEqual([]);
        expect(wrapper.vm.folderLoaderDone).toBe(true);
    });

    it('should sort media criteria by file type', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.sorting = {
            sortBy: 'fileExtension',
            sortDirection: 'desc',
        };

        expect(wrapper.vm.nextMediaCriteria.parse().sort).toEqual([
            { field: 'fileExtension', order: 'desc', naturalSorting: false },
        ]);
    });

    it('should have a computed property for nextFoldersCriteria', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.nextFoldersCriteria.parse()).toEqual({
            page: 1,
            limit: 5,
            term: '',
            filter: [{ type: 'equals', field: 'parentId', value: null }],
            sort: [{ field: 'createdAt', order: 'desc', naturalSorting: false }],
            'total-count-mode': 1,
        });
    });

    it('should return filters from filter registry', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.assetFilter).toEqual(expect.any(Function));
    });

    it('should refresh media item in items and selectedItems arrays', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const mockMediaItems = [
            {
                id: 'test-media-id-foo',
                getEntityName: () => 'media',
                title: 'Foo Title',
            },
            {
                id: 'test-media-id-bar',
                getEntityName: () => 'media',
                title: 'Bar Title',
            },
        ];

        wrapper.vm.items.push(...mockMediaItems);
        wrapper.vm.selectedItems.push(...mockMediaItems);

        const refreshMediaItem = {
            id: 'test-media-id-foo',
            getEntityName: () => 'media',
            title: 'New Title',
        };

        wrapper.vm.mediaRepository.get = jest.fn().mockResolvedValue(refreshMediaItem);

        await wrapper.vm.refreshItem(refreshMediaItem.id);

        expect(wrapper.vm.mediaRepository.get).toHaveBeenCalledWith(refreshMediaItem.id, expect.any(Object));
        expect(wrapper.vm.items).toContainEqual(refreshMediaItem);
        expect(wrapper.vm.selectedItems).toContainEqual(refreshMediaItem);
    });

    it('should handle refreshItem when media item not found in arrays', async () => {
        const wrapper = await createWrapper();

        const mockMediaItems = [
            {
                id: 'test-media-id-foo',
                getEntityName: () => 'media',
                title: 'Foo Title',
            },
            {
                id: 'test-media-id-bar',
                getEntityName: () => 'media',
                title: 'Bar Title',
            },
        ];

        wrapper.vm.items.push(...mockMediaItems);
        wrapper.vm.selectedItems.push(...mockMediaItems);

        const refreshMediaItem = {
            id: 'test-media-id-new',
            getEntityName: () => 'media',
            title: 'New Title',
        };

        wrapper.vm.mediaRepository.get = jest.fn().mockResolvedValue(refreshMediaItem);

        await wrapper.vm.refreshItem(refreshMediaItem.id);

        expect(wrapper.vm.mediaRepository.get).toHaveBeenCalledWith(refreshMediaItem.id, expect.any(Object));
        expect(wrapper.vm.items).not.toContainEqual(refreshMediaItem);
        expect(wrapper.vm.selectedItems).not.toContainEqual(refreshMediaItem);
    });

    it('should show Add folder button when canCreateFolder is true', async () => {
        const wrapper = await createWrapper();

        let addFolderButton = wrapper.find('.sw-media-index__create-folder-action');
        expect(addFolderButton.exists()).toBe(false);

        await wrapper.setProps({ allowCreateFolder: true });

        addFolderButton = wrapper.find('.sw-media-index__create-folder-action');
        expect(addFolderButton.exists()).toBe(true);
    });

    it('should show the scroll fade only when media content is scrolled', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.classes()).not.toContain('sw-media-library--has-scroll-fade');

        wrapper.vm.$refs.scrollContainer.scrollTop = 20;
        await wrapper.find('.sw-media-library__scroll-container').trigger('scroll');

        expect(wrapper.classes()).toContain('sw-media-library--has-scroll-fade');

        wrapper.vm.$refs.scrollContainer.scrollTop = 0;
        await wrapper.find('.sw-media-library__scroll-container').trigger('scroll');

        expect(wrapper.classes()).not.toContain('sw-media-library--has-scroll-fade');
    });

    it('should show empty folder state copy without link', async () => {
        const wrapper = await createWrapper({
            mediaAmount: [0],
            folderAmount: [0],
        });
        await flushPromises();

        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('sw-media.mediaLibrary.titleFolderEmptyState');
        expect(wrapper.find('.mt-empty-state__description').text()).toBe(
            'sw-media.mediaLibrary.descriptionFolderEmptyState',
        );
        expect(wrapper.find('.mt-empty-state__link').exists()).toBe(false);
    });

    it('should not show the empty state while more media or folders can still load', async () => {
        const wrapper = await createWrapper({
            mediaAmount: [0],
            folderAmount: [null],
        });
        await flushPromises();

        expect(wrapper.vm.selectableItems).toHaveLength(0);
        expect(wrapper.vm.itemLoaderDone).toBe(true);
        expect(wrapper.vm.folderLoaderDone).toBe(false);
        expect(wrapper.find('.mt-empty-state').exists()).toBe(false);
        expect(wrapper.find('.sw-media-library__load-more-button').exists()).toBe(true);
    });

    it('should show empty folder state copy when active type filters cannot match anything in the folder', async () => {
        const wrapper = await createWrapper(
            {
                mediaAmount: [0],
                folderAmount: [0],
            },
            {},
            {
                userConfigService: {
                    search: jest.fn().mockResolvedValue({
                        data: {
                            [MEDIA_LIBRARY_PREFERENCES_KEY]: {
                                typeFilter: ['video'],
                            },
                        },
                    }),
                    upsert: jest.fn().mockResolvedValue(),
                },
            },
        );
        await flushPromises();

        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('sw-media.mediaLibrary.titleFolderEmptyState');
        expect(wrapper.find('.mt-empty-state__description').text()).toBe(
            'sw-media.mediaLibrary.descriptionFolderEmptyState',
        );
        expect(wrapper.find('.mt-empty-state__link').exists()).toBe(false);
    });

    it('should show filtered empty state copy when a type filter has no matches in a non-empty folder', async () => {
        const wrapper = await createWrapper(
            {
                mediaAmount: [
                    0,
                    1,
                ],
                folderAmount: [0],
            },
            {},
            {
                userConfigService: {
                    search: jest.fn().mockResolvedValue({
                        data: {
                            [MEDIA_LIBRARY_PREFERENCES_KEY]: {
                                typeFilter: ['video'],
                            },
                        },
                    }),
                    upsert: jest.fn().mockResolvedValue(),
                },
            },
        );
        await flushPromises();

        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('sw-media.mediaLibrary.titleFilteredEmptyState');
        expect(wrapper.find('.mt-empty-state__description').text()).toBe(
            'sw-media.mediaLibrary.descriptionFilteredEmptyState',
        );
        expect(wrapper.find('.mt-empty-state__link').exists()).toBe(false);
    });
});
