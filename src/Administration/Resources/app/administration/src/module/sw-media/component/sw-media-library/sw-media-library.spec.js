/**
 * @package content
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-media/mixin/media-grid-listener.mixin';

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

async function createWrapper({ mediaAmount, folderAmount } = { mediaAmount: [5], folderAmount: [5] }) {
    return mount(await wrapTestComponent('sw-media-library', { sync: true }), {
        props: {
            selection: [],
            limit: 5,
        },
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-media-display-options': true,
                'sw-media-entity-mapper': true,
                'sw-media-grid': true,
                'sw-empty-state': true,
                'sw-skeleton': true,
                'sw-button': true,
                'sw-media-folder-item': true,
                'router-link': true,
                'sw-extension-teaser-popover': true,
            },

            provide: {
                repositoryFactory: {
                    create: (repositoryName) => {
                        switch (repositoryName) {
                            case 'media':
                                return new Repository('media', mediaAmount);
                            case 'media_folder':
                                return new Repository('folder', folderAmount);
                            case 'media_folder_configuration':
                                return {};
                            default:
                                throw new Error(`No Repository found for ${repositoryName}`);
                        }
                    },
                },
                mediaService: {},
                searchRankingService: {},
            },
        },
    });
}

describe('src/module/sw-media/component/sw-media-library/index', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

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
    it('should limit association loading to 25', async () => {
        const wrapper = await createWrapper();

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
            filter: [{ type: 'equals', field: 'mediaFolderId', value: null }],
            sort: [{ field: 'fileName', order: 'asc', naturalSorting: false }],
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

    it('should have a computed property for nextFoldersCriteria', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.nextFoldersCriteria.parse()).toEqual({
            page: 1,
            limit: 5,
            term: '',
            filter: [{ type: 'equals', field: 'parentId', value: null }],
            sort: [{ field: 'name', order: 'asc', naturalSorting: false }],
            'total-count-mode': 1,
        });
    });

    it('should return filters from filter registry', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.assetFilter).toEqual(expect.any(Function));
    });
});
